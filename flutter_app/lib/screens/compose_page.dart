import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/api.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';
import 'contacts_screens.dart';

/// Turns the light formatting used by the compose toolbar into safe HTML.
/// **bold**  _italic_  ++underline++  [text](url)  "- " bullets  "1. " numbers  "> " quote  bare links.
String composeToHtml(String input) {
  String esc(String s) => s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
  String inline(String s) {
    var t = esc(s);
    t = t.replaceAllMapped(RegExp(r'\*\*(.+?)\*\*'), (m) => '<b>${m[1]}</b>');
    t = t.replaceAllMapped(RegExp(r'\+\+(.+?)\+\+'), (m) => '<u>${m[1]}</u>');
    t = t.replaceAllMapped(RegExp(r'(?<![\w])_(.+?)_(?![\w])'), (m) => '<i>${m[1]}</i>');
    t = t.replaceAllMapped(RegExp(r'\[([^\]]+)\]\((https?://[^\s)]+)\)'), (m) => '<a href="${m[2]}">${m[1]}</a>');
    t = t.replaceAllMapped(RegExp(r'(?<!["=>])(https?://[^\s<]+)'), (m) => '<a href="${m[1]}">${m[1]}</a>');
    return t;
  }

  final out = StringBuffer();
  String? open;
  void close() {
    if (open != null) out.write('</$open>');
    open = null;
  }

  for (final raw in input.replaceAll('\r\n', '\n').split('\n')) {
    final ul = RegExp(r'^\s*[-•]\s+(.*)$').firstMatch(raw);
    final ol = RegExp(r'^\s*\d+[.)]\s+(.*)$').firstMatch(raw);
    final bq = RegExp(r'^\s*>\s?(.*)$').firstMatch(raw);
    if (ul != null) {
      if (open != 'ul') { close(); out.write('<ul>'); open = 'ul'; }
      out.write('<li>${inline(ul[1]!)}</li>');
    } else if (ol != null) {
      if (open != 'ol') { close(); out.write('<ol>'); open = 'ol'; }
      out.write('<li>${inline(ol[1]!)}</li>');
    } else if (bq != null) {
      if (open != 'blockquote') { close(); out.write('<blockquote>'); open = 'blockquote'; }
      out.write('${inline(bq[1]!)}<br>');
    } else {
      close();
      out.write(raw.trim().isEmpty ? '<br>' : '<p>${inline(raw)}</p>');
    }
  }
  close();
  return out.toString();
}

/// Compose / reply / reply-all / forward. Really sends via POST inbox/send or inbox/conversations/{id}/send.
class ComposePage extends StatefulWidget {
  final String channel;
  final Item? contact;
  final bool standalone;
  final int? conversationId; // set for reply / reply_all / forward
  final String mode; // new | reply | reply_all | forward
  final String? subject;
  final String? replyTo; // shown as the "To" for replies
  final String? prefill; // initial body (e.g. an AI suggested reply)
  const ComposePage({super.key, this.channel = 'email', this.contact, this.standalone = true, this.conversationId, this.mode = 'new', this.subject, this.replyTo, this.prefill});
  @override
  State<ComposePage> createState() => _ComposePageState();
}

class _ComposePageState extends State<ComposePage> {
  late String _channel = widget.channel;
  Item? _contact;
  final _to = TextEditingController(), _cc = TextEditingController(), _bcc = TextEditingController();
  late final _subject = TextEditingController(text: widget.subject ?? '');
  late final _body = TextEditingController(text: widget.prefill ?? '');
  final _files = <PlatformFile>[];
  List<Item> _accounts = [];
  int? _from;
  bool _busy = false, _showCc = false;
  DateTime? _when;

  bool get _isReply => widget.conversationId != null;
  bool get _isForward => widget.mode == 'forward';

