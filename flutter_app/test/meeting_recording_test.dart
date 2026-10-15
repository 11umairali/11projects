import 'dart:io';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/session.dart';
import 'package:dahimail/core/meeting_recording.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

class RecordingApi extends Api {
  RecordingApi() : super(Session());
  Completer<dynamic>? claim;
  int uploads = 0;
  bool failUpload = false;
  Map<String, String>? uploaded;
  @override Future<dynamic> post(String path, [Object? body]) async {
    if (path.endsWith('/claim')) return claim != null ? claim!.future : {'data': {'claimed': true}};
    return {};
  }
  @override Future<dynamic> multipart(String path, Map<String, String> fields, {String? fileField, String? filePath}) async {
    uploads++; uploaded = fields;
    expect(File(filePath!).lengthSync(), greaterThan(44));
    if (failUpload) throw StateError('Synthetic upload failure');
    return {};
  }
}
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('com.dahify.dahimail/recording');
  late Directory directory;
  late File file;
  late RecordingApi api;
  late MeetingRecording recording;
  late List<String> methods;
  final state = {'enabled': true, 'claim_open': true, 'session': {'id': 42, 'status': 'recording'}};
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    directory = await Directory.systemTemp.createTemp('recording-test');
    file = await File('${directory.path}/call.wav').writeAsBytes(List.filled(100, 0));
    api = RecordingApi(); api.session.user = {'id': 7}; methods = [];
    recording = MeetingRecording(api, 'room', (_) {});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
      methods.add(call.method);
      if (call.method == 'available') return true;
      if (call.method == 'start') return file.path;
      if (call.method == 'stop') return {'path': file.path, 'duration': 3};
      return {};
    });
  });
  tearDown(() async {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, null);
    await directory.delete(recursive: true);
  });
  test('stopping captures final audio and uploads the claimed session once', () async {
    await recording.sync(state, 7, [{'id': 'audio'}]);
    await recording.sync({'enabled': true, 'session': null}, 7, []);
    await recording.finish();
    expect(methods.where((m) => m == 'stop'), hasLength(1));
    expect(api.uploads, 1); expect(api.uploaded!['session_id'], '42');
    expect(api.uploaded!['pid'], '7'); expect(api.uploaded!['video'], '0');
    expect(await file.exists(), false);
  });
  test('failed delivery retains file and retry uses the same session', () async {
    await recording.sync(state, 7, [{'id': 'audio'}]);
    api.failUpload = true;
    await recording.sync({'enabled': true}, 7, []);
    expect(await file.exists(), true); expect(recording.hasPending, true);
    api.failUpload = false;
    await recording.retry();
    expect(api.uploads, 2); expect(api.uploaded!['session_id'], '42');
    expect(await file.exists(), false);
  });
  test('leaving during a pending claim never starts a recorder', () async {
    api.claim = Completer();
    final sync = recording.sync(state, 7, [{'id': 'audio'}]);
    await Future<void>.delayed(Duration.zero);
    final finish = recording.finish();
    api.claim!.complete({'data': {'claimed': true}});
    await sync; await finish;
    expect(methods, isNot(contains('start'))); expect(api.uploads, 0);
  });
  test('pending upload survives a new recorder and is isolated to its account', () async {
    await recording.sync(state, 7, [{'id': 'audio'}]);
    api.failUpload = true;
    await recording.sync({'enabled': true}, 7, []);
    final other = RecordingApi()..session.user = {'id': 8};
    await MeetingRecording.recover(other);
    expect(other.uploads, 0); expect(await file.exists(), true);
    final restored = RecordingApi()..session.user = {'id': 7};
    await MeetingRecording.recover(restored);
    expect(restored.uploads, 1); expect(restored.uploaded!['session_id'], '42');
    expect(await file.exists(), false);
  });

  test('a participant awaiting consent cannot claim capture', () async {
    await recording.sync({...state, 'consent': {'session_id': 42}}, 7, [{'id': 'audio'}]);
    expect(methods, isEmpty); expect(api.uploads, 0);
  });

}
