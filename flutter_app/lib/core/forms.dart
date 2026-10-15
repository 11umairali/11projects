import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'api.dart';
import 'theme.dart';
import 'util.dart';
import 'widgets.dart';

enum FT { text, multiline, number, email, password, phone, url, dropdown, toggle, slider, section }

/// Declarative field for [FormBody].
class F {
  final String key;
  final String label;
  final FT type;
  final String? hint;
  final String? help;
  final bool required;
  final List<(String, String)> options; // (value, label)
  final double min;
  final double max;
  final int? divisions;
  final bool asInt;
  final dynamic initial;
  final bool Function(Map<String, dynamic>)? visible;
  final bool sendEmpty; // send "" instead of null when blank
  const F(this.key, this.label,
      {this.type = FT.text, this.hint, this.help, this.required = false, this.options = const [], this.min = 0, this.max = 100, this.divisions, this.asInt = true, this.initial, this.visible, this.sendEmpty = false});

  const F.section(String title)
      : key = '',
        label = title,
        type = FT.section,
        hint = null,
        help = null,
        required = false,
        options = const [],
        min = 0,
        max = 0,
        divisions = null,
        asInt = true,
        initial = null,
        visible = null,
        sendEmpty = false;
}

class FormBody extends StatefulWidget {
  final List<F> fields;
  final Map<String, dynamic> initial;
  final String submitLabel;
  final Future<void> Function(Map<String, dynamic> values) onSubmit;
  final List<Widget> header;
  final List<Widget> footer;
  final EdgeInsets padding;
  final bool scroll;
  const FormBody({
    super.key,
    required this.fields,
    required this.onSubmit,
    this.initial = const {},
    this.submitLabel = 'Save',
    this.header = const [],
    this.footer = const [],
    this.padding = const EdgeInsets.fromLTRB(16, 12, 16, 96),
    this.scroll = true,
  });

  @override
  State<FormBody> createState() => FormBodyState();
}

class FormBodyState extends State<FormBody> {
  final _v = <String, dynamic>{};
  final _c = <String, TextEditingController>{};
  final _err = <String, String>{};
  bool _busy = false, _hide = true;

