import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'config.dart';

/// How an in-app web flow ended.
class WebResult {
  final String status; // ok | error | closed
  final String message;
  const WebResult(this.status, [this.message = '']);
  bool get ok => status == 'ok';
}

/// A full-screen page hosting a payment gateway (Stripe, PayPal, Razorpay …) inside the app.
/// It watches every navigation: when the gateway sends the customer back to the DahiMail success / cancel
/// page (or the dahimail:// return link) the page closes itself and reports the result, so the user never
/// sees the website. Do NOT use this for Google / Facebook sign-in — those providers block embedded
/// browsers; use [connectOAuth] (secure in-app sheet) for them.
class InAppWebPage extends StatefulWidget {
  final String url;
  final String title;
  const InAppWebPage({super.key, required this.url, required this.title});
  @override
  State<InAppWebPage> createState() => _InAppWebPageState();
}

class _InAppWebPageState extends State<InAppWebPage> {
  late final WebViewController _c;
  int _progress = 0;
  bool _finished = false;

  void _finish(WebResult r) {
    if (_finished || !mounted) return;
    _finished = true;
    Navigator.of(context).pop(r);
  }

  @override
  void initState() {
    super.initState();
    _c = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (p) => mounted ? setState(() => _progress = p) : null,
        onNavigationRequest: (req) {
          final u = req.url;
          if (u.startsWith('${AppConfig.urlScheme}://')) {
            final q = Uri.tryParse(u)?.queryParameters ?? const {};
            _finish(WebResult(q['status'] == 'ok' ? 'ok' : 'error', q['message'] ?? ''));
            return NavigationDecision.prevent;
          }
          if (u.contains('/checkout-success')) {
            _finish(const WebResult('ok'));
            return NavigationDecision.prevent;
          }
          if (u.contains('/checkout-cancel')) {
            _finish(const WebResult('error', 'Payment was cancelled.'));
            return NavigationDecision.prevent;
          }
          // Wallet / bank apps (upi:, intent:, tez: …) cannot run in a WebView; ignore instead of showing an error page.
          if (!u.startsWith('http')) return NavigationDecision.prevent;
          return NavigationDecision.navigate;
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
  }

  Future<void> _onBack() async {
    if (await _c.canGoBack()) {
      await _c.goBack();
      return;
    }
    if (!mounted) return;
    final leave = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Leave payment?'),
        content: const Text('If you already paid, your plan updates automatically. Otherwise nothing has been charged.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Stay')),
          TextButton(onPressed: () => Navigator.pop(c, true), child: const Text('Leave')),
        ],
      ),
    );
    if (leave == true) _finish(const WebResult('closed'));
  }

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _onBack();
        },
        child: Scaffold(
          appBar: AppBar(
            title: Text(widget.title),
            leading: IconButton(tooltip: 'Close', icon: const Icon(Icons.close_rounded), onPressed: _onBack),
            bottom: _progress < 100 ? PreferredSize(preferredSize: const Size.fromHeight(3), child: LinearProgressIndicator(value: _progress / 100, minHeight: 3)) : null,
          ),
          body: SafeArea(child: WebViewWidget(controller: _c)),
        ),
      );
}
