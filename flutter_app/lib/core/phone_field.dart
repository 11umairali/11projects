import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'countries.dart';
import 'theme.dart';

/// Holds the chosen country and the typed national number (no country code, no example format).
class PhoneFieldController extends ChangeNotifier {
  Country? country;
  final TextEditingController number = TextEditingController();

  PhoneFieldController() {
    // Best guess from the phone's region; the person can change it.
    final code = WidgetsBinding.instance.platformDispatcher.locale.countryCode;
    if (code != null) {
      for (final c in kCountries) {
        if (c.iso == code.toUpperCase()) {
          country = c;
          break;
        }
      }
    }
  }

  String get national => number.text.trim();
  bool get filled => national.isNotEmpty;
  String? get iso => country?.iso;

  void pick(Country c) {
    country = c;
    notifyListeners();
  }

  void clear() {
    number.clear();
    notifyListeners();
  }

  @override
  void dispose() {
    number.dispose();
    super.dispose();
  }
}

/// "Country code" button (flag + code) + number box. The country comes from a searchable list of every country.
class PhoneField extends StatelessWidget {
  final PhoneFieldController controller;
  final String label;
  final bool enabled;
  const PhoneField({super.key, required this.controller, this.label = 'Phone number', this.enabled = true});

  Future<void> _choose(BuildContext context) async {
    final c = await showModalBottomSheet<Country>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CountrySheet(selected: controller.country?.iso),
    );
    if (c != null) controller.pick(c);
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: controller,
        builder: (context, _) {
          final c = controller.country;
          return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            InkWell(
              onTap: enabled ? () => _choose(context) : null,
              borderRadius: BorderRadius.circular(Rad.m),
              child: Container(
                height: 56,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(border: Border.all(color: AppColors.borderOf(context)), borderRadius: BorderRadius.circular(Rad.m)),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  if (c != null) ...[Text(c.flag, style: const TextStyle(fontSize: 22)), const SizedBox(width: 8), Text(c.dial, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15))] else const Text('Country code', style: TextStyle(color: AppColors.muted, fontSize: 14)),
                  const SizedBox(width: 4),
                  const Icon(Icons.arrow_drop_down_rounded, color: AppColors.muted),
                ]),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: TextField(
                controller: controller.number,
                enabled: enabled,
                keyboardType: TextInputType.phone,
                autofillHints: const [AutofillHints.telephoneNumberNational],
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9 ()\-]'))],
                decoration: InputDecoration(labelText: label),
              ),
            ),
          ]);
        },
      );
}

class _CountrySheet extends StatefulWidget {
  final String? selected;
  const _CountrySheet({this.selected});
  @override
  State<_CountrySheet> createState() => _CountrySheetState();
}

class _CountrySheetState extends State<_CountrySheet> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final q = _q.trim().toLowerCase();
    final list = q.isEmpty ? kCountries : kCountries.where((c) => c.name.toLowerCase().contains(q) || c.dial.contains(q) || c.iso.toLowerCase() == q).toList();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.8,
        child: Column(children: [
          const Padding(padding: EdgeInsets.fromLTRB(20, 0, 20, 8), child: Align(alignment: Alignment.centerLeft, child: Text('Choose country code', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18)))),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: TextField(autofocus: false, onChanged: (v) => setState(() => _q = v), decoration: const InputDecoration(hintText: 'Search country or code', prefixIcon: Icon(Icons.search_rounded))),
          ),
          Expanded(
            child: list.isEmpty
                ? const Center(child: Text('No country found', style: TextStyle(color: AppColors.muted)))
                : ListView.builder(
                    itemCount: list.length,
                    itemBuilder: (_, i) {
                      final c = list[i];
                      final sel = c.iso == widget.selected;
                      return ListTile(
                        dense: true,
                        leading: Text(c.flag, style: const TextStyle(fontSize: 24)),
                        title: Text(c.name, style: TextStyle(fontWeight: sel ? FontWeight.w700 : FontWeight.w500)),
                        trailing: Text(c.dial, style: const TextStyle(color: AppColors.muted, fontWeight: FontWeight.w600)),
                        selected: sel,
                        onTap: () => Navigator.pop(context, c),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }
}
