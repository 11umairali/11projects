// Refresh meeting status without discarding scheduling or rescheduling edits.
(function () {
  var root = document.getElementById('meeting-lists'), busy = false;
  if (!root) return;
  async function refresh() {
    if (busy || document.hidden || root.querySelector('details[open]') || root.contains(document.activeElement)) return;
    busy = true;
    try {
      var response = await fetch('/meetings?_live=1', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } });
      if (!response.ok || response.redirected) return;
      var html = await response.text();
      if (!root.querySelector('details[open]') && !root.contains(document.activeElement)) root.innerHTML = html;
    } catch (_) { /* Keep the current list during a network interruption. */ }
    finally { busy = false; }
  }
  setInterval(refresh, 3000);
  window.addEventListener('dahi:sync', refresh);
  window.addEventListener('online', refresh);
  document.addEventListener('visibilitychange', refresh);
})();
