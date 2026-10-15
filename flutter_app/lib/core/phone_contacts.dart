import 'package:flutter_contacts/flutter_contacts.dart';
import 'app_permissions.dart';

/// Reads the phone numbers in the phone's contact list (with the person's permission) and turns each into the same "key" the
/// server uses to compare numbers: digits only, last 10 digits. Names are synced privately for this account alongside these keys.
class PhoneContacts {
  /// null = the person refused the permission.
  static Future<List<Map<String, String>>?> read() async {
    if (!await AppPermissions.contacts()) return null; // our explanation first, then the phone's dialog
    if (!await FlutterContacts.requestPermission(readonly: true)) return null;
    final contacts = await FlutterContacts.getContacts(withProperties: true, withPhoto: false);
    final out = <String, String>{};
    for (final c in contacts) {
      for (final p in c.phones) {
        final d = p.number.replaceAll(RegExp(r'\D'), '');
        if (d.length < 8) continue; // too short to identify anybody
        final key = d.length > 10 ? d.substring(d.length - 10) : d;
        if (c.displayName.trim().isNotEmpty) out.putIfAbsent(key, () => c.displayName.trim());
      }
    }
    return out.entries.take(3000).map((e) => {'key': e.key, 'name': e.value.length > 255 ? e.value.substring(0, 255) : e.value}).toList();
  }
}
