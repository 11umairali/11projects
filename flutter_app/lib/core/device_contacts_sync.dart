import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api.dart';
import 'session.dart';

/// One account-scoped directory shared by the UI and background refresh.
class DeviceContactsSync extends ChangeNotifier {
  static final I = DeviceContactsSync();
  List<Map<String, dynamic>> contacts = [], matches = [];
  bool busy = false, allowed = false, paused = false;
  String? error, _scope;
  Session? _session;
  Timer? _changes;
  bool _listening = false;
  DateTime? _lastSync;

  static List<Map<String, dynamic>> normalize(Iterable<Contact> source) {
    final result = <Map<String, dynamic>>[], seen = <String>{};
    for (final c in source) {
      final phones = c.phones.map((p) => p.number.trim()).where((p) => p.replaceAll(RegExp(r'\D'), '').isNotEmpty).toSet().toList();
      final emails = c.emails.map((e) => e.address.trim()).where((e) => e.isNotEmpty).toSet().toList();
      var name = c.displayName.trim();
      if (name.isEmpty) name = [c.name.first, c.name.middle, c.name.last].where((s) => s.trim().isNotEmpty).join(' ').trim();
      if (name.isEmpty) name = phones.isNotEmpty ? phones.first : (emails.isNotEmpty ? emails.first : '');
      if (name.isEmpty) continue;
      final keys = <String>{};
      for (final phone in phones) {
        final digits = phone.replaceAll(RegExp(r'\D'), '');
        if (digits.length >= 8) keys.add(digits.length > 10 ? digits.substring(digits.length - 10) : digits);
      }
      final identity = jsonEncode([name.toLowerCase(), phones.toList()..sort(), emails.toList()..sort()]);
      if (!seen.add(identity)) continue;
      result.add({'name': name, 'phones': phones, 'emails': emails, 'keys': keys.toList()});
    }
    result.sort((a, b) => '${a['name']}'.toLowerCase().compareTo('${b['name']}'.toLowerCase()));
    return result;
  }

  static List<String> _strings(dynamic values) => values is List
      ? values.whereType<String>().map((s) => s.trim()).where((s) => s.isNotEmpty).toList() : [];

  static List<Map<String, dynamic>> _savedContacts(dynamic rows) => normalize(Api.list(rows).map((row) => Contact(
    displayName: row['name'] is String ? row['name'] : '',
    phones: _strings(row['phones']).map((number) => Phone(number)).toList(),
    emails: _strings(row['emails']).map((address) => Email(address)).toList(),
  )));

  static List<Map<String, dynamic>> _validMatches(dynamic rows) {
    final result = <Map<String, dynamic>>[];
    for (final row in Api.list(rows)) {
      final id = int.tryParse('${row['id']}');
      final name = row['name'] is String ? (row['name'] as String).trim() : '';
      if (id == null || id <= 0 || name.isEmpty) continue;
      result.add({...row, 'id': id, 'name': name,
        'phone_key': '${row['phone_key'] ?? ''}',
        'avatar_url': row['avatar_url'] is String ? row['avatar_url'] : null,
      });
    }
    return result;
  }

  Future<void> restore(Session session) async {
    final scope = Api.cacheScope(session);
    if (_scope != scope) {
      contacts = []; matches = []; allowed = false; paused = false; _lastSync = null;
    }
    _scope = scope; _session = session;
    final prefs = await SharedPreferences.getInstance();
    await prefs.reload();
    if (_scope != scope) return;
    paused = prefs.getBool('device_contacts_paused_$scope') ?? false;
    try {
      final saved = jsonDecode(prefs.getString('device_contacts_$scope') ?? '{}');
      // Discard invalid rows from older app versions too.
      contacts = _savedContacts(saved['contacts']);
      matches = _validMatches(saved['matches']);
    } catch (_) {}
    notifyListeners();
  }

  void watch() {
    if (_listening) return;
    _listening = true;
    FlutterContacts.addListener(_changed);
  }
  void _changed() {
    _changes?.cancel();
    _changes = Timer(const Duration(seconds: 1), () => sync(force: true));
  }
  void detach() {
    _changes?.cancel();
    if (_listening) FlutterContacts.removeListener(_changed);
    _listening = false; _session = null; _scope = null;
    contacts = []; matches = []; allowed = false;
  }

  Future<void> enable() async {
    final scope = _scope;
    if (scope == null) return;
    // Native permission sheet, without an extra full-screen app gate.
    final permission = await Permission.contacts.request();
    allowed = permission.isGranted || permission.isLimited;
    if (_scope != scope) return;
    if (allowed) {
      paused = false;
      await (await SharedPreferences.getInstance()).setBool('device_contacts_paused_$scope', false);
    }
    notifyListeners();
    if (allowed) await sync(force: true);
  }

  Future<void> pause() async {
    paused = true;
    await (await SharedPreferences.getInstance()).setBool('device_contacts_paused_$_scope', true);
    notifyListeners();
  }

  Future<void> sync({bool force = false, Future<bool> Function()? stillActive}) async {
    final session = _session, scope = _scope;
    if (session == null || scope == null || paused || busy) return;
    if (!force && _lastSync != null && DateTime.now().difference(_lastSync!).inMinutes < 5) return;
    busy = true; error = null; notifyListeners();
    try {
      final permission = await Permission.contacts.status;
      allowed = permission.isGranted || permission.isLimited;
      if (!allowed) return;
      final local = normalize(await FlutterContacts.getContacts(withProperties: true));
      if (scope != _scope || scope != Api.cacheScope(session)) return;
      contacts = local; notifyListeners();
      final keys = local.expand((c) => (c['keys'] as List).cast<String>()).toSet().toList();
      final found = <Map<String, dynamic>>[];
      for (var i = 0; i < keys.length; i += 3000) {
        if (scope != _scope || (stillActive != null && !await stillActive())) return;
        found.addAll(_validMatches(await Api(session).post('friends/sync-contacts', {'keys': keys.sublist(i, (i + 3000).clamp(0, keys.length)), 'directory': true})));
      }
      if (paused || scope != _scope || scope != Api.cacheScope(session) || (stillActive != null && !await stillActive())) return;
      matches = found; _lastSync = DateTime.now();
      await (await SharedPreferences.getInstance()).setString('device_contacts_$scope', jsonEncode({'contacts': local, 'matches': found}));
    } catch (_) { error = 'Could not update contacts. Pull down to retry.'; }
    finally { busy = false; notifyListeners(); }
  }
}
