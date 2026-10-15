import 'profile_avatar.dart';
import 'dart:async';
import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/api.dart';
import '../core/notification_presentation.dart';
import '../core/calls.dart';
import '../core/conversation_visibility.dart';
import '../core/chat_cache.dart';
import '../core/config.dart';
import '../core/session.dart';
import '../core/refresh.dart';
import '../core/paged.dart';
import '../core/prefs.dart';
import '../core/sounds.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/voice.dart';
import '../core/widgets.dart';
import 'call_history_screen.dart';
import 'chat_media.dart';
import 'person_profile_page.dart';

/// Chat with a friend or teammate: text, replies, voice messages, files, and an audio-call button.
/// New messages arrive by polling every 3 seconds while this screen is open.
class FriendChatPage extends StatefulWidget {
  final Item friend;
  final Map<String, dynamic> features;
  const FriendChatPage({super.key, required this.friend, required this.features});
  @override
  State<FriendChatPage> createState() => _FriendChatPageState();
}

class _FriendChatPageState extends State<FriendChatPage> with ConversationVisibility<FriendChatPage> {
  final _msgs = <Item>[];
  final _text = TextEditingController();
  final _scroll = ScrollController();
  final _voice = VoiceRecorder();
  Timer? _timer, _recTimer;
  int _last = 0, _recSeconds = 0, _readThrough = 0;
  bool _acknowledging = false;
  bool _loading = false, _sending = false, _first = true, _recording = false;
  String? _path, _fileName;
  Item? _replyTo; // {id, name, preview}
  Item? _editing; // the message being edited
  Map<String, dynamic>? _presence; // {online, label} of the friend (null/empty label = hidden)
  late final String? _cacheAccount;
  bool _cacheReady = false;
  String? _loadError;
  String? _since; // server clock: ask only for edits / deletes after this

  int get _id => widget.friend['id'] as int;
  String get _name => '${widget.friend['name']}';
  bool get _files => widget.features['files'] == true;
  /// former friends: the chat stays readable, nobody can write or call (server says so in `relation`)
  bool _readOnly = false;
  bool get _calls => widget.features['calls'] == true && !_readOnly;
  int get _maxMb => (widget.features['max_mb'] as int?) ?? 10;

