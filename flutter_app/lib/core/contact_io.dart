import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'api.dart';
import 'util.dart';

/// Pick a CSV on the phone and upload it straight to POST /api/v1/contacts/import (no website involved).
/// Returns true when at least one contact was imported.
Future<bool> importContacts(BuildContext context) async {
  final picked = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: const ['csv', 'txt'], withData: false);
  final path = picked?.files.single.path;
  if (path == null || !context.mounted) return false;
  showDialog(context: context, barrierDismissible: false, builder: (_) => const AlertDialog(content: Row(children: [CircularProgressIndicator(), SizedBox(width: 20), Expanded(child: Text('Importing contacts…'))])));
  try {
    final j = await Api.of(context).multipart('contacts/import', {}, fileField: 'file', filePath: path);
    if (!context.mounted) return false;
    Navigator.of(context, rootNavigator: true).pop();
    final d = Api.obj(j);
    final errs = [for (final e in (d['errors'] as List? ?? const [])) '$e'];
    final imported = (d['imported'] as num?)?.toInt() ?? 0;
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Import finished'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('$imported imported · ${d['skipped'] ?? 0} skipped', style: const TextStyle(fontWeight: FontWeight.w700)),
            if (errs.isNotEmpty) ...[const SizedBox(height: 10), const Text('Why rows were skipped:'), const SizedBox(height: 4), for (final e in errs.take(15)) Text('• $e', style: const TextStyle(fontSize: 12))],
          ]),
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Done'))],
      ),
    );
    return imported > 0;
  } on ApiException catch (e) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      toast(context, e.message, error: true);
    }
    return false;
  }
}

/// Downloads all contacts as CSV through the API and opens it with the phone's file/spreadsheet app.
Future<void> exportContacts(BuildContext context) => downloadAndOpen(context, 'contacts/export', 'contacts.csv');

/// Writes a ready-to-fill CSV with the exact column names the importer expects.
Future<void> contactsTemplate(BuildContext context) async {
  const csv = 'first_name,last_name,email,phone,company,job_title,city,country\nJane,Doe,jane@example.com,+15550100,Acme Inc,Manager,London,UK\n';
  final f = File('${(await getTemporaryDirectory()).path}/contacts-template.csv');
  await f.writeAsString(csv, flush: true);
  final r = await OpenFilex.open(f.path);
  if (r.type != ResultType.done && context.mounted) toast(context, 'Saved, but no app can open CSV files', error: true);
}
