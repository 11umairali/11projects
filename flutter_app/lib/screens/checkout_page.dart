import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/session.dart';
import 'package:flutter/services.dart';
import '../core/api.dart';
import '../core/inapp_web.dart';
import '../core/paged.dart';
import '../core/theme.dart';
import '../core/util.dart';
import '../core/widgets.dart';

/// Native plan checkout: coupon, payment method, bank-transfer details and confirmation, all inside the app.
class CheckoutPage extends StatefulWidget {
  final Item plan;
  final String cycle; // monthly | yearly
  const CheckoutPage({super.key, required this.plan, required this.cycle});
  @override
  State<CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends State<CheckoutPage> {
  List<Item> _gateways = [];
  String? _gw;
  bool _loading = true, _busy = false, _verifying = false;
  final _ref = TextEditingController();
  String? _error;
  final _coupon = TextEditingController();
  Map<String, dynamic>? _couponInfo;
  Map<String, dynamic>? _result; // response of POST checkout
  bool _confirmed = false;

  double get _base => ((widget.cycle == 'yearly' ? widget.plan['yearly_price'] : widget.plan['monthly_price']) as num? ?? 0).toDouble();

  double get _total {
    final c = _couponInfo;
    if (c == null || c['valid'] != true) return _base;
    final v = (c['value'] as num? ?? 0).toDouble();
    final off = c['type'] == 'percent' ? _base * v / 100 : v;
    return (_base - off).clamp(0, double.infinity).toDouble();
  }

  @override
  void initState() {
    super.initState();
    final saved = Api.peek('checkout/gateways');
    if (saved != null) { _gateways = Api.list(saved); _gw = _gateways.isNotEmpty ? '${_gateways.first['slug']}' : null; _loading = false; }
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadGateways());
  }

  @override
  void dispose() {
    _coupon.dispose();
    _ref.dispose();
    super.dispose();
  }

