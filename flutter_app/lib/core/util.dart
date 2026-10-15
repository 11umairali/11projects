import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';
import 'inapp_web.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import 'api.dart';
import 'config.dart';
import 'utility_notice.dart';

String pick(Map m, List<String> keys, {String fallback = ''}) {
  for (final k in keys) {
    final v = m[k];
    if (v != null && v.toString().trim().isNotEmpty) return v.toString();
  }
  return fallback;
}

String initials(String s) {
  final parts = s.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1)).toUpperCase();
}

String stripHtml(String h) => h
    .replaceAll(RegExp(r'<(script|style)[^>]*>.*?</\1>', dotAll: true, caseSensitive: false), '')
    .replaceAll(RegExp(r'<br\s*/?>|</p>|</div>', caseSensitive: false), '\n')
    .replaceAll(RegExp(r'<[^>]+>'), '')
    .replaceAll('&nbsp;', ' ')
    .replaceAll('&amp;', '&')
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    .replaceAll('&quot;', '"')
    .replaceAll(RegExp(r'\n{3,}'), '\n\n')
    .trim();

String timeAgo(dynamic iso) {
  final d = DateTime.tryParse('${iso ?? ''}')?.toLocal();
  if (d == null) return '';
  final diff = DateTime.now().difference(d);
  if (diff.inMinutes < 1) return 'now';
  if (diff.inMinutes < 60) return '${diff.inMinutes}m';
  if (diff.inHours < 24) return '${diff.inHours}h';
  if (diff.inDays < 7) return '${diff.inDays}d';
  return DateFormat('d MMM').format(d);
}

void toast(BuildContext c, String msg, {bool error = false}) {
  UtilityNotice.show(c, msg, error: error);
}

/// Social / messaging channels supported by the website (conversations.channel).
class Channel {
  final String id, label;
  final IconData icon;
  final Color color;
  const Channel(this.id, this.label, this.icon, this.color);
}

const kChannels = <Channel>[
  Channel('email', 'Email', Icons.mail_rounded, Color(0xFF5F33E1)),
  Channel('whatsapp', 'WhatsApp', Icons.chat_rounded, Color(0xFF25D366)),
  Channel('telegram', 'Telegram', Icons.send_rounded, Color(0xFF229ED9)),
  Channel('sms', 'SMS', Icons.sms_rounded, Color(0xFFFF9800)),
  Channel('slack', 'Slack', Icons.tag_rounded, Color(0xFF611F69)),
  Channel('live_chat', 'Live Chat', Icons.support_agent_rounded, Color(0xFF0087FF)),
];

Channel channelById(String id) =>
    kChannels.firstWhere((c) => c.id == id, orElse: () => kChannels.first);

/// Gets a one-time, already-signed-in link for [path] from the API.
Future<String> _handoffUrl(BuildContext context, String path, {bool app = false}) async {
  final j = await Api.of(context).post('auth/web-link', {'path': path, if (app) 'app': true});
  final url = Api.obj(j)['url']?.toString();
  if (url == null) throw ApiException(0, 'Could not create a secure link. Please try again.');
  return url;
}

/// Connects Gmail / Outlook / Slack / Google Calendar / Salesforce without leaving the app.
/// Google, Slack and Microsoft refuse to show their sign-in page inside an embedded WebView, so this uses the
/// system's secure in-app sheet (Chrome Custom Tab / ASWebAuthenticationSession) — it slides over the app and
/// the server sends it straight back to dahimail://oauth, which closes the sheet and returns here. The website's
/// own pages are never shown. Returns true when the account was connected.
Future<bool> connectOAuth(BuildContext context, String path) async {
  try {
    final url = await _handoffUrl(context, path, app: true);
    final result = await FlutterWebAuth2.authenticate(url: url, callbackUrlScheme: AppConfig.urlScheme);
    final q = Uri.parse(result).queryParameters;
    final ok = q['status'] == 'ok';
    final msg = (q['message'] ?? '').trim();
    Api.clearCache();
    if (context.mounted) toast(context, msg.isNotEmpty ? msg : (ok ? 'Connected' : 'Connection failed'), error: !ok);
    return ok;
  } on PlatformException catch (e) {
    if (e.code != 'CANCELED' && context.mounted) toast(context, 'Could not complete the connection.', error: true);
    return false;
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
    return false;
  }
}

/// Opens a payment page (gateway checkout URL or hosted checkout) inside the app and returns how it ended.
Future<WebResult> openPaymentPage(BuildContext context, {String? url, String? path, String title = 'Secure payment'}) async {
  try {
    final target = url ?? await _handoffUrl(context, path!, app: true);
    if (!context.mounted) return const WebResult('closed');
    final r = await Navigator.of(context).push<WebResult>(MaterialPageRoute(builder: (_) => InAppWebPage(url: target, title: title)));
    return r ?? const WebResult('closed');
  } on ApiException catch (e) {
    return WebResult('error', e.message);
  }
}