  @override
  void initState() {
    super.initState();
    _contact = widget.contact;
    if (!_isReply) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        try {
          final a = Api.list(await Api.of(context).get('inbox/accounts'));
          if (mounted) setState(() { _accounts = a; if (a.isNotEmpty) _from = a.first['id'] as int?; });
        } catch (_) {}
      });
    }
  }

  @override
  void dispose() {
    for (final c in [_to, _cc, _bcc, _subject, _body]) {
      c.dispose();
    }
    super.dispose();
  }

  // ── formatting toolbar ──
  void _wrap(String l, String r) {
    final t = _body.text, s = _body.selection;
    final a = s.isValid ? s.start : t.length, b = s.isValid ? s.end : t.length;
    final sel = t.substring(a, b);
    final ins = '$l${sel.isEmpty ? 'text' : sel}$r';
    _body.value = TextEditingValue(text: t.replaceRange(a, b, ins), selection: TextSelection(baseOffset: a + l.length, extentOffset: a + ins.length - r.length));
  }

  void _prefix(String p) {
    final t = _body.text, s = _body.selection;
    final a = s.isValid ? s.start : t.length, b = s.isValid ? s.end : t.length;
    final ls = a == 0 ? 0 : t.lastIndexOf('\n', a - 1) + 1;
    var n = 0;
    final out = t.substring(ls, b).split('\n').map((l) { n++; return p == '1.' ? '$n. $l' : '$p $l'; }).join('\n');
    _body.value = TextEditingValue(text: t.replaceRange(ls, b, out), selection: TextSelection.collapsed(offset: ls + out.length));
  }

  Future<void> _link() async {
    final url = await promptDialog(context, 'Insert link', hint: 'https://example.com', type: TextInputType.url);
    if (url == null || url.trim().isEmpty) return;
    final t = _body.text, s = _body.selection;
    final a = s.isValid ? s.start : t.length, b = s.isValid ? s.end : t.length;
    final label = t.substring(a, b).isEmpty ? url.trim() : t.substring(a, b);
    final ins = '[$label](${url.trim()})';
    _body.value = TextEditingValue(text: t.replaceRange(a, b, ins), selection: TextSelection.collapsed(offset: a + ins.length));
  }

  // ── attachments ──
  Future<void> _attach() async {
    final r = await FilePicker.platform.pickFiles(allowMultiple: true);
    if (r == null) return;
    for (final f in r.files) {
      if (f.path == null) continue;
      if (f.size > 25 * 1024 * 1024) {
        if (mounted) toast(context, '${f.name} is larger than 25 MB', error: true);
        continue;
      }
      if (_files.length >= 10) {
        if (mounted) toast(context, 'Maximum 10 attachments', error: true);
        break;
      }
      _files.add(f);
    }
    if (mounted) setState(() {});
  }

  String _size(int b) => b >= 1048576 ? '${(b / 1048576).toStringAsFixed(1)} MB' : b >= 1024 ? '${(b / 1024).toStringAsFixed(0)} KB' : '$b B';

  // ── schedule ──
  Future<void> _schedule() async {
    final now = DateTime.now();
    final d = await showDatePicker(context: context, initialDate: _when ?? now.add(const Duration(hours: 1)), firstDate: now, lastDate: now.add(const Duration(days: 365)));
    if (d == null || !mounted) return;
    final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(_when ?? now.add(const Duration(hours: 1))));
    if (t == null) return;
    final w = DateTime(d.year, d.month, d.day, t.hour, t.minute);
    if (!mounted) return;
    if (!w.isAfter(DateTime.now())) return toast(context, 'Pick a time in the future', error: true);
    setState(() => _when = w);
  }

  // ── send ──
  Future<void> _send() async {
    final text = _body.text.trim();
    if (text.isEmpty) return toast(context, 'Write a message first', error: true);
    final isEmail = _channel == 'email';
    if (_channel == 'live_chat') return toast(context, 'Live chat is started by the visitor. Reply from the inbox instead.', error: true);

    final fields = <String, String>{'body': text};
    String path;
    if (_isReply) {
      path = 'inbox/conversations/${widget.conversationId}/send';
      fields['mode'] = widget.mode;
      if (_isForward) {
        if (_to.text.trim().isEmpty) return toast(context, 'Enter who to forward to', error: true);
        fields['to'] = _to.text.trim();
      }
    } else {
      path = 'inbox/send';
      var to = _to.text.trim();
      if (_contact != null) to = isEmail ? pick(_contact!, ['email']) : pick(_contact!, ['phone']);
      if (to.isEmpty) return toast(context, isEmail ? 'Choose a recipient with an email address' : 'Choose a contact with a phone number or type the ID', error: true);
      if (isEmail && _subject.text.trim().isEmpty) return toast(context, 'Add a subject', error: true);
      fields['channel'] = _channel;
      fields['to'] = to;
      if (isEmail && _from != null) fields['from_account_id'] = '$_from';
    }
    if (isEmail || _isReply) {
      if (isEmail && !_isReply) fields['subject'] = _subject.text.trim();
      fields['body_html'] = composeToHtml(text);
      if (_cc.text.trim().isNotEmpty) fields['cc'] = _cc.text.trim();
      if (_bcc.text.trim().isNotEmpty) fields['bcc'] = _bcc.text.trim();
    }
    if (_when != null) {
      fields['scheduled_at'] = DateFormat('yyyy-MM-dd HH:mm:ss').format(_when!.toUtc());
      fields['timezone'] = 'UTC';
    }

    setState(() => _busy = true);
    try {
      final r = await Api.of(context).multipartForm(path, fields, files: [for (final f in _files) f.path!]);
      if (!mounted) return;
      toast(context, r is Map && r['message'] != null ? '${r['message']}' : 'Sent');
      _body.clear();
      _files.clear();
      _when = null;
      if (widget.standalone) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _tool(IconData i, String tip, VoidCallback f) => IconButton(tooltip: tip, visualDensity: VisualDensity.compact, icon: Icon(i, size: 20), onPressed: f);

  @override
  Widget build(BuildContext context) {
    final isEmail = _channel == 'email';
    final rich = isEmail || _isReply;
    final title = _isReply ? {'reply': 'Reply', 'reply_all': 'Reply all', 'forward': 'Forward'}[widget.mode] ?? 'Reply' : (isEmail ? 'New email' : 'New ${channelById(_channel).label} message');

    final form = ListView(padding: const EdgeInsets.fromLTRB(14, 10, 14, 24), children: [
      if (!_isReply)
        SizedBox(
          height: 42,
          child: ListView(scrollDirection: Axis.horizontal, children: [
            for (final ch in kChannels)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  avatar: Icon(ch.icon, size: 16, color: _channel == ch.id ? Colors.white : ch.color),
                  label: Text(ch.label, style: TextStyle(color: _channel == ch.id ? Colors.white : null)),
                  selected: _channel == ch.id,
                  selectedColor: AppColors.primary,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _channel = ch.id),
                ),
              ),
          ]),
        ),
      if (isEmail && !_isReply && _accounts.length > 1) ...[
        const SizedBox(height: 10),
        DropdownButtonFormField<int>(
          initialValue: _from,
          decoration: const InputDecoration(labelText: 'From'),
          items: [for (final a in _accounts) DropdownMenuItem<int>(value: a['id'] as int?, child: Text('${a['email']}', overflow: TextOverflow.ellipsis))],
          onChanged: (v) => setState(() => _from = v),
        ),
      ],
      const SizedBox(height: 10),
      if (_isReply && !_isForward)
        InputDecorator(decoration: const InputDecoration(labelText: 'To'), child: Text(widget.replyTo?.isNotEmpty == true ? widget.replyTo! : 'Conversation contact'))
      else if (_contact != null && !_isReply)
        AppCard(
          padding: EdgeInsets.zero,
          child: ListTile(
            leading: Avatar(contactName(_contact!)),
            title: Text(contactName(_contact!)),
            subtitle: Text(pick(_contact!, ['email', 'phone'])),
            trailing: IconButton(tooltip: 'Close', icon: const Icon(Icons.close), onPressed: () => setState(() => _contact = null)),
          ),
        )
      else
        TextField(
          controller: _to,
          keyboardType: isEmail ? TextInputType.emailAddress : TextInputType.text,
          decoration: InputDecoration(
            labelText: _isForward ? 'Forward to' : 'To',
            hintText: isEmail ? 'email@example.com' : (_channel == 'slack' ? 'Slack channel ID' : _channel == 'telegram' ? 'Chat ID or @username' : 'Phone number with country code'),
            suffixIcon: _isReply ? null : IconButton(tooltip: 'Contacts', icon: const Icon(Icons.contacts_rounded), onPressed: () async {
              final c = await pickContact(context);
              if (c != null) setState(() => _contact = c);
            }),
          ),
        ),
      if (rich) ...[
        Align(
          alignment: Alignment.centerRight,
          child: TextButton(onPressed: () => setState(() => _showCc = !_showCc), child: Text(_showCc ? 'Hide Cc / Bcc' : 'Cc / Bcc')),
        ),
        if (_showCc) ...[
          TextField(controller: _cc, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Cc', hintText: 'comma separated')),
          const SizedBox(height: 10),
          TextField(controller: _bcc, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Bcc', hintText: 'comma separated')),
          const SizedBox(height: 10),
        ],
      ],
      if (isEmail && !_isReply) TextField(controller: _subject, decoration: const InputDecoration(labelText: 'Subject')),
      const SizedBox(height: 10),
      if (rich)
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(children: [
            _tool(Icons.format_bold, 'Bold', () => _wrap('**', '**')),
            _tool(Icons.format_italic, 'Italic', () => _wrap('_', '_')),
            _tool(Icons.format_underline, 'Underline', () => _wrap('++', '++')),
            _tool(Icons.format_list_bulleted, 'Bulleted list', () => _prefix('-')),
            _tool(Icons.format_list_numbered, 'Numbered list', () => _prefix('1.')),
            _tool(Icons.format_quote, 'Quote', () => _prefix('>')),
            _tool(Icons.link, 'Link', _link),
            const SizedBox(height: 24, child: VerticalDivider()),
            _tool(Icons.attach_file_rounded, 'Attach files', _attach),
            _tool(Icons.bolt_rounded, 'Quick replies', () async {
              try {
                final rows = Api.list(await Api.of(context).get('quick-replies'));
                if (!context.mounted || rows.isEmpty) return;
                actionSheet(context, 'Quick replies', [for (final r in rows) SheetAction('${r['title']}', Icons.bolt_rounded, () => setState(() => _body.text = '${r['content']}'))]);
              } catch (_) {}
            }),
            _tool(Icons.schedule_send_rounded, 'Schedule send', _schedule),
          ]),
        ),
      TextField(controller: _body, minLines: 10, maxLines: 24, keyboardType: TextInputType.multiline, decoration: const InputDecoration(hintText: 'Write your message…', alignLabelWithHint: true)),
      if (_files.isNotEmpty) ...[
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 6, children: [
          for (final f in _files)
            InputChip(
              avatar: const Icon(Icons.insert_drive_file_outlined, size: 16),
              label: Text('${f.name} · ${_size(f.size)}', overflow: TextOverflow.ellipsis),
              onDeleted: () => setState(() => _files.remove(f)),
            ),
        ]),
      ],
      if (_when != null) ...[
        const SizedBox(height: 10),
        InputChip(avatar: const Icon(Icons.schedule_send_rounded, size: 16), label: Text('Scheduled: ${DateFormat('EEE d MMM, h:mm a').format(_when!)}'), onDeleted: () => setState(() => _when = null)),
      ],
      if (isEmail) const Padding(padding: EdgeInsets.only(top: 8), child: Text('Your email signature is added automatically when sent.', style: TextStyle(fontSize: 12, color: AppColors.muted))),
      const SizedBox(height: 16),
      FilledButton.icon(
        onPressed: _busy ? null : _send,
        icon: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Icon(_when != null ? Icons.schedule_send_rounded : Icons.send_rounded),
        label: Text(_when != null ? 'Schedule' : 'Send'),
      ),
    ]);

    return widget.standalone
        ? Scaffold(appBar: AppBar(title: Text(title), actions: [IconButton(tooltip: 'Attach', icon: const Icon(Icons.attach_file_rounded), onPressed: rich ? _attach : null), IconButton(tooltip: 'Send', icon: const Icon(Icons.send_rounded), onPressed: _busy ? null : _send)]), body: form)
        : form;
  }
}