  Future<void> _loadGateways() async {
    try {
      final g = Api.list(await Api.of(context).get('checkout/gateways'));
      if (!mounted) return;
      setState(() {
        _gateways = g;
        if (!g.any((item) => '${item['slug']}' == _gw)) _gw = g.isNotEmpty ? '${g.first['slug']}' : null;
        _loading = false;
        _error = g.isEmpty ? 'No payment method is enabled yet. Please contact support.' : null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() { _error = e.message; _loading = false; });
    }
  }

  Future<void> _applyCoupon() async {
    final code = _coupon.text.trim();
    if (code.isEmpty) return;
    try {
      final r = await Api.of(context).post('checkout/coupon', {'code': code, 'plan_id': widget.plan['id']});
      if (!mounted) return;
      setState(() => _couponInfo = r is Map ? Map<String, dynamic>.from(r) : null);
      toast(context, '${_couponInfo?['message'] ?? ''}', error: _couponInfo?['valid'] != true);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  Future<void> _pay() async {
    if (_gw == null) return;
    setState(() => _busy = true);
    try {
      final r = await Api.of(context).post('checkout', {
        'plan_id': widget.plan['id'],
        'gateway': _gw,
        'billing_cycle': widget.cycle,
        'currency': 'USD',
        if (_couponInfo?['valid'] == true) 'coupon_code': _coupon.text.trim(),
      });
      if (!mounted) return;
      final m = r is Map ? Map<String, dynamic>.from(r) : <String, dynamic>{};
      if ((m['type'] == 'redirect' && m['url'] != null) || (m['type'] == 'web' && m['path'] != null)) {
        // Card / wallet gateways: the payment page opens INSIDE the app and closes itself when done.
        final web = await openPaymentPage(context, url: m['type'] == 'redirect' ? '${m['url']}' : null, path: m['type'] == 'web' ? '${m['path']}' : null, title: 'Pay ${money(m['amount'], '${m['currency'] ?? 'USD'}')}');
        if (!mounted) return;
        await _afterPayment(m['payment_id'], web);
      } else {
        setState(() => _result = m);
      }
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Shows the real outcome: asks the server for the payment status (gateways confirm via webhook, which can lag a few seconds).
  Future<void> _afterPayment(dynamic paymentId, WebResult web) async {
    if (web.status == 'error' && web.message.isNotEmpty) {
      toast(context, web.message, error: true);
      return;
    }
    setState(() => _verifying = true);
    String status = 'pending';
    for (var i = 0; i < 6 && mounted; i++) {
      try {
        final j = await Api.of(context).get('checkout/$paymentId/status');
        status = '${Api.obj(j)['status']}';
      } catch (_) {}
      if (status != 'pending') break;
      await Future.delayed(const Duration(seconds: 2));
    }
    if (!mounted) return;
    setState(() => _verifying = false);
    if (status == 'succeeded') {
      toast(context, 'Payment successful. Your plan is active.');
      Navigator.of(context).pop(true);
    } else if (status == 'failed') {
      toast(context, 'Payment failed. You have not been charged.', error: true);
    } else {
      toast(context, web.status == 'closed' ? 'Payment not completed.' : 'Payment received — your plan will activate as soon as the gateway confirms it.');
      if (web.status != 'closed') Navigator.of(context).pop(true);
    }
  }

  Future<void> _confirm() async {
    setState(() => _busy = true);
    try {
      final r = await Api.of(context).post('checkout/${_result!['payment_id']}/confirm', {if (_ref.text.trim().isNotEmpty) 'reference': _ref.text.trim()});
      if (!mounted) return;
      toast(context, r is Map ? '${r['message']}' : 'Done');
      setState(() => _confirmed = true);
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!context.watch<Session>().billingEnabled) return const Scaffold(body: Center(child: Text('All features are free and unlimited.')));
    final title = _result != null ? 'Complete your payment' : 'Checkout';
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: _verifying
          ? const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [CircularProgressIndicator(), SizedBox(height: 16), Text('Confirming your payment…')]))
          : _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _result == null
              ? EmptyState(icon: Icons.payments_outlined, text: _error!)
              : _result != null
                  ? _manual(context)
                  : _form(context),
    );
  }

  Widget _form(BuildContext context) => ListView(padding: const EdgeInsets.all(16), children: [
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${widget.plan['name']} plan', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
            Text(widget.cycle == 'yearly' ? 'Billed yearly' : 'Billed monthly', style: const TextStyle(color: AppColors.muted)),
            const Divider(height: 24),
            Row(children: [const Expanded(child: Text('Price')), Text(money(_base))]),
            if (_couponInfo?['valid'] == true) Row(children: [Expanded(child: Text('Coupon (${_couponInfo!['message']})')), Text('- ${money(_base - _total)}', style: const TextStyle(color: AppColors.success))]),
            const SizedBox(height: 6),
            Row(children: [const Expanded(child: Text('Total', style: TextStyle(fontWeight: FontWeight.w800))), Text(money(_total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))]),
          ]),
        ),
        const SectionHeader('Coupon'),
        Row(children: [
          Expanded(child: TextField(controller: _coupon, textCapitalization: TextCapitalization.characters, decoration: const InputDecoration(hintText: 'Have a coupon code?'))),
          const SizedBox(width: 8),
          OutlinedButton(onPressed: _applyCoupon, child: const Text('Apply')),
        ]),
        const SectionHeader('Payment method'),
        for (final g in _gateways)
          AppCard(
            padding: EdgeInsets.zero,
            child: ListTile(
              onTap: () => setState(() => _gw = '${g['slug']}'),
              leading: Icon(g['manual'] == true ? Icons.account_balance_rounded : Icons.credit_card_rounded, color: AppColors.primary),
              title: Text('${g['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: g['manual'] == true ? const Text('Pay by bank transfer, activated after we confirm') : null,
              trailing: Icon(_gw == '${g['slug']}' ? Icons.radio_button_checked : Icons.radio_button_off, color: AppColors.primary),
            ),
          ),
        const SizedBox(height: 16),
        FilledButton(
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50)),
          onPressed: _busy || _gw == null ? null : _pay,
          child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text('Continue · ${money(_total)}'),
        ),
      ]);

  Widget _manual(BuildContext context) {
    final r = _result!;
    final details = Api.list(r['details']);
    return ListView(padding: const EdgeInsets.all(16), children: [
      AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Transfer ${money(r['amount'], '${r['currency'] ?? 'USD'}')}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          const SizedBox(height: 4),
          const Text('Send the exact amount to this bank account and use the payment reference so we can match it.', style: TextStyle(color: AppColors.muted, height: 1.35)),
          const Divider(height: 24),
          for (final d in details)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(children: [
                Expanded(flex: 2, child: Text('${d['label']}', style: const TextStyle(color: AppColors.muted, fontSize: 13))),
                Expanded(flex: 3, child: SelectableText('${d['value']}', style: const TextStyle(fontWeight: FontWeight.w600))),
                InkWell(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: '${d['value']}'));
                    toast(context, 'Copied ${d['label']}');
                  },
                  child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.copy_rounded, size: 18, color: AppColors.primary)),
                ),
              ]),
            ),
          if ('${r['instructions'] ?? ''}'.isNotEmpty) ...[const Divider(height: 24), Text('${r['instructions']}', style: const TextStyle(height: 1.4))],
        ]),
      ),
      const SizedBox(height: 16),
      if (!_confirmed) ...[
        TextField(controller: _ref, decoration: const InputDecoration(labelText: 'Transfer reference / transaction ID (optional)', prefixIcon: Icon(Icons.tag_rounded))),
        const SizedBox(height: 12),
        FilledButton.icon(
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50)),
          icon: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.check_circle_outline),
          label: const Text('I have made the transfer'),
          onPressed: _busy ? null : _confirm,
        ),
      ] else ...[
        const AppCard(child: Row(children: [Icon(Icons.hourglass_top_rounded, color: AppColors.warn), SizedBox(width: 10), Expanded(child: Text('Thanks! Your plan will be activated as soon as we confirm the payment.'))])),
        const SizedBox(height: 12),
        OutlinedButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Back to billing')),
      ],
    ]);
  }
}