/// Opens a link inside the app (Chrome Custom Tab / Safari View Controller) so the user stays in the app,
/// like Facebook or Gmail do. Falls back to the normal browser if the phone has no in-app browser.
Future<void> openUrl(String url) async {
  final uri = Uri.parse(url);
  try {
    if (await launchUrl(uri, mode: LaunchMode.inAppBrowserView)) return;
  } catch (_) {}
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}

/// Downloads a file from the API with the login token, saves it and opens it with the phone's viewer.
Future<void> downloadAndOpen(BuildContext context, String apiPath, String filename) async {
  try {
    toast(context, 'Preparing $filename…');
    final bytes = await Api.of(context).download(apiPath);
    final dir = await getTemporaryDirectory();
    final safe = filename.replaceAll(RegExp(r'[^\w.\- ]'), '_');
    final f = File('${dir.path}/$safe');
    await f.writeAsBytes(bytes, flush: true);
    final r = await OpenFilex.open(f.path);
    if (r.type != ResultType.done && context.mounted) toast(context, 'Saved, but no app can open this file type', error: true);
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
  } catch (_) {
    if (context.mounted) toast(context, 'Could not open the file', error: true);
  }
}

String money(dynamic v, [String cur = 'USD']) {
  final n = v is num ? v : num.tryParse('$v') ?? 0;
  return NumberFormat.simpleCurrency(name: cur).format(n);
}

String fmtDate(dynamic iso, {bool time = true}) {
  final d = DateTime.tryParse('${iso ?? ''}')?.toLocal();
  if (d == null) return '';
  return DateFormat(time ? 'd MMM y, h:mm a' : 'd MMM y').format(d);
}


/// Downloads an email attachment through the API (with the login token) and opens it with the phone's viewer.
Future<void> openAttachment(BuildContext context, int id, String filename) async {
  try {
    toast(context, 'Opening $filename…');
    final bytes = await Api.of(context).download('inbox/attachments/$id');
    final dir = await getTemporaryDirectory();
    final safe = filename.replaceAll(RegExp(r'[^\w.\- ]'), '_');
    final f = File('${dir.path}/$safe');
    await f.writeAsBytes(bytes, flush: true);
    final r = await OpenFilex.open(f.path);
    if (r.type != ResultType.done && context.mounted) toast(context, 'No app on this phone can open this file type', error: true);
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
  } catch (_) {
    if (context.mounted) toast(context, 'Could not open the file', error: true);
  }
}


/// Turns a stored e-mail preview into readable text: removes leftover HTML tags, <style> CSS
/// (e.g. "body { padding:0 !important }"), HTML entities and the invisible padding characters newsletters add.
String cleanPreview(String? raw) {
  var s = raw ?? '';
  if (s.trim().isEmpty) return '';
  s = s.replaceAll(RegExp(r'<!--.*?-->', dotAll: true), ' ');
  s = s.replaceAll(RegExp(r'<(style|script|head|title)\b[^>]*>.*?</\1\s*>', caseSensitive: false, dotAll: true), ' ');
  s = s.replaceAll(RegExp(r'<[^>]*>'), ' ');
  const entities = {'&nbsp;': ' ', '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&apos;': "'", '&zwnj;': '', '&zwj;': '', '&hellip;': '…', '&rsquo;': '’', '&lsquo;': '‘', '&ndash;': '–', '&mdash;': '—'};
  entities.forEach((k, v) => s = s.replaceAll(k, v));
  s = s.replaceAllMapped(RegExp(r'&#(\d+);'), (m) {
    final c = int.tryParse(m[1]!);
    return c == null || c > 0x10FFFF ? ' ' : String.fromCharCode(c);
  });
  s = s.replaceAllMapped(RegExp(r'&#x([0-9a-fA-F]+);'), (m) {
    final c = int.tryParse(m[1]!, radix: 16);
    return c == null || c > 0x10FFFF ? ' ' : String.fromCharCode(c);
  });
  // CSS that leaked in as text: keep only what comes AFTER the last "{ property: value }" block.
  Match? last;
  for (final m in RegExp(r'\{[^{}]*:[^{}]*\}').allMatches(s)) {
    last = m;
  }
  if (last != null) s = s.substring(last.end);
  // A CSS block cut off by the server's 120-character limit has no closing brace: what remains is only CSS.
  final open = s.indexOf('{');
  if (open >= 0 && s.indexOf('}', open) < 0) s = open < 80 ? '' : s.substring(0, open);
  s = s.replaceAll(RegExp(r'[{}]'), ' ').replaceAll(RegExp(r'!important', caseSensitive: false), ' ');
  s = s.replaceAll(RegExp('[\u200B-\u200F\u2028-\u202F\u2060\u00AD\u034F\uFEFF\u180E]'), '');
  return s.replaceAll(RegExp(r'\s+'), ' ').trim();
}
