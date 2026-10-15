import 'dart:async';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/meeting_engine.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter_test/flutter_test.dart';

class PendingApi extends Api {
  PendingApi() : super(Session());
  final response = Completer<dynamic>();
  final paths = <String>[];
  @override Future<dynamic> post(String path, [Object? body]) {
    paths.add(path);
    return response.future;
  }
}
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test('audio hangup ends local state before an unresponsive server replies', () async {
    final api = PendingApi();
    final call = CallSession(api, {'id': 42, 'peer': {'name': 'Alice'}}, outgoing: true);
    CallManager.I.active = call;
    await call.hangUp().timeout(const Duration(seconds: 1));
    expect(call.state, 'ended');
    expect(CallManager.I.active, isNull);
    expect(api.paths, ['calls/42/end']);
    expect(api.response.isCompleted, false);
    api.response.complete({});
    call.dispose(); api.session.dispose();
  });
  test('meeting leave closes media state before server acknowledgement', () async {
    final api = PendingApi();
    final room = MeetingEngine(api, 'testroom')..pid = 7..phase = 'in';
    await room.leave().timeout(const Duration(seconds: 1));
    expect(room.phase, 'ended');
    expect(api.paths, ['meetings/testroom/leave']);
    expect(api.response.isCompleted, false);
    api.response.complete({});
    room.dispose(); api.session.dispose();
  });
  test('host termination keeps media closed but reports a rejected server action', () async {
    final api = PendingApi();
    final room = MeetingEngine(api, 'testroom')..pid = 7..phase = 'in'..role = 'host';
    final closing = room.endForAll();
    expect(room.phase, 'ended');
    api.response.completeError(ApiException(403, 'Host role changed.'));
    await closing;
    expect(room.phase, 'error');
    expect(room.message, contains('Could not confirm'));
    expect(api.paths, ['meetings/testroom/host']);
    room.dispose(); api.session.dispose();
  });
  test('cohosts cannot claim that they closed the meeting for everyone', () async {
    final api = PendingApi();
    final room = MeetingEngine(api, 'testroom')..pid = 7..phase = 'in'..role = 'cohost';
    await room.endForAll();
    expect(room.phase, 'in');
    expect(api.paths, isEmpty);
    room.dispose(); api.session.dispose();
  });

  test('native answer is validated before media and cancelling its pending handoff ends the call', () async {
    final api = PendingApi();
    final room = MeetingEngine(api, 'testroom');
    final joining = room.join(audio: true, video: false, answerCallId: 42);
    expect(room.phase, 'joining');
    expect(api.paths, ['calls/42/answer']);
    await room.leave();
    api.response.complete({});
    await joining;
    expect(api.paths, contains('calls/42/end'));
    expect(room.phase, 'ended');
    room.dispose(); api.session.dispose();
  });

  test('expired native answer does not acquire media or join the room', () async {
    final api = PendingApi();
    final room = MeetingEngine(api, 'testroom');
    final joining = room.join(audio: true, video: true, answerCallId: 42);
    api.response.completeError(ApiException(410, 'This call has ended.'));
    await joining;
    expect(api.paths, ['calls/42/answer']);
    expect(room.phase, 'error');
    expect(room.message, 'This call has ended.');
    room.dispose(); api.session.dispose();
  });

}
