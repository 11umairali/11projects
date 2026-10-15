import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_saver/file_saver.dart';
import 'package:http/http.dart' as http;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:video_player/video_player.dart';
import '../core/config.dart';
import '../core/chat_cache.dart';
import '../core/session.dart';
import '../core/util.dart';

/// Downloaded chat files live on the phone (like WhatsApp): the first tap downloads with a progress ring, after that they open at once.
class ChatMediaStore {
  static String _safe(String n) => n.replaceAll(RegExp(r'[^\w.\- ]'), '_');

  static Future<File> _file(String account, int id, String name) async {
    final dir = Directory('${(await getApplicationSupportDirectory()).path}/chat_media/$account');
    if (!await dir.exists()) await dir.create(recursive: true);
    return File('${dir.path}/${id}_${_safe(name)}');
  }

  static String? _account(BuildContext context) {
    final session = context.read<Session>();
    return ChatCache.accountKey(AppConfig.apiBase, session.userId, session.token);
  }

  static Future<File?> cached(BuildContext context, int id, String name) async {
    final account = _account(context);
    if (account == null) return null;
    final f = await _file(account, id, name);
    return (await f.exists() && await f.length() > 0) ? f : null;
  }

  /// a file you just sent is already on the phone: keep a copy so it shows at once
  /// "Download": the phone's Save-as dialog (choose Downloads, Gallery, Drive ...). The file is already on the phone; this makes a copy where YOU choose.
  static Future<void> saveToDevice(BuildContext context, File f, String name) async {
    try {
      final bytes = await f.readAsBytes();
      final dot = name.lastIndexOf('.');
      final base = dot > 0 ? name.substring(0, dot) : name;
      final ext = dot > 0 ? name.substring(dot + 1) : '';
      final saved = await FileSaver.instance.saveAs(name: base, bytes: bytes, ext: ext, mimeType: MimeType.other);
      if (context.mounted && saved != null && saved.isNotEmpty) toast(context, 'Saved');
    } catch (_) {
      if (context.mounted) toast(context, 'Could not save the file', error: true);
    }
  }

  static Future<void> keepSent(BuildContext context, int id, String name, String path) async {
    try {
      final account = _account(context);
      if (account == null) return;
      final f = await _file(account, id, name);
      await File(path).copy(f.path);
    } catch (_) {}
  }

  static Future<File> download(BuildContext context, int id, String name, int size, void Function(double) onProgress) async {
    final token = context.read<Session>().token;
    final account = _account(context);
    if (account == null) throw StateError('Sign in to download attachments');
    final target = await _file(account, id, name);
    final part = File('${target.path}.part');
    final client = http.Client();
    try {
      final req = http.Request('GET', Uri.parse('${AppConfig.apiBase}/friends/messages/$id/file'))..headers.addAll({'Accept': '*/*', if (token != null) 'Authorization': 'Bearer $token'});
      final res = await client.send(req).timeout(const Duration(seconds: 60));
      if (res.statusCode >= 400) throw Exception('HTTP ${res.statusCode}');
      final total = (res.contentLength != null && res.contentLength! > 0) ? res.contentLength! : size;
      var got = 0;
      final sink = part.openWrite();
      try {
      await for (final chunk in res.stream.timeout(const Duration(seconds: 60))) {
        sink.add(chunk);
        got += chunk.length;
        if (total > 0) onProgress((got / total).clamp(0.0, 1.0));
      }
      await sink.flush();
      } finally { await sink.close(); }
      if (total > 0 && got != total) throw Exception('Incomplete download');
      if (await target.exists()) await target.delete();
      await part.rename(target.path);
      return target;
    } finally {
      if (await part.exists()) await part.delete();
      client.close();
    }
  }
}

String fileSizeText(int n) => n < 1024 ? '$n B' : (n < 1048576 ? '${(n / 1024).round()} KB' : '${(n / 1048576).toStringAsFixed(1)} MB');

/// One attachment in a chat bubble. image / video / audio / pdf / other – all use the same download-then-show flow.
class ChatFileTile extends StatefulWidget {
  final int messageId;
  final Map file; // {name, mime, type, size}
  final Color fg;
  const ChatFileTile({super.key, required this.messageId, required this.file, required this.fg});
  @override
  State<ChatFileTile> createState() => _ChatFileTileState();
}

class _ChatFileTileState extends State<ChatFileTile> {
  File? _local;
  bool _checked = false, _busy = false, _failed = false;
  double _p = 0;
  VideoPlayerController? _v;
  bool _openingVideo = false;

