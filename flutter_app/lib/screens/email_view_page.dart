import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../core/config.dart';
import '../core/util.dart';

/// Browser-quality rendering of an e-mail body: real CSS layout, images, pinch-zoom.
///  • Fits the screen automatically (wide newsletter layouts are scaled down – no manual zoom-out needed).
///  • Pull down from the top to reload (also re-downloads the e-mail); a Reload button appears if loading fails.
///  • Safe: a Content-Security-Policy blocks every script inside the e-mail, links open outside the page.
class EmailWebBody extends StatefulWidget {
  final String html;
  /// Re-downloads the e-mail (called on pull-to-reload / Reload button). Without it only the page is re-rendered.
  final Future<void> Function()? onReload;
  const EmailWebBody({super.key, required this.html, this.onReload});
  @override
  State<EmailWebBody> createState() => _EmailWebBodyState();
}

class _EmailWebBodyState extends State<EmailWebBody> {
  late final WebViewController _c;
  int _progress = 0;
  bool _failed = false, _retried = false, _reloading = false;
  double _scrollY = 0, _pull = 0;
  Offset? _start;
  bool _armed = false;
  int _pointers = 0;
  static const _trigger = 90.0;

  /// The page stays INVISIBLE until it has been scaled to the screen width, so the e-mail never appears "too big"
  /// and then jumps. The fit script is our own inline script, allowed by a one-time nonce; scripts inside the
  /// e-mail have no nonce and are blocked by the Content-Security-Policy.
  static String _wrap(String body, String nonce) => '''<!DOCTYPE html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="script-src 'nonce-$nonce'; object-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'">
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.25, maximum-scale=5">
<style>
  html{visibility:hidden}
  html,body{margin:0;padding:0;background:#fff;color:#1f1f29}
  body{padding:12px;font-family:-apple-system,Roboto,"Segoe UI",sans-serif;font-size:15px;line-height:1.45;overflow-wrap:anywhere;word-wrap:break-word}
  img{max-width:100%!important;height:auto!important}
  table{max-width:100%!important}
  pre{white-space:pre-wrap}
  a{color:#5f33e1}
  blockquote{margin:8px 0;padding-left:10px;border-left:3px solid #ddd;color:#555}
</style></head><body>$body
<script nonce="$nonce">
(function(){
  var d=document.documentElement;
  function fit(){
    var b=document.body; if(!b) return;
    b.style.zoom='1';
    var vw=d.clientWidth||window.innerWidth;
    var w=Math.max(d.scrollWidth,b.scrollWidth);
    var z=(w>vw+2)?vw/w:1;
    if(z<1) b.style.zoom=z.toFixed(4);
  }
  fit();
  d.style.visibility='visible';
  window.addEventListener('load',fit);
  window.addEventListener('resize',fit);
  setTimeout(fit,350);
})();
</script></body></html>''';

  static String _newNonce() => base64Url.encode(List<int>.generate(16, (_) => Random.secure().nextInt(256))).replaceAll('=', '');

  /// Scales the page down when its content is wider than the screen (fixed 600–700 px newsletter tables).
  /// Runs from the app (not from the e-mail): the CSP above blocks scripts inside the e-mail itself.
  static const _fitJs = '''(function(){var d=document.documentElement,b=document.body;if(!b)return;
b.style.zoom='1';var vw=d.clientWidth||window.innerWidth;var w=Math.max(d.scrollWidth,b.scrollWidth);
if(w>vw+2){b.style.zoom=(vw/w).toFixed(4);}d.style.visibility='visible';})();''';

