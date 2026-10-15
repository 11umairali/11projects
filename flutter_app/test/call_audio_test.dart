import 'dart:async';
import 'package:dahimail/core/api.dart';
import 'package:dahimail/core/call_audio.dart';
import 'package:dahimail/core/calls.dart';
import 'package:dahimail/core/meeting_engine.dart';
import 'package:dahimail/core/session.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

class _AudioApi extends Api {
  _AudioApi() : super(Session());
  @override
  Future<dynamic> post(String path, [Object? body]) async => {};
}

void main() {
  final binding = TestWidgetsFlutterBinding.ensureInitialized();
  const rtc = MethodChannel('FlutterWebRTC.Method');
  final calls = <MethodCall>[];
  var failSwitch = false, failCamera = false;
  Completer<Map<String, dynamic>>? capture;
  Completer<void>? captureEntered;
  Completer<Map<String, dynamic>>? peerCreation;
  Completer<void>? peerEntered;
  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    calls.clear();
    failSwitch = false;
    failCamera = false;
    capture = null;
    captureEntered = null;
    peerCreation = null;
    peerEntered = null;
    binding.defaultBinaryMessenger.setMockMethodCallHandler(rtc, (call) async {
      calls.add(call);
      if (failSwitch && call.method == 'enableSpeakerphone') {
        throw PlatformException(code: 'route_failed');
      }
      if (call.method == 'getSources') {
        return {
          'sources': [
            for (final id in [
              'speaker',
              'earpiece',
              'bluetooth',
              'wired-headset'
            ])
              {
                'deviceId': id,
                'groupId': id,
                'kind': 'audiooutput',
                'label': id
              },
            {
              'deviceId': 'camera',
              'groupId': 'camera',
              'kind': 'videoinput',
              'label': 'Camera'
            },
          ]
        };
      }
      if (call.method == 'createVideoRenderer') return {'textureId': 1};
      if (call.method == 'createPeerConnection') {
        peerEntered?.complete();
        if (peerCreation != null) return peerCreation!.future;
        throw PlatformException(code: 'stop_at_peer');
      }
      if (call.method == 'getUserMedia') {
        if (failCamera &&
            (call.arguments as Map)['constraints']['video'] != false) {
          throw PlatformException(code: 'camera_busy');
        }
        captureEntered?.complete();
        if (capture != null) return capture!.future;
        return {'streamId': 'local', 'audioTracks': [], 'videoTracks': []};
      }
      return null;
    });
    binding.defaultBinaryMessenger.setMockMethodCallHandler(
        const MethodChannel('flutter.baseflow.com/permissions/methods'),
        (_) async => 1);
  });
  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    binding.defaultBinaryMessenger.setMockMethodCallHandler(rtc, null);
    binding.defaultBinaryMessenger.setMockMethodCallHandler(
        const MethodChannel('flutter.baseflow.com/permissions/methods'), null);
  });

  test('call and meeting start in automatic headset or receiver mode',
      () async {
    final session = Session();
    final api = Api(session);
    final room = MeetingEngine(api, 'room');
    final call = CallSession(
        api,
        {
          'id': 1,
          'peer': {'name': 'Friend'}
        },
        outgoing: true);
    expect(room.speaker, false);
    expect(call.speaker, false);
    await room.audio.prepare();
    await call.audio.prepare();
    expect(
        calls
            .where((c) => c.method == 'enableSpeakerphone')
            .map((c) => c.arguments),
        [
          {'enable': false},
          {'enable': false}
        ]);
    room.dispose();
    call.dispose();
    session.dispose();
  });

  test(
      'meeting capture applies receiver routing before and after opening media',
      () async {
    final session = Session();
    final room = MeetingEngine(Api(session), 'room');
    expect(await room.startPreview(wantVideo: false), true);
    final media = calls.indexWhere((c) => c.method == 'getUserMedia');
    final routing = calls.indexed
        .where((c) => c.$2.method == 'enableSpeakerphone')
        .toList();
    expect(routing.length, 2);
    expect(routing.first.$1, lessThan(media));
    expect(routing.last.$1, greaterThan(media));
    expect(routing.map((c) => c.$2.arguments), everyElement({'enable': false}));
    await room.leave();
    room.dispose();
    session.dispose();
  });

  test(
      'unavailable camera falls back to microphone capture without ending the room',
      () async {
    final session = Session();
    final room = MeetingEngine(Api(session), 'room');
    failCamera = true;
    expect(await room.startPreview(wantVideo: true), true);
    final requests = calls.where((c) => c.method == 'getUserMedia').toList();
    expect(requests.length, 2);
    expect((requests.last.arguments as Map)['constraints']['video'], false);
    expect(room.camOn, false);
    expect(room.message, contains('continue with audio'));
    await room.shutdown();
    room.dispose();
    session.dispose();
  });

  test('legacy audio capture applies receiver routing before and after media',
      () async {
    final api = _AudioApi();
    final call = CallSession(
        api,
        {
          'id': 1,
          'peer': {'name': 'Friend'}
        },
        outgoing: true);
    await call.startOutgoing();
    final media = calls.indexWhere((c) => c.method == 'getUserMedia');
    final routing = calls.indexed
        .where((c) => c.$2.method == 'enableSpeakerphone')
        .toList();
    expect(routing.length, 2);
    expect(routing.first.$1, lessThan(media));
    expect(routing.last.$1, greaterThan(media));
    expect(routing.map((c) => c.$2.arguments), everyElement({'enable': false}));
    call.dispose();
    api.session.dispose();
  });

  test(
      'ending a call while media opens releases it without applying a late route',
      () async {
    final api = _AudioApi();
    final call = CallSession(
        api,
        {
          'id': 1,
          'peer': {'name': 'Friend'}
        },
        outgoing: true);
    capture = Completer<Map<String, dynamic>>();
    captureEntered = Completer<void>();
    final starting = call.startOutgoing();
    await captureEntered!.future;
    await call.hangUp();
    capture!
        .complete({'streamId': 'late', 'audioTracks': [], 'videoTracks': []});
    await starting;
    expect(call.state, 'ended');
    expect(calls.where((c) => c.method == 'enableSpeakerphone').length, 1);
    expect(calls.any((c) => c.method == 'streamDispose'), true);
    expect(calls.any((c) => c.method == 'createPeerConnection'), false);
    call.dispose();
    api.session.dispose();
  });

  test('hangup while the peer connection opens closes the late connection',
      () async {
    final api = _AudioApi();
    final call = CallSession(
        api,
        {
          'id': 1,
          'peer': {'name': 'Friend'}
        },
        outgoing: true);
    peerCreation = Completer<Map<String, dynamic>>();
    peerEntered = Completer<void>();
    final starting = call.startOutgoing();
    await peerEntered!.future;
    await call.hangUp();
    peerCreation!.complete({'peerConnectionId': 'late-peer'});
    await starting;
    expect(call.state, 'ended');
    expect(calls.any((c) => c.method == 'peerConnectionClose'), true);
    expect(calls.any((c) => c.method == 'createOffer'), false);
    call.dispose();
    api.session.dispose();
  });

  test(
      'speaker requires manual selection and returning to automatic disables it',
      () async {
    final audio = CallAudio();
    expect(await audio.select('speaker'), true);
    expect(audio.speaker, true);
    expect(calls.last.arguments, {'enable': true});
    expect(await audio.select('auto'), true);
    expect(audio.speaker, false);
    expect(calls.last.arguments, {'enable': false});
  });

  test('Android offers connected Bluetooth, wired and receiver outputs',
      () async {
    final audio = CallAudio();
    expect(await audio.available(),
        ['auto', 'speaker', 'earpiece', 'bluetooth', 'wired-headset']);
    expect(await audio.select('earpiece'), true);
    expect(calls.last.method, 'selectAudioOutput');
    expect(calls.last.arguments, {'deviceId': 'earpiece'});
    expect(await audio.select('bluetooth'), true);
    expect(calls.last.arguments, {'deviceId': 'bluetooth'});
    expect(await audio.select('missing'), false);
    expect(audio.output, 'bluetooth');
  });

  test('failed switching retains the previous selection', () async {
    final audio = CallAudio();
    failSwitch = true;
    expect(await audio.select('speaker'), false);
    expect(audio.output, 'auto');
  });

  test('iOS prepares a voice session with both microphone and playback enabled',
      () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    AppleNativeAudioManagement.currentMode = AppleAudioIOMode.none;
    await CallAudio().prepare();
    expect(AppleNativeAudioManagement.currentMode,
        AppleAudioIOMode.localAndRemote);
    // The plugin's iOS method is gated by Platform.isIOS on this Linux test host.
    final configuration =
        AppleNativeAudioManagement.getAppleAudioConfigurationForMode(
            AppleNativeAudioManagement.currentMode);
    expect(configuration.appleAudioCategory, AppleAudioCategory.playAndRecord);
    expect(configuration.appleAudioMode, AppleAudioMode.voiceChat);
    expect(calls.last.arguments, {'enable': false});
  });

  test('iOS offers automatic and speaker through its supported route API',
      () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    final audio = CallAudio();
    expect(await audio.available(), ['auto', 'speaker']);
    expect(await audio.select('auto'), true);
    expect(calls.last.arguments, {'enable': false});
  });

  testWidgets('audio picker selects speaker only after tapping that option',
      (tester) async {
    final audio = CallAudio();
    await tester.pumpWidget(MaterialApp(
        home: Builder(
            builder: (c) => Scaffold(
                    body: TextButton(
                  onPressed: () => showCallAudioPicker(c, audio, audio.select),
                  child: const Text('Audio'),
                )))));
    await tester.tap(find.text('Audio'));
    await tester.pumpAndSettle();
    expect(audio.speaker, false);
    expect(find.text('Bluetooth'), findsOneWidget);
    await tester.tap(find.text('Speaker'));
    await tester.pumpAndSettle();
    expect(audio.speaker, true);
    expect(calls.last.arguments, {'enable': true});
    debugDefaultTargetPlatformOverride = null;
  });
}