  String get _name => '${widget.file['name'] ?? 'file'}';
  String get _type => '${widget.file['type'] ?? 'other'}';
  int get _size => (widget.file['size'] as num?)?.toInt() ?? 0;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    final f = await ChatMediaStore.cached(context, widget.messageId, _name);
    if (!mounted) return;
    setState(() { _local = f; _checked = true; });
    if (f == null && _type == 'image' && _size <= 8 * 1024 * 1024) _get(); // photos download by themselves, like on WhatsApp
  }

  Future<File?> _get() async {
    if (_busy) return null;
    setState(() { _busy = true; _failed = false; _p = 0; });
    try {
      final f = await ChatMediaStore.download(context, widget.messageId, _name, _size, (v) { if (mounted) setState(() => _p = v); });
      if (mounted) setState(() { _local = f; _busy = false; });
      return f;
    } catch (_) {
      if (mounted) setState(() { _busy = false; _failed = true; });
      return null;
    }
  }

  @override
  void dispose() {
    _v?.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final f = _local ?? await _get();
    if (f != null && mounted) await ChatMediaStore.saveToDevice(context, f, _name);
  }

  Widget _ring(Color c) => SizedBox(width: 44, height: 44, child: Stack(alignment: Alignment.center, children: [
        CircularProgressIndicator(value: _p > 0 ? _p : null, strokeWidth: 3, color: c, backgroundColor: c.withValues(alpha: 0.25)),
        Icon(Icons.close_rounded, size: 18, color: c),
      ]));

  Widget _downloadBadge() => Container(
        padding: const EdgeInsets.all(8),
        decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
        child: _busy ? _ring(Colors.white) : const Icon(Icons.arrow_downward_rounded, color: Colors.white, size: 26),
      );

  @override
  Widget build(BuildContext context) {
    final fg = widget.fg;
    if (!_checked) return const SizedBox(width: 200, height: 64, child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))));
    if (_type == 'image') return _image();
    if (_type == 'video') return _video();
    final icon = _type == 'pdf' ? Icons.picture_as_pdf_rounded : (_type == 'audio' ? Icons.audiotrack_rounded : Icons.insert_drive_file_rounded);
    return InkWell(
      onTap: () async {
        final f = _local ?? await _get();
        if (f == null) return;
        final r = await OpenFilex.open(f.path);
        if (r.type != ResultType.done && mounted) toast(context, 'No app on this phone can open this file type', error: true);
      },
      child: SizedBox(
        width: 230,
        child: Row(children: [
          Icon(icon, color: fg, size: 34),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(_name, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg, fontWeight: FontWeight.w700, fontSize: 14)),
            Text(_failed ? 'Download failed – tap to retry' : (_busy ? '${(_p * 100).round()}%' : fileSizeText(_size)), style: TextStyle(color: fg.withValues(alpha: 0.75), fontSize: 12)),
          ])),
          const SizedBox(width: 8),
          if (_local == null) (_busy ? _ring(fg) : Icon(Icons.download_for_offline_rounded, color: fg, size: 30)) else Row(mainAxisSize: MainAxisSize.min, children: [InkWell(onTap: _save, child: Padding(padding: const EdgeInsets.all(4), child: Icon(Icons.download_rounded, color: fg.withValues(alpha: 0.9), size: 24))), Icon(Icons.open_in_new_rounded, color: fg.withValues(alpha: 0.8), size: 20)]),
        ]),
      ),
    );
  }

  Widget _image() {
    final f = _local;
    if (f == null) {
      return InkWell(
        onTap: _busy ? null : _get,
        child: Container(width: 220, height: 160, decoration: BoxDecoration(color: Colors.black26, borderRadius: BorderRadius.circular(12)), child: Center(child: _failed ? const Text('Tap to retry', style: TextStyle(color: Colors.white)) : _downloadBadge())),
      );
    }
    return GestureDetector(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => Scaffold(
          backgroundColor: Colors.black,
          appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: Text(_name, style: const TextStyle(fontSize: 14)), actions: [IconButton(tooltip: 'Download', icon: const Icon(Icons.download_rounded), onPressed: _save)]),
          body: Center(child: InteractiveViewer(maxScale: 5, child: Image.file(f))),
        ),
      )),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Stack(children: [ConstrainedBox(constraints: const BoxConstraints(maxWidth: 240, maxHeight: 300), child: Image.file(f, fit: BoxFit.cover, gaplessPlayback: true, errorBuilder: (_, __, ___) => const SizedBox(width: 200, height: 80, child: Center(child: Text('Photo could not be shown'))))), Positioned(right: 6, top: 6, child: Material(color: Colors.black54, shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: _save, child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.download_rounded, color: Colors.white, size: 20)))))]),
      ),
    );
  }

  Future<void> _play() async {
    final f = _local;
    if (f == null || _openingVideo || _v != null) return;
    _openingVideo = true;
    VideoPlayerController? c;
    try {
      c = VideoPlayerController.file(f);
      await c.initialize();
      if (!mounted) { await c.dispose(); return; }
      c.addListener(() { if (mounted) setState(() {}); });
      await c.play();
      if (mounted) setState(() => _v = c);
    } catch (_) {
      await c?.dispose();
      final r = await OpenFilex.open(f.path);
      if (r.type != ResultType.done && mounted) toast(context, 'Could not play this video', error: true);
    } finally { _openingVideo = false; }
  }

  Widget _video() {
    final c = _v;
    if (c != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: SizedBox(width: 240, child: Stack(alignment: Alignment.bottomCenter, children: [
          AspectRatio(aspectRatio: c.value.aspectRatio == 0 ? 16 / 9 : c.value.aspectRatio, child: VideoPlayer(c)),
          Positioned.fill(child: GestureDetector(onTap: () => c.value.isPlaying ? c.pause() : c.play(), child: Center(child: c.value.isPlaying ? const SizedBox() : const Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 52)))),
          VideoProgressIndicator(c, allowScrubbing: true, padding: const EdgeInsets.only(top: 6)),
          Positioned(right: 6, top: 6, child: Material(color: Colors.black54, shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: _save, child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.download_rounded, color: Colors.white, size: 20))))),
        ])),
      );
    }
    return InkWell(
      onTap: _busy ? null : (_local == null ? _get : _play),
      child: Container(
        width: 230,
        height: 140,
        decoration: BoxDecoration(color: Colors.black87, borderRadius: BorderRadius.circular(12)),
        child: Stack(alignment: Alignment.center, children: [
          if (_local != null) Positioned(right: 6, top: 6, child: Material(color: Colors.black54, shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: _save, child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.download_rounded, color: Colors.white, size: 20))))),
          Center(child: _local == null ? _downloadBadge() : const Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 54)),
          Positioned(left: 10, bottom: 8, right: 10, child: Text(_failed ? 'Download failed – tap to retry' : '$_name · ${fileSizeText(_size)}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 11.5))),
        ]),
      ),
    );
  }
}
