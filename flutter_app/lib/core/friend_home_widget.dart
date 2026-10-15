import 'dart:async';
import 'dart:math' as math;
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:flutter/services.dart';

/// Android launcher widgets contain account/person IDs, the chosen name and a small local photo.
/// Authentication and friendship checks still happen inside the app.
class FriendHomeWidget {
  static const channel = MethodChannel('com.dahify.dahimail/friend_widget');
  static bool get supported => !kIsWeb && defaultTargetPlatform == TargetPlatform.android;
  static bool _initialized = false;

  static Future<void> init(void Function(Map<String, dynamic>) onOpen) async {
    if (!supported || _initialized) return;
    _initialized = true;
    channel.setMethodCallHandler((call) async {
      if (call.method == 'open' && call.arguments is Map) {
        onOpen(Map<String, dynamic>.from(call.arguments as Map));
      }
    });
    try {
      final action = await channel.invokeMapMethod<String, dynamic>('initialAction');
      if (action != null) onOpen(action);
    } on MissingPluginException {
      // Older installed builds have no widget channel.
    }
  }

  static Future<bool> pin({required int ownerId, required int friendId, required String name, Uint8List? avatar}) async {
    return await channel.invokeMethod<bool>('pin', {'owner_id': ownerId, 'friend_id': friendId, 'name': name, if (avatar != null) 'avatar': avatar}) ?? false;
  }

  static Future<void> configure({required int widgetId, required int ownerId, required int friendId, required String name, Uint8List? avatar}) async {
    await channel.invokeMethod<void>('configure', {'widget_id': widgetId, 'owner_id': ownerId, 'friend_id': friendId, 'name': name, if (avatar != null) 'avatar': avatar});
  }

  /// A bounded, circular thumbnail. Failure keeps the widget's initial fallback.
  static Future<Uint8List?> loadAvatar(String? url) async {
    final uri = Uri.tryParse(url ?? '');
    if (uri == null || !uri.hasAuthority || !['https', 'http'].contains(uri.scheme)) return null;
    final client = http.Client();
    try {
      return await (() async {
        final response = await client.send(http.Request('GET', uri));
        const maxBytes = 5 * 1024 * 1024;
        if (response.statusCode != 200 || (response.contentLength ?? 0) > maxBytes) return null;
        final bytes = BytesBuilder(copy: false);
        await for (final chunk in response.stream) {
          if (bytes.length + chunk.length > maxBytes) return null;
          bytes.add(chunk);
        }
        final buffer = await ui.ImmutableBuffer.fromUint8List(bytes.takeBytes());
        ui.ImageDescriptor? descriptor;
        ui.Codec? codec;
        ui.Image? image;
        ui.Image? thumbnail;
        ui.Picture? picture;
        try {
          descriptor = await ui.ImageDescriptor.encoded(buffer);
          if (descriptor.width > 8192 || descriptor.height > 8192) return null;
          final scale = 256 / math.max(descriptor.width, descriptor.height);
          codec = await descriptor.instantiateCodec(
            targetWidth: math.max(1, (descriptor.width * scale).round()),
            targetHeight: math.max(1, (descriptor.height * scale).round()),
          );
          image = (await codec.getNextFrame()).image;
          final side = math.min(image.width, image.height).toDouble();
          final recorder = ui.PictureRecorder();
          final canvas = ui.Canvas(recorder)..clipPath(ui.Path()..addOval(const ui.Rect.fromLTWH(0, 0, 256, 256)));
          canvas.drawImageRect(image,
            ui.Rect.fromLTWH((image.width - side) / 2, (image.height - side) / 2, side, side),
            const ui.Rect.fromLTWH(0, 0, 256, 256), ui.Paint()..filterQuality = ui.FilterQuality.medium);
          picture = recorder.endRecording();
          thumbnail = await picture.toImage(256, 256);
          final png = await thumbnail.toByteData(format: ui.ImageByteFormat.png);
          return png?.buffer.asUint8List(png.offsetInBytes, png.lengthInBytes);
        } finally {
          thumbnail?.dispose();
          picture?.dispose();
          image?.dispose();
          codec?.dispose();
          descriptor?.dispose();
          buffer.dispose();
        }
      })().timeout(const Duration(seconds: 5));
    } catch (_) {
      return null;
    } finally {
      client.close();
    }
  }

  static Future<void> syncAccount(int ownerId) async {
    if (!supported) return;
    try { await channel.invokeMethod<void>('account', ownerId); } on MissingPluginException { /* Older native build. */ }
  }

  static Future<void> clear() async {
    if (!supported) return;
    try { await channel.invokeMethod<void>('clear'); } on MissingPluginException { /* Older native build. */ }
  }
}