  bool _isText(FT t) => t != FT.toggle && t != FT.slider && t != FT.dropdown && t != FT.section;

  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      if (f.type == FT.section) continue;
      final init = widget.initial.containsKey(f.key) ? widget.initial[f.key] : f.initial;
      if (_isText(f.type)) {
        _c[f.key] = TextEditingController(text: init == null ? '' : '$init');
      } else if (f.type == FT.toggle) {
        _v[f.key] = init == true || init == 1 || init == '1';
      } else if (f.type == FT.slider) {
        _v[f.key] = (init is num ? init.toDouble() : double.tryParse('$init') ?? f.min).clamp(f.min, f.max);
      } else {
        final has = f.options.any((o) => o.$1 == '$init');
        _v[f.key] = has ? '$init' : (f.options.isNotEmpty ? f.options.first.$1 : '');
      }
    }
  }

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> collect() {
    final out = <String, dynamic>{};
    for (final f in widget.fields) {
      if (f.type == FT.section) continue;
      if (_isText(f.type)) {
        final t = _c[f.key]!.text.trim();
        if (t.isEmpty) {
          out[f.key] = f.sendEmpty ? '' : null;
        } else if (f.type == FT.number) {
          out[f.key] = num.tryParse(t) ?? t;
        } else {
          out[f.key] = f.type == FT.password ? _c[f.key]!.text : t;
        }
      } else if (f.type == FT.slider) {
        final d = _v[f.key] as double;
        out[f.key] = f.asInt ? d.round() : double.parse(d.toStringAsFixed(2));
      } else {
        out[f.key] = _v[f.key];
      }
    }
    return out;
  }

  bool _visible(F f) => f.visible == null || f.visible!(collect());

  Future<void> _submit() async {
    final vals = collect();
    _err.clear();
    for (final f in widget.fields) {
      if (f.type == FT.section || !_visible(f)) continue;
      if (f.required && (vals[f.key] == null || '${vals[f.key]}'.isEmpty)) _err[f.key] = '${f.label} is required';
    }
    if (_err.isNotEmpty) {
      setState(() {});
      return;
    }
    // Drop hidden fields so they are not validated server-side.
    for (final f in widget.fields) {
      if (f.type != FT.section && !_visible(f)) vals.remove(f.key);
    }
    setState(() => _busy = true);
    try {
      await widget.onSubmit(vals);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(F f) {
    final err = _err[f.key];
    switch (f.type) {
      case FT.section:
        return SectionHeader(f.label);
      case FT.toggle:
        return SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(f.label),
          subtitle: f.help == null ? null : Text(f.help!, style: const TextStyle(fontSize: 12)),
          value: _v[f.key] as bool,
          activeThumbColor: AppColors.primary,
          onChanged: (b) => setState(() => _v[f.key] = b),
        );
      case FT.slider:
        final d = _v[f.key] as double;
        return Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${f.label}: ${f.asInt ? d.round() : d.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w500)),
            Slider(value: d, min: f.min, max: f.max, divisions: f.divisions, activeColor: AppColors.primary, onChanged: (x) => setState(() => _v[f.key] = x)),
            if (f.help != null) Text(f.help!, style: const TextStyle(fontSize: 12, color: AppColors.muted)),
          ]),
        );
      case FT.dropdown:
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: DropdownButtonFormField<String>(
            initialValue: _v[f.key] as String?,
            isExpanded: true,
            decoration: InputDecoration(labelText: f.label, helperText: f.help, errorText: err),
            items: [for (final o in f.options) DropdownMenuItem(value: o.$1, child: Text(o.$2, overflow: TextOverflow.ellipsis))],
            onChanged: (x) => setState(() => _v[f.key] = x ?? ''),
          ),
        );
      default:
        final pw = f.type == FT.password;
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: TextField(
            controller: _c[f.key],
            obscureText: pw && _hide,
            minLines: f.type == FT.multiline ? 4 : 1,
            maxLines: f.type == FT.multiline ? 12 : 1,
            keyboardType: switch (f.type) {
              FT.email => TextInputType.emailAddress,
              FT.number => const TextInputType.numberWithOptions(decimal: true),
              FT.phone => TextInputType.phone,
              FT.url => TextInputType.url,
              FT.multiline => TextInputType.multiline,
              _ => TextInputType.text,
            },
            autocorrect: !(pw || f.type == FT.email || f.type == FT.url),
            decoration: InputDecoration(
              labelText: f.required ? '${f.label} *' : f.label,
              hintText: f.hint,
              helperText: f.help,
              helperMaxLines: 3,
              errorText: err,
              alignLabelWithHint: f.type == FT.multiline,
              suffixIcon: pw ? IconButton(tooltip: 'Show or hide password', icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined), onPressed: () => setState(() => _hide = !_hide)) : null,
            ),
          ),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final children = <Widget>[
      ...widget.header,
      for (final f in widget.fields)
        if (_visible(f)) _field(f),
      ...widget.footer,
      const SizedBox(height: 8),
      FilledButton(
        onPressed: _busy ? null : _submit,
        child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text(widget.submitLabel),
      ),
    ];
    if (!widget.scroll) return Padding(padding: widget.padding, child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: children));
    return ListView(padding: widget.padding, children: children);
  }
}

/// Loads server data, then shows a [FormBody]. Used for every settings page.
class LoadedForm extends StatelessWidget {
  final Future<Map<String, dynamic>> Function(Api api) load;
  final List<F> fields;
  final Future<dynamic> Function(Api api, Map<String, dynamic> values) save;
  final String submitLabel;
  final String successMessage;
  final List<Widget> Function(Map<String, dynamic> data)? header;
  final List<Widget> Function(Map<String, dynamic> data, Future<void> Function() reload)? footer;
  final bool popOnSave;
  final Map<String, dynamic> Function(Map<String, dynamic> loaded)? transform;

  const LoadedForm({
    super.key,
    required this.load,
    required this.fields,
    required this.save,
    this.submitLabel = 'Save changes',
    this.successMessage = 'Saved',
    this.header,
    this.footer,
    this.popOnSave = false,
    this.transform,
  });

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      load: load,
      builder: (c, data, reload) => FormBody(
        fields: fields,
        initial: transform != null ? transform!(data) : data,
        submitLabel: submitLabel,
        header: header?.call(data) ?? const [],
        footer: footer?.call(data, reload) ?? const [],
        onSubmit: (vals) async {
          final r = await save(Api.of(c), vals);
          if (!c.mounted) return;
          String msg = successMessage;
          if (r is Map && r['message'] != null && successMessage == '*') msg = '${r['message']}';
          toast(c, msg == '*' ? 'Saved' : msg);
          if (popOnSave) {
            Navigator.of(c).pop(true);
          } else {
            await reload();
          }
        },
      ),
    );
  }
}

/// Pick an image from the gallery (avatar / logo). Returns file path or null.
Future<String?> pickImage(BuildContext c) async {
  try {
    final x = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1024, imageQuality: 85);
    if (x == null) return null;
    if (File(x.path).lengthSync() > 2 * 1024 * 1024) {
      if (c.mounted) toast(c, 'Image must be under 2 MB', error: true);
      return null;
    }
    return x.path;
  } catch (_) {
    if (c.mounted) toast(c, 'Could not open the photo library', error: true);
    return null;
  }
}