  @override
  void initState() {
    super.initState();
    _c = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted) // only used for the fit script above; e-mail scripts are blocked by CSP
      ..setBackgroundColor(Colors.white)
      ..enableZoom(true)
      ..setOnScrollPositionChange((p) => _scrollY = p.y)
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (p) => mounted ? setState(() => _progress = p) : null,
        onPageFinished: (_) => _fit(),
        onWebResourceError: (e) {
          if (e.isForMainFrame != true) return; // a single missing image is not a failed e-mail
          if (!_retried) {
            _retried = true;
            Future.delayed(const Duration(milliseconds: 600), () => mounted ? _load() : null);
          } else if (mounted) {
            setState(() => _failed = true);
          }
        },
        onNavigationRequest: (r) {
          final u = r.url;
          if (u.startsWith('about:') || u.startsWith('data:')) return NavigationDecision.navigate;
          openUrl(u); // links leave the page
          return NavigationDecision.prevent;
        },
      ));
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    if (_failed) setState(() => _failed = false);
    await _c.loadHtmlString(_wrap(widget.html, _newNonce()), baseUrl: AppConfig.baseUrl);
  }

  Future<void> _fit() async {
    try {
      await _c.runJavaScript(_fitJs); // safety net only – the page normally fitted itself before it was shown
    } catch (_) {}
  }

  @override
  void didUpdateWidget(covariant EmailWebBody old) {
    super.didUpdateWidget(old);
    if (old.html != widget.html) {
      _retried = false;
      _load();
    }
  }

  Future<void> _reload() async {
    if (_reloading) return;
    setState(() { _reloading = true; _retried = false; _failed = false; });
    try {
      if (widget.onReload != null) {
        await widget.onReload!(); // usually swaps this widget for a fresh one
      } else {
        await _load();
      }
    } catch (_) {}
    if (mounted) setState(() => _reloading = false);
  }

  // ── pull-down-to-reload: the page is a native view, so the gesture is detected from raw touches ──
  void _down(PointerDownEvent e) {
    _pointers++;
    _start = e.position;
    _armed = _pointers == 1 && _scrollY <= 1 && !_reloading;
  }

  void _move(PointerMoveEvent e) {
    if (!_armed || _start == null) return;
    if (_pointers > 1 || _scrollY > 1) {
      _armed = false;
      if (_pull != 0) setState(() => _pull = 0);
      return;
    }
    final dy = e.position.dy - _start!.dy;
    final dx = (e.position.dx - _start!.dx).abs();
    if (dy <= 0 || dx > dy) {
      if (_pull != 0) setState(() => _pull = 0);
      return;
    }
    setState(() => _pull = dy.clamp(0.0, 140.0));
  }

  void _up(PointerEvent e) {
    _pointers = (_pointers - 1).clamp(0, 10);
    final go = _armed && _pull >= _trigger;
    _armed = false;
    _start = null;
    if (_pull != 0) setState(() => _pull = 0);
    if (go) _reload();
  }

  @override
  Widget build(BuildContext context) {
    final showPull = _pull > 8 || _reloading;
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: _down,
      onPointerMove: _move,
      onPointerUp: _up,
      onPointerCancel: _up,
      child: Stack(children: [
        WebViewWidget(controller: _c),
        if (_progress < 100 && !_reloading) Positioned(top: 0, left: 0, right: 0, child: LinearProgressIndicator(value: _progress / 100, minHeight: 3)),
        if (showPull)
          Positioned(
            top: 8 + (_reloading ? 24.0 : (_pull / 3)),
            left: 0,
            right: 0,
            child: Center(
              child: Material(
                elevation: 3,
                shape: const CircleBorder(),
                color: Colors.white,
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2.4, value: _reloading ? null : (_pull / _trigger).clamp(0.0, 1.0)),
                  ),
                ),
              ),
            ),
          ),
        if (_failed)
          Positioned.fill(
            child: Container(
              color: Colors.white,
              alignment: Alignment.center,
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.cloud_off_rounded, size: 44, color: Color(0xFF706F86)),
                const SizedBox(height: 12),
                const Text('This email could not be loaded.', style: TextStyle(color: Color(0xFF1F1F29), fontSize: 15)),
                const SizedBox(height: 14),
                FilledButton.icon(style: FilledButton.styleFrom(minimumSize: const Size(140, 46)), onPressed: _reload, icon: const Icon(Icons.refresh_rounded), label: const Text('Reload')),
              ]),
            ),
          ),
      ]),
    );
  }
}

/// Stand-alone full-screen version (kept for other screens that only have the HTML).
class EmailViewPage extends StatelessWidget {
  final String html;
  final String title;
  const EmailViewPage({super.key, required this.html, required this.title});
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title.isEmpty ? 'Email' : title, maxLines: 1, overflow: TextOverflow.ellipsis)),
        body: SafeArea(child: EmailWebBody(html: html)),
      );
}
