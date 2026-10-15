import 'dart:async';
import 'package:dahimail/core/call_tones.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final binding = TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('com.dahify.dahimail/call_tones');
  final calls = <String>[];
  setUp(() {
    calls.clear();
    binding.defaultBinaryMessenger.setMockMethodCallHandler(channel,
        (call) async {
      calls.add(call.method);
      return null;
    });
  });
  tearDown(() =>
      binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, null));

  test('dialing transitions to one connection tone and stops on hangup',
      () async {
    final tones = CallTones();
    tones.dialing();
    await Future<void>.delayed(Duration.zero);
    tones.connected();
    tones.connected();
    await Future<void>.delayed(Duration.zero);
    await tones.stop();
    expect(calls, ['dialing', 'connected', 'stop']);
    tones.dialing();
    tones.connected();
    await Future<void>.delayed(Duration.zero);
    expect(calls, ['dialing', 'connected', 'stop']);
  });

  test('hangup suppresses playback still queued during native initialization',
      () async {
    final entered = Completer<void>(), released = Completer<void>();
    binding.defaultBinaryMessenger.setMockMethodCallHandler(channel,
        (call) async {
      calls.add(call.method);
      if (call.method == 'dialing') {
        entered.complete();
        await released.future;
      }
      return null;
    });
    final tones = CallTones()..dialing();
    await entered.future;
    tones.connected();
    final stopping = tones.stop();
    released.complete();
    await stopping;
    expect(calls, ['dialing', 'stop']);
  });
  test('acceptance silences ringback while media is still connecting', () async {
    final tones = CallTones()..dialing();
    await Future<void>.delayed(Duration.zero);
    tones.accepted();
    tones.accepted();
    tones.dialing();
    await Future<void>.delayed(Duration.zero);
    expect(calls, ['dialing', 'stop']);
    tones.connected();
    await Future<void>.delayed(Duration.zero);
    expect(calls, ['dialing', 'stop', 'connected']);
    await tones.stop();
  });

}