  @override
  void initState() {
    super.initState();
    _readOnly = widget.friend['former'] == true;
    _scroll.addListener(_acknowledgeVisible);
    final session = context.read<Session>();
    _cacheAccount = ChatCache.accountKey(AppConfig.apiBase, session.userId, session.token);
    _restore();
    AppRefresh.tick.addListener(_load);
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => _load());
  }

  @override
  void dispose() {
    if (CallManager.I.openChatId == _id) CallManager.I.openChatId = null;
    AppRefresh.tick.removeListener(_load);
    _timer?.cancel();
    _recTimer?.cancel();
    VoicePlayer.I.stop();
    _voice.cancel();
    _voice.dispose();
    _text.dispose();
    _scroll.dispose();
    super.dispose();
  }

  bool get _nearBottom => !_scroll.hasClients || _scroll.position.maxScrollExtent - _scroll.offset < 160;

  Future<void> _restore() async {
    final cached = _cacheAccount == null ? <Item>[] : await ChatCache.read(_cacheAccount, _id);
    if (!mounted) return;
    setState(() { _msgs.addAll(cached); _cacheReady = true; });
    WidgetsBinding.instance.addPostFrameCallback((_) { if (mounted) _toEnd(); });
    // Fetch an authoritative snapshot first: cached deletions/clears must disappear too.
    await _load();
  }

  Future<void> _load() async {
    if (_loading || !conversationVisible || !_cacheReady) return;
    final epoch = visibilityEpoch;
    _loading = true;
    try {
      final j = await Api.of(context).getNoCache('friends/$_id/messages', query: {'mark_read': '0', 'after': '$_last', if (_since != null) 'since': _since!});
      if (!conversationVisible || epoch != visibilityEpoch) return;
      _loadError = null;
      if (_first) setState(() => _msgs.clear());
      // messages that were edited or deleted since the last look
      if (j is Map && j['changes'] is List && mounted) {
        final ch = (j['changes'] as List).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
        if (ch.isNotEmpty) {
          setState(() {
            for (final c in ch) {
              final i = _msgs.indexWhere((m) => m['id'] == c['id']);
              if (i >= 0) _msgs[i] = c;
              if (_editing != null && _editing!['id'] == c['id'] && c['deleted'] == true) {
                _editing = null;
                _text.clear();
              }
            }
          });
        }
      }
      if (j is Map && j['now'] != null) _since = '${j['now']}';
      if (j is Map && j['relation'] is Map && mounted) {
        final ro = (j['relation'] as Map)['read_only'] == true;
        if (ro != _readOnly) {
          setState(() {
            _readOnly = ro;
            if (ro) {
              _editing = null;
              _replyTo = null;
              _path = null;
              _fileName = null;
              _text.clear();
            }
          });
          if (ro) {
            FocusManager.instance.primaryFocus?.unfocus();
            if (_recording) await _finishRecording(false);
          }
        }
      }
      if (j is Map && j['presence'] is Map && mounted) setState(() => _presence = Map<String, dynamic>.from(j['presence'] as Map));
      final fresh = Api.list(j).where((m) => (m['id'] as int) > _last).toList();
      if (fresh.isNotEmpty && mounted) {
        final stick = _nearBottom || _first;
        final wasFirst = _first;
        if (!wasFirst && fresh.any((m) => m['mine'] != true && m['kind'] != 'call')) {
          final p = context.read<AppPrefs>();
          if (p.inAppSound && !p.inQuietHours) SoundPlayer.play(p.soundId); // a friend wrote while you are in the chat
        }
        setState(() {
          _msgs.addAll(fresh);
          _last = fresh.last['id'] as int;
        });
        _first = false;
        if (stick) WidgetsBinding.instance.addPostFrameCallback((_) => _toEnd());
      }
      _first = false;
      afterVisibleFrame(_acknowledgeVisible);
      if (_cacheAccount != null) await ChatCache.write(_cacheAccount, _id, _msgs);
    } catch (_) {
      if (mounted) setState(() => _loadError = 'Could not sync. Showing saved messages.');
    } finally {
      if (mounted) setState(() {});
      _loading = false;
    }
  }

  @override
  void onConversationVisibilityChanged(bool visible) {
    if (visible) {
      CallManager.I.openChatId = _id;
      afterVisibleFrame(() { _load(); _acknowledgeVisible(); });
    } else if (CallManager.I.openChatId == _id) {
      CallManager.I.openChatId = null;
    }
  }

  Future<void> _acknowledgeVisible() async {
    if (!conversationVisible || !_nearBottom || _last <= _readThrough || _acknowledging) return;
    final through = _last;
    _acknowledging = true;
    try {
      final result = await Api.of(context).post('friends/$_id/read', {'through_message_id': through});
      if (result is Map && result['unread'] is num) CallManager.I.applyUnreadReceipt((result['unread'] as num).toInt());
      _readThrough = through;
      AppRefresh.bump();
      CallManager.I.pollNow();
    } catch (_) { /* A later visible frame/poll retries; fetching never marks seen. */ }
    finally { _acknowledging = false; }
  }

  void _toEnd() {
    if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
  }

  String _preview(Item m) => m['kind'] == 'voice' ? '🎤 Voice message' : (m['file'] != null ? '📎 ${(m['file'] as Map)['name']}' : '${m['body'] ?? ''}');

  void _reply(Item m) {
    if (_readOnly) return;
    setState(() {
      _editing = null;
      _replyTo = {'id': m['id'], 'name': m['mine'] == true ? 'You' : _name, 'preview': _preview(m)};
    });
  }

  static const _quick = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

  /// Long-press menu like WhatsApp: a row of quick reactions, then Reply, Forward, Copy, Edit, Info, Delete.
  void _messageMenu(Item m) {
    if (m['kind'] == 'call' || m['deleted'] == true) return;
    final mine = m['mine'] == true;
    final canEdit = !_readOnly && mine && (m['kind'] == 'text' || (m['kind'] == 'file' && '${m['body'] ?? ''}'.isNotEmpty));
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 6),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
              for (final e in _quick) InkWell(borderRadius: BorderRadius.circular(24), onTap: () { Navigator.pop(c); _react(m, e); }, child: Padding(padding: const EdgeInsets.all(8), child: Text(e, style: const TextStyle(fontSize: 28)))),
              InkWell(borderRadius: BorderRadius.circular(24), onTap: () { Navigator.pop(c); _emojiSheet((e) => _react(m, e)); }, child: const Padding(padding: EdgeInsets.all(8), child: Icon(Icons.add_circle_outline_rounded, size: 28))),
            ]),
          ),
          const Divider(height: 1),
          if (!_readOnly) ListTile(leading: const Icon(Icons.reply_rounded), title: const Text('Reply'), onTap: () { Navigator.pop(c); _reply(m); }),
          ListTile(leading: const Icon(Icons.forward_rounded), title: const Text('Forward'), onTap: () { Navigator.pop(c); _forward(m); }),
          if (m['file'] is Map) ListTile(leading: const Icon(Icons.download_rounded), title: const Text('Download'), onTap: () { Navigator.pop(c); _downloadAttachment(m); }),
          if ('${m['body'] ?? ''}'.isNotEmpty)
            ListTile(leading: const Icon(Icons.copy_rounded), title: const Text('Copy text'), onTap: () { Navigator.pop(c); Clipboard.setData(ClipboardData(text: '${m['body']}')); toast(context, 'Copied'); }),
          if (canEdit) ListTile(leading: const Icon(Icons.edit_rounded), title: const Text('Edit'), onTap: () { Navigator.pop(c); _startEdit(m); }),
          if (mine) ListTile(leading: const Icon(Icons.info_outline_rounded), title: const Text('Info'), onTap: () { Navigator.pop(c); _info(m); }),
          if (mine) ListTile(leading: const Icon(Icons.delete_outline_rounded, color: AppColors.danger), title: const Text('Delete', style: TextStyle(color: AppColors.danger)), onTap: () { Navigator.pop(c); _deleteMessage(m); }),
        ]),
      ),
    );
  }

  /// Download = save a copy where you choose (the phone's Save dialog). Fetches the file first if it is not on the phone yet.
  Future<void> _downloadAttachment(Item m) async {
    final f = m['file'] is Map ? Map<String, dynamic>.from(m['file'] as Map) : null;
    if (f == null) return;
    final id = (m['id'] as num).toInt(), name = '${f['name'] ?? 'file'}';
    var file = await ChatMediaStore.cached(context, id, name);
    if (file == null) {
      if (mounted) toast(context, 'Downloading…');
      try {
        file = await ChatMediaStore.download(context, id, name, (f['size'] as num?)?.toInt() ?? 0, (_) {});
      } catch (_) {
        if (mounted) toast(context, 'Download failed. Try again.', error: true);
        return;
      }
    }
    if (mounted) await ChatMediaStore.saveToDevice(context, file, name);
  }

  Future<void> _react(Item m, String emoji) async {
    try {
      final j = await Api.of(context).post('friends/$_id/messages/${m['id']}/react', {'emoji': emoji});
      final fresh = Api.obj(j);
      if (mounted && fresh.isNotEmpty) {
        setState(() {
        final i = _msgs.indexWhere((x) => x['id'] == m['id']);
        if (i >= 0) _msgs[i] = fresh;
      });
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  void _info(Item m) {
    final r = (m['reactions'] as List?) ?? const [];
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Message info', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
            const SizedBox(height: 12),
            _infoRow(Icons.done_all_rounded, 'Read', '${m['read_at'] ?? '—'}', const Color(0xFF53BDEB)),
            _infoRow(Icons.done_all_rounded, 'Delivered', '${m['delivered_at'] ?? '—'}', AppColors.muted),
            _infoRow(Icons.done_rounded, 'Sent', '${m['date']}, ${m['time']}', AppColors.muted),
            for (final x in r) Padding(padding: const EdgeInsets.only(top: 8), child: Text('${(x as Map)['emoji']}  ${((x['users'] as List?) ?? const []).join(', ')}')),
          ]),
        ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value, Color color) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [Icon(icon, size: 20, color: color), const SizedBox(width: 12), Expanded(child: Text(label)), Text(value, style: const TextStyle(fontWeight: FontWeight.w600))]),
      );

  /// Forward to up to 5 friends / teammates (text, photo, video, voice or file).
  Future<void> _forward(Item m) async {
    List<Item> people = [];
    try {
      final saved = Api.peek('friends/forward-targets');
    if (saved != null) {
      people = Api.list(saved);
      Api.of(context).get('friends/forward-targets').catchError((_) => null);
    } else { people = Api.list(await Api.of(context).get('friends/forward-targets')); }
    } catch (_) {}
    if (!mounted) return;
    final chosen = <int>{};
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (c) => StatefulBuilder(
        builder: (c, setS) => SizedBox(
          height: MediaQuery.sizeOf(c).height * 0.7,
          child: Column(children: [
            const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8), child: Align(alignment: Alignment.centerLeft, child: Text('Forward to…', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)))),
            Expanded(
              child: people.isEmpty
                  ? const Center(child: Text('No friends or teammates yet.', style: TextStyle(color: AppColors.muted)))
                  : ListView(children: [
                      for (final p in people)
                        CheckboxListTile(
                          secondary: ProfileAvatar(p, radius: 18),
                          title: Text('${p['name']}'),
                          value: chosen.contains((p['id'] as num).toInt()),
                          onChanged: (v) => setS(() {
                            final id = (p['id'] as num).toInt();
                            if (v == true) {
                              if (chosen.length >= 5) { toast(c, 'You can forward to 5 people at once.', error: true); return; }
                              chosen.add(id);
                            } else {
                              chosen.remove(id);
                            }
                          }),
                        ),
                    ]),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  icon: const Icon(Icons.send_rounded),
                  label: Text(chosen.isEmpty ? 'Choose people' : 'Send to ${chosen.length}'),
                  onPressed: chosen.isEmpty
                      ? null
                      : () async {
                          Navigator.pop(c);
                          try {
                            final j = await Api.of(context).post('friends/$_id/messages/${m['id']}/forward', {'to': chosen.toList()});
                            if (mounted) toast(context, j is Map ? '${j['message'] ?? 'Forwarded.'}' : 'Forwarded.');
                          } on ApiException catch (e) {
                            if (mounted) toast(context, e.message, error: true);
                          }
                        },
                ),
              ),
            ),
          ]),
        ),
      ),
    );
  }

  // ── emoji ──
  static const _emoji = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','😉','😍','🥰','😘','😗','😋','😜','🤪','😎','🤩','🥳','😏','😒','😞','😔','😢','😭','😤','😡','🤬','😱','😨','😰','😮','😲','🤔','🤫','😴','🤯','🥺','😷','🤒','🤑','😈','💀','💩','👍','👎','👏','🙌','🙏','💪','👋','🤝','✌️','🤞','👌','✋','👉','❤️','🧡','💛','💚','💙','💜','🖤','💔','💕','💖','🔥','✨','⭐','🎉','🎊','🎁','🎂','🌹','🌟','☀️','🌙','☁️','🌈','🚀','✅','❌','❗','❓','💯','👀','👊','🍕','🍔','☕','🍺','🍎','🎵','⚽','📱','💻','📧','📞','⏰','🏠','🚗','✈️'];

  void _emojiSheet(void Function(String) pick, {bool keepOpen = false}) {
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: SizedBox(
          height: 300,
          child: GridView.count(
            crossAxisCount: 8,
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 12),
            children: [
              for (final e in _emoji) InkWell(borderRadius: BorderRadius.circular(8), onTap: () { pick(e); if (!keepOpen) Navigator.pop(c); }, child: Center(child: Text(e, style: const TextStyle(fontSize: 26)))),
            ],
          ),
        ),
      ),
    );
  }

  void _insertEmoji(String e) {
    final t = _text.text;
    final sel = _text.selection;
    final s = sel.isValid ? sel.start : t.length, en = sel.isValid ? sel.end : t.length;
    _text.value = TextEditingValue(text: t.replaceRange(s, en, e), selection: TextSelection.collapsed(offset: s + e.length));
  }

  void _startEdit(Item m) {
    if (_readOnly) return;
    setState(() {
      _replyTo = null;
      _editing = m;
      _text.text = '${m['body'] ?? ''}';
      _text.selection = TextSelection.collapsed(offset: _text.text.length);
    });
  }

  void _cancelEdit() {
    setState(() => _editing = null);
    _text.clear();
  }

  Future<void> _saveEdit() async {
    if (_readOnly) return;
    final m = _editing;
    final text = _text.text.trim();
    if (m == null || _sending) return;
    if (text.isEmpty) {
      toast(context, 'A message cannot be empty. Use Delete instead.', error: true);
      return;
    }
    setState(() => _sending = true);
    try {
      final j = await Api.of(context).put('friends/$_id/messages/${m['id']}', {'body': text});
      final fresh = Api.obj(j);
      if (mounted) {
        setState(() {
          final i = _msgs.indexWhere((x) => x['id'] == m['id']);
          if (i >= 0 && fresh.isNotEmpty) _msgs[i] = fresh;
          _editing = null;
        });
        _text.clear();
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _deleteMessage(Item m) async {
    final ok = await confirmDialog(context, 'Delete this message?', 'It is deleted for both of you. A note "This message was deleted" stays in the chat.', action: 'Delete', danger: true);
    if (!ok || !mounted) return;
    try {
      final j = await Api.of(context).delete('friends/$_id/messages/${m['id']}');
      final fresh = Api.obj(j);
      if (mounted) {
        setState(() {
          final i = _msgs.indexWhere((x) => x['id'] == m['id']);
          if (i >= 0) _msgs[i] = fresh.isNotEmpty ? fresh : {...m, 'deleted': true, 'body': null, 'file': null, 'reply': null};
          if (_editing != null && _editing!['id'] == m['id']) {
            _editing = null;
            _text.clear();
          }
        });
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  /// WhatsApp-style attach menu: camera, photo / video from the gallery, or any file.
  void _attachMenu() {
    if (_readOnly) return;
    actionSheet(context, '', [
      SheetAction('Take a photo', Icons.photo_camera_rounded, () => _pickMedia(ImageSource.camera, video: false)),
      SheetAction('Record a video', Icons.videocam_rounded, () => _pickMedia(ImageSource.camera, video: true)),
      SheetAction('Photo from gallery', Icons.photo_library_rounded, () => _pickMedia(ImageSource.gallery, video: false)),
      SheetAction('Video from gallery', Icons.video_library_rounded, () => _pickMedia(ImageSource.gallery, video: true)),
      SheetAction('File', Icons.attach_file_rounded, _pick),
    ]);
  }

  Future<void> _pickMedia(ImageSource src, {required bool video}) async {
    try {
      final picker = ImagePicker();
      final x = video ? await picker.pickVideo(source: src, maxDuration: const Duration(minutes: 5)) : await picker.pickImage(source: src, maxWidth: 1920, imageQuality: 85);
      if (!mounted || _readOnly || x == null) return;
      if (File(x.path).lengthSync() > _maxMb * 1024 * 1024) {
        if (mounted) toast(context, 'The file is larger than $_maxMb MB', error: true);
        return;
      }
      setState(() {
        _path = x.path;
        _fileName = x.name;
      });
    } catch (_) {
      if (mounted) toast(context, 'Could not open the camera or gallery. Check the permission in your phone settings.', error: true);
    }
  }

  Future<void> _pick() async {
    final r = await FilePicker.platform.pickFiles();
    final f = r?.files.single;
    if (!mounted || _readOnly || f == null || f.path == null) return;
    if (File(f.path!).lengthSync() > _maxMb * 1024 * 1024) {
      if (mounted) toast(context, 'The file is larger than $_maxMb MB', error: true);
      return;
    }
    setState(() {
      _path = f.path;
      _fileName = f.name;
    });
  }

  Future<void> _afterSend() async {
    setState(() => _replyTo = null);
    await _load();
    WidgetsBinding.instance.addPostFrameCallback((_) => _toEnd());
  }

  Future<void> _send() async {
    if (_readOnly) return;
    if (_editing != null) return _saveEdit();
    final text = _text.text.trim();
    if ((text.isEmpty && _path == null) || _sending) return;
    setState(() => _sending = true);
    try {
      final api = Api.of(context);
      final reply = _replyTo != null ? {'reply_to': '${_replyTo!['id']}'} : <String, String>{};
      if (_path != null) {
        final sent = Api.obj(await api.multipartForm('friends/$_id/messages', {if (text.isNotEmpty) 'body': text, ...reply}, files: [_path!], fileField: 'file'));
        if (sent['id'] is int && sent['file'] is Map) await ChatMediaStore.keepSent(context, sent['id'] as int, '${(sent['file'] as Map)['name']}', _path!);
      } else {
        await api.post('friends/$_id/messages', {'body': text, if (_replyTo != null) 'reply_to': _replyTo!['id']});
      }
      _text.clear();
      setState(() {
        _path = null;
        _fileName = null;
      });
      await _afterSend();
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  // ── voice messages ──
  Future<void> _startRecording() async {
    if (_readOnly || _recording) return;
    try {
      if (!await _voice.start()) {
        if (mounted) toast(context, 'Allow the microphone in your phone settings to record voice messages', error: true);
        return;
      }
    } catch (_) {
      if (mounted) toast(context, 'Could not start recording', error: true);
      return;
    }
    if (!mounted || _readOnly) { await _voice.cancel(); return; }
    setState(() {
      _recording = true;
      _recSeconds = 0;
    });
    _recTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _recSeconds = _voice.seconds);
      if (_recSeconds >= 900) _finishRecording(true); // 15 minutes at most
    });
  }

  Future<void> _finishRecording(bool send) async {
    _recTimer?.cancel();
    if (!send || _readOnly) {
      await _voice.cancel();
      if (mounted) setState(() => _recording = false);
      return;
    }
    final r = await _voice.stop();
    if (mounted) setState(() => _recording = false);
    if (!mounted || _readOnly || r == null) return;
    setState(() => _sending = true);
    try {
      await Api.of(context).multipartForm(
        'friends/$_id/messages',
        {'voice': '1', 'duration': '${r.seconds}', if (_replyTo != null) 'reply_to': '${_replyTo!['id']}'},
        files: [r.path],
        fileField: 'file',
      );
      try {
        File(r.path).deleteSync();
      } catch (_) {}
      await _afterSend();
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  // ── clear chat: only for you, the friend keeps their copy; the friendship is never affected ──
  Future<void> _clear() async {
    final ok = await confirmDialog(context, 'Clear this chat?', 'All messages, voice messages and files will be removed from this chat. You stay friends.', action: 'Clear chat', danger: true);
    if (!ok || !mounted) return;
    if (await run(context, (a) => a.delete('friends/$_id/messages'), ok: '*') && mounted) {
      await VoicePlayer.I.stop();
      setState(() {
        _msgs.clear();
        _last = 0;
        _replyTo = null;
        _editing = null;
      });
    }
  }

  void _openProfile() => pushPage(context, PersonProfilePage(userId: _id));

  // ── drawing ──
  String _mmss(int s) => '${s ~/ 60}:${(s % 60).toString().padLeft(2, '0')}';

  Widget _quote(Map r, bool mine, Color fg) => Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.fromLTRB(8, 4, 8, 4),
        decoration: BoxDecoration(
          color: AppColors.softOf(context),
          borderRadius: BorderRadius.circular(8),
          border: Border(left: BorderSide(color: AppColors.mutedOf(context), width: 3)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text('${r['name']}', style: TextStyle(color: AppColors.inkOf(context), fontWeight: FontWeight.w700, fontSize: 12)),
          Text('${r['preview']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg.withValues(alpha: 0.85), fontSize: 12.5)),
        ]),
      );

  Widget _voiceBubble(Item m, Color fg) {
    final id = m['id'] as int;
    final dur = (m['duration'] as int?) ?? 0;
    final fileName = '${(m['file'] as Map?)?['name'] ?? 'voice.m4a'}';
    return SizedBox(
      width: 250,
      child: ValueListenableBuilder<int?>(
        valueListenable: VoicePlayer.I.playing,
        builder: (c, playing, _) {
          final on = playing == id;
          return Row(children: [
            IconButton(tooltip: 'Download', onPressed: () => _downloadAttachment(m), icon: Icon(Icons.download_rounded, color: fg)),
            InkWell(
              onTap: () => VoicePlayer.I.toggle(context, id, fileName),
              child: Icon(on ? Icons.pause_circle_filled_rounded : Icons.play_circle_fill_rounded, size: 38, color: fg),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: on
                  ? StreamBuilder<Duration>(
                      stream: VoicePlayer.I.position,
                      builder: (c, s) {
                        final pos = s.data ?? Duration.zero;
                        final total = (dur * 1000) < 1 ? 1 : dur * 1000;
                        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          LinearProgressIndicator(value: (pos.inMilliseconds / total).clamp(0.0, 1.0), minHeight: 3, color: fg, backgroundColor: fg.withValues(alpha: 0.25)),
                          const SizedBox(height: 4),
                          Text('${_mmss(pos.inSeconds)} / ${_mmss(dur)}', style: TextStyle(color: fg.withValues(alpha: 0.8), fontSize: 11.5)),
                        ]);
                      },
                    )
                  : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      LinearProgressIndicator(value: 0, minHeight: 3, color: fg, backgroundColor: fg.withValues(alpha: 0.25)),
                      const SizedBox(height: 4),
                      Text('🎤 ${_mmss(dur)}', style: TextStyle(color: fg.withValues(alpha: 0.8), fontSize: 11.5)),
                    ]),
            ),
          ]);
        },
      ),
    );
  }

  /// "Became friends" / "Unfriended" lines written by the server
  String _systemText(Item m) {
    final b = '${m['body']}';
    if (b == 'Unfriended') return m['mine'] == true ? 'You unfriended $_name' : '$_name unfriended you';
    if (b == 'Became friends') return 'You became friends';
    return b;
  }

  Widget _bubble(Item m, Item? prev) {
    final mine = m['mine'] == true;
    final scheme = Theme.of(context).colorScheme;
    final newDay = prev == null || prev['date'] != m['date'];
    Widget body;
    if (m['kind'] == 'system') {
      body = Center(
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 6),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
          decoration: BoxDecoration(color: scheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(20)),
          child: Text('${_systemText(m)} · ${m['date']}, ${m['time']}', textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
        ),
      );
    } else if (m['kind'] == 'call') {
      final event = NotificationPresentation.from({'type': 'call', 'body': m['body']});
      body = Center(
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 4),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
          decoration: BoxDecoration(color: scheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(20)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(event.icon, size: 16, color: event.color), const SizedBox(width: 6), Flexible(child: Text('${m['body']} · ${m['time']}', style: TextStyle(fontSize: 12, color: event.color)))]),
        ),
      );
    } else {
      final fg = scheme.onSurface;
      if (m['deleted'] == true) {
        body = Align(
          alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.symmetric(vertical: 3),
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
            decoration: BoxDecoration(border: Border.all(color: AppColors.muted.withValues(alpha: 0.6)), borderRadius: BorderRadius.circular(16)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.end, mainAxisSize: MainAxisSize.min, children: [
              const Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.block_rounded, size: 15, color: AppColors.muted),
                SizedBox(width: 6),
                Text('This message was deleted', style: TextStyle(fontStyle: FontStyle.italic, color: AppColors.muted, fontSize: 14)),
              ]),
              Text('${m['time']}', style: const TextStyle(color: AppColors.muted, fontSize: 11)),
            ]),
          ),
        );
        return newDay ? Column(children: [Padding(padding: const EdgeInsets.only(top: 8, bottom: 4), child: Text('${m['date']}', style: const TextStyle(fontSize: 11.5, color: AppColors.muted))), body]) : body;
      }
      final f = m['file'] is Map ? Map<String, dynamic>.from(m['file'] as Map) : null;
      final r = m['reply'] is Map ? Map<String, dynamic>.from(m['reply'] as Map) : null;
      body = GestureDetector(
        onLongPress: () => _messageMenu(m),
        onHorizontalDragEnd: (d) {
          if ((d.primaryVelocity ?? 0).abs() > 500) _reply(m); // swipe a message to reply
        },
        child: Align(
          alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
            margin: const EdgeInsets.symmetric(vertical: 3),
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
            decoration: BoxDecoration(color: mine ? AppColors.sentOf(context) : AppColors.receivedOf(context), borderRadius: BorderRadius.circular(16)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              if (m['forwarded'] == true) Padding(padding: const EdgeInsets.only(bottom: 3), child: Text('↪ Forwarded', style: TextStyle(fontStyle: FontStyle.italic, fontSize: 11.5, color: fg.withValues(alpha: 0.7)))),
              if (r != null) _quote(r, mine, fg),
              if (m['kind'] == 'voice')
                _voiceBubble(m, fg)
              else if (f != null)
                ChatFileTile(messageId: m['id'] as int, file: f, fg: fg),
              if ('${m['body'] ?? ''}'.isNotEmpty) Padding(padding: EdgeInsets.only(top: (f != null || m['kind'] == 'voice') ? 6 : 0), child: Text('${m['body']}', style: TextStyle(color: fg, fontSize: 15, height: 1.3))),
              const SizedBox(height: 2),
              if (m['reactions'] is List && (m['reactions'] as List).isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Wrap(spacing: 4, runSpacing: 4, children: [
                    for (final x in (m['reactions'] as List).whereType<Map>())
                      InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () => _react(m, '${x['emoji']}'),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(color: x['mine'] == true ? AppColors.softOf(context) : Colors.black.withValues(alpha: 0.12), border: Border.all(color: x['mine'] == true ? AppColors.accentOf(context) : Colors.transparent), borderRadius: BorderRadius.circular(12)),
                          child: Text('${x['emoji']}${(x['count'] as num? ?? 1) > 1 ? ' ${x['count']}' : ''}', style: TextStyle(fontSize: 13, color: fg)),
                        ),
                      ),
                  ]),
                ),
              Align(
                alignment: Alignment.centerRight,
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  Text('${m['edited'] == true ? 'edited  ' : ''}${m['time']}', style: TextStyle(color: fg.withValues(alpha: 0.7), fontSize: 11)),
                  if (hasMessageReceipt(m)) ...[
                    const SizedBox(width: 3),
                    Icon(m['status'] == 'sent' || m['status'] == null ? Icons.done_rounded : Icons.done_all_rounded, size: 15, color: m['status'] == 'read' ? AppColors.accentOf(context) : fg.withValues(alpha: 0.7)),
                  ],
                ]),
              ),
            ]),
          ),
        ),
      );
    }
    if (!newDay) return body;
    return Column(children: [Padding(padding: const EdgeInsets.only(top: 8, bottom: 4), child: Text('${m['date']}', style: const TextStyle(fontSize: 11.5, color: AppColors.muted))), body]);
  }

  Widget _editBar() => Container(
        width: double.infinity,
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        padding: const EdgeInsets.fromLTRB(14, 6, 6, 6),
        child: Row(children: [
          const Icon(Icons.edit_rounded, size: 18, color: AppColors.primary),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Editing message', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
            Text('${_editing!['body'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, color: AppColors.muted)),
          ])),
          IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.close_rounded, size: 20), onPressed: _cancelEdit),
        ]),
      );

  Widget _replyBar() => Container(
        width: double.infinity,
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        padding: const EdgeInsets.fromLTRB(14, 6, 6, 6),
        child: Row(children: [
          Container(width: 3, height: 34, color: AppColors.primary),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Replying to ${_replyTo!['name']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
            Text('${_replyTo!['preview']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, color: AppColors.muted)),
          ])),
          IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.close_rounded, size: 20), onPressed: () => setState(() => _replyTo = null)),
        ]),
      );

  Widget _composer() {
    if (_readOnly) {
      return Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        decoration: BoxDecoration(border: Border(top: BorderSide(color: Theme.of(context).dividerColor))),
        child: Row(children: [
          const Icon(Icons.lock_outline_rounded, size: 18, color: AppColors.muted),
          const SizedBox(width: 10),
          const Expanded(child: Text('You are no longer friends. You can read this conversation, but can’t send messages.', style: TextStyle(fontSize: 13, color: AppColors.muted, height: 1.35))),
        ]),
      );
    }
    if (_recording) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(8, 8, 8, 10),
        child: Row(children: [
          IconButton(tooltip: 'Cancel', icon: const Icon(Icons.delete_outline_rounded, color: AppColors.danger), onPressed: () => _finishRecording(false)),
          const Icon(Icons.fiber_manual_record_rounded, color: AppColors.danger, size: 14),
          const SizedBox(width: 8),
          Text(_mmss(_recSeconds), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(width: 10),
          const Expanded(child: Text('Recording… tap send when you are done', style: TextStyle(color: AppColors.muted, fontSize: 13))),
          IconButton.filled(tooltip: 'Send voice message', icon: const Icon(Icons.send_rounded), onPressed: () => _finishRecording(true)),
        ]),
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 6, 8, 8),
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        if (_files && _editing == null) IconButton(tooltip: 'Attach a file', icon: const Icon(Icons.attach_file_rounded), onPressed: _sending ? null : _attachMenu),
        if (_editing == null) IconButton(tooltip: 'Emoji', icon: const Icon(Icons.emoji_emotions_outlined), onPressed: () => _emojiSheet(_insertEmoji, keepOpen: true)),
        Expanded(child: TextField(controller: _text, minLines: 1, maxLines: 4, textCapitalization: TextCapitalization.sentences, decoration: const InputDecoration(hintText: 'Message', contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 10)))),
        const SizedBox(width: 6),
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: _text,
          builder: (c, v, _) {
            if (_sending) return const Padding(padding: EdgeInsets.all(12), child: SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.4)));
            final empty = v.text.trim().isEmpty && _path == null;
            if (_editing != null) return IconButton.filled(tooltip: 'Save', icon: const Icon(Icons.check_rounded), onPressed: _saveEdit);
            if (empty && _files) return IconButton.filled(tooltip: 'Record a voice message', icon: const Icon(Icons.mic_rounded), onPressed: _startRecording);
            return IconButton.filled(tooltip: 'Send', icon: const Icon(Icons.send_rounded), onPressed: _send);
          },
        ),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          titleSpacing: 0,
          title: InkWell(
            onTap: _openProfile,
            child: Row(children: [
              Avatar(_name, url: widget.friend['avatar_url'] as String?, radius: 17),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                  Text(_name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                  if ('${_presence?['label'] ?? ''}'.isNotEmpty)
                    Text('${_presence!['label']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: _presence!['online'] == true ? AppColors.success : AppColors.muted, fontWeight: _presence!['online'] == true ? FontWeight.w600 : FontWeight.w400)),
                ]),
              ),
            ]),
          ),
          actions: [
            if (_calls) IconButton(tooltip: 'Video call', icon: const Icon(Icons.videocam_rounded), onPressed: () => CallManager.I.startVideoCall(context, _id, _name)),
            if (_calls) IconButton(tooltip: 'Audio call', icon: const Icon(Icons.call_rounded), onPressed: () => CallManager.I.startCall(context, _id)),
            PopupMenuButton<String>(
              onSelected: (v) {
                if (v == 'profile') _openProfile();
                if (v == 'clear_me') _clear();
                if (v == 'history') pushPage(context, const CallHistoryPage());
              },
              itemBuilder: (_) => [
                const PopupMenuItem(value: 'profile', child: ListTile(dense: true, leading: Icon(Icons.person_outline_rounded), title: Text('View profile'))),
                const PopupMenuItem(value: 'history', child: ListTile(dense: true, leading: Icon(Icons.history_rounded), title: Text('Call history'))),
                const PopupMenuItem(value: 'clear_me', child: ListTile(dense: true, leading: Icon(Icons.cleaning_services_outlined), title: Text('Clear chat'))),
              ],
            ),
          ],
        ),
        body: SafeArea(
          child: Column(children: [
            if (_loadError != null) MaterialBanner(content: Text(_loadError!), actions: [TextButton(onPressed: _load, child: const Text('Retry'))]),
            Expanded(
              child: _msgs.isEmpty && _first && _loadError == null
                  ? const Center(child: CircularProgressIndicator())
                  : _msgs.isEmpty
                  ? EmptyState(icon: Icons.chat_bubble_outline_rounded, text: _readOnly ? 'No messages in this conversation.' : 'No messages yet.\nSay hello 👋')
                  : ListView.builder(controller: _scroll, padding: const EdgeInsets.fromLTRB(12, 8, 12, 8), itemCount: _msgs.length, itemBuilder: (_, i) => _bubble(_msgs[i], i > 0 ? _msgs[i - 1] : null)),
            ),
            if (!_readOnly && _editing != null && !_recording) _editBar(),
            if (!_readOnly && _replyTo != null && _editing == null && !_recording) _replyBar(),
            if (!_readOnly && _fileName != null && !_recording)
              Container(
                width: double.infinity,
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                child: Row(children: [
                  const Icon(Icons.attach_file_rounded, size: 18),
                  const SizedBox(width: 6),
                  Expanded(child: Text(_fileName!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13))),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.close_rounded, size: 18), onPressed: () => setState(() { _path = null; _fileName = null; })),
                ]),
              ),
            _composer(),
          ]),
        ),
      );
}
