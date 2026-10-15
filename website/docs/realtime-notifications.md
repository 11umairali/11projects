# Realtime updates and device notifications

## Email refresh and notification layout correction: 1.0.17+6

Deploy both the Laravel files and rebuilt Flutter app from this update. It
supersedes the earlier 1.0.16+5 ZIP.

Mail reloads the saved server list immediately on every visit, even while a
provider import is pending. Forced list reloads bypass older prefetch requests;
late responses cannot overwrite the newer cached result. Pull-to-refresh queues
one more provider pass if an automatic sync is already running. Socket/FCM
invalidations and polling continue to refresh the visible list without a gesture.

Gmail's HTTP and worker paths now share cursor handling. Incremental pages retain
their original query and next-page token; failed message fetches are retried
before the cursor advances. The sync timestamp advances only after the complete
window drains, using its start time. Per-account locks prevent concurrent workers
and HTTP requests from replacing each other's cursor. IMAP foreground imports
headers without waiting for the full background body backfill; message bodies
remain available on demand. Manual refresh reports failed accounts rather than
silently treating a provider error as an empty successful import.

Internal notifications use a contained sender avatar/category badge, full name,
message preview and compact top-right relative time. They have no extra action
row. Foreground Firebase messages use this in-app layout when the banner host is
ready; startup and PiP fall back to system alerts. The same card presentation
serves the notification list and older card API.

Android system alerts have separate compact and expanded layouts. Compact
content stays within the 48dp collapsed content budget. Expanded/headsup content
retains app brand/category overlay at left, sender/content in the center, and
time above the circular avatar at right. Replyable alerts offer View and inline
Reply. View opens a styled sheet containing only the notified message, including
older chat messages outside the newest page, with no read receipt. Events with
no reply target offer View only. Ringing calls keep Android CallStyle and their
Answer/Decline actions; missed calls use the common notification layout.

On iOS, sender name appears above the message with the existing View/Reply
categories. Apple controls system icon/avatar/time placement; no custom iOS
content extension is included. Android controls its system header, available
space, privacy settings and heads-up availability. Synthetic render previews
verify app content, not an OEM's final notification container or real delivery.

After deploying Laravel, run `php artisan optimize:clear` and
`php artisan queue:restart`. No schema migration is added by this update; existing
project migrations must already be applied. Keep scheduler and queue workers
running. Rebuild the app with the existing Firebase configuration/signing keys,
install it, and open once to register version 1.0.17+6.

On a signed Android phone check Mail when already open, on repeated returns from
Chats during an active sync, with pull-to-refresh, and after resuming. Check
heads-up/shade/lock-screen presentation, View's exact full message, inline Reply,
and missing/download-failed photos. Real FCM/APNs delivery and native iOS builds
require the respective configured devices/toolchains.

## Delivery diagnostics update: 1.0.10+11

“This phone is saved on the website” means registration was stored. It does not
prove Google accepted a push. The app now retains the most recent provider check
for the current token/session when registering again on resume. A rejected check
keeps the polling fallback enabled until another provider check succeeds; saving
the same token cannot turn a rejection into a successful delivery status.

Settings → Notifications → **Send test notification** uses the existing
authenticated `me/devices/check-push` endpoint with `send_test: true`. It sends a
real alert only to this signed-in phone. **Check connection** remains validation
only. Older websites that ignore `send_test` cannot claim that a test was sent.
Receipt time refreshes when a foreground push arrives and when the app resumes.
The installed app must be rebuilt to version **1.0.10+11** to include these controls.

To diagnose an installed build without rebuilding, run from the deployed Laravel
root (replace `123` with the receiving account's user ID):

```sh
php artisan push:status --user=123 --test
```

Keep the recipient app backgrounded for this test, then reopen Notifications and
inspect the receipt time and Phone delivery details. Share the command output
and that screen; the command does not print private keys or device tokens.
An `authentication_rejected` result still requires repairing the server key or
clock. An `accepted` result followed by no phone receipt requires checking the
installed build/token and device delivery, rather than treating registration as
evidence that a notification was sent. The automatic tests simulate providers;
production transport and the affected physical phone remain unverified.

## Mobile notification fix: 1.0.9+10

Rebuild and install the mobile app with the version in `flutter_app/pubspec.yaml`.
The previous manifest still declared `1.0.0+2`, so registration selected the
server's legacy alert format instead of the native Android View/Reply path.
Open the updated app once while signed in to register its new version and token.
Do not override the build name with an older version.

Foreground chat, email, friend request/acceptance and general pushes now create
system notifications even when an in-app banner cannot be displayed. Chats
currently being read remain quiet. Device category controls, quiet hours and
the foreground sound preference still apply. Background alerts retain the
phone's default sound, independently of the foreground sound preference.

The server must have the device metadata migration applied and valid Firebase
configuration. From the deployed Laravel root, run `php artisan migrate --force`
and `php artisan optimize:clear` using the normal deployment process. Diagnose
an affected account with `php artisan push:status --user=<id> --probe`, then
`php artisan push:status --user=<id> --test` to send a real test alert. A successful
probe is not evidence of phone receipt. Use Settings → Device notifications →
Check connection and Android delivery details to distinguish configuration,
registration and OS permission/channel failures.

Verify chat, new email, friend request, acceptance and an ordinary activity alert
with two accounts while the recipient app is open, backgrounded, locked and
swiped away. A PHP-only deployment cannot update the installed mobile app.
Production FCM/APNs delivery and physical-phone presentation require this check.

## Web deployment

Install the committed Composer/npm dependencies and build frontend assets:

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
```

For instant broadcasts, configure a Pusher Channels app or a compatible server:

```dotenv
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=<app id>
PUSHER_APP_KEY=<public key>
PUSHER_APP_SECRET=<server secret>
PUSHER_APP_CLUSTER=mt1
```

For a custom compatible server, set `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME` for Laravel's publishing endpoint, and `PUSHER_WS_HOST`, `PUSHER_WS_PORT`, `PUSHER_WS_SCHEME` for the browser's reachable WebSocket endpoint. Use HTTPS/WSS in production. The browser reads only the public configuration from page metadata. The secret remains on the server.

`POST /broadcasting/auth` uses the authenticated web session and CSRF protection. Private `user.{id}` channels require that exact user; private `workspace.{id}` channels require membership. `state.changed` is an invalidation signal: components fetch current data through their authorized endpoints. Events publish after the database commit; a failed broadcast does not roll back the business operation.

Without a configured broadcaster (`BROADCAST_CONNECTION=null`), Friends and inbox views still refresh every five seconds and the notification center every ten seconds. Foreground FCM events, reconnect and resume also refresh those views. Inbox refresh retains filters, selection and loaded pages (up to 200 rows). Friend previews respect per-user chat clears and recording visibility.

## Email and social alerts

New incremental email sync creates a database notification for the mailbox owner, then sends a visible mobile push. Initial imports and mail older than the previous sync anchor do not flood the notification center. Outbound persisted `sent`, `delivered`, `failed` and `bounced` transitions notify the sending user, falling back to the mailbox owner. A recognized incoming mailer-daemon/postmaster delivery-failure message also generates a delivery-failure alert.

SMTP acceptance means the message was accepted for sending; it does not prove recipient delivery. Delivery/bounce transitions require the mail provider or existing delivery integration to persist those results. The app cannot infer a successful delivery receipt from SMTP acceptance alone. A received bounce email is still surfaced when there is no structured provider callback. Repeated unchanged saves and body hydration do not repeat notifications. Account notification preferences remain respected.

Friend requests/acceptance and meeting invitations continue through the shared database-notification push listener. Friend rejection/cancellation/removal also invalidate both users' live directory views. Live meeting call invitations use the existing call push path; scheduled meeting invitations use ordinary alerts.

Keep the repository scheduler and queue worker running. Email sync and scheduled sends must execute while every client is closed. Use the repository's configured scheduler/worker deployment, including `mailtrixy:sync-emails` and `emails:send-scheduled`; browser polling is not a substitute for these server processes.

## Android and iOS

Build the Flutter app from the committed lockfile (Flutter 3.44+/Dart 3.12+).

- Firebase service-account JSON must be outside the web root and configured as `FIREBASE_CREDENTIALS`. Enable FCM HTTP v1. Android/iOS Firebase application identifiers must match the signed app.
- Android incoming calls use high-priority **data-only** pushes and `flutter_callkit_incoming` ConnectionService. A `notification` block must not be added to Android call messages. Accept/Decline actions and cold-start replay retain the call identity and expiry.
- Non-call pushes use an OS-visible notification payload plus data; this gives system-tray alerts in background/terminated states without depending on a Dart timer. Foreground and data-only general alerts use the local notification path, with category preferences and duplicate suppression.
- Push registration retries after transient errors, after app resume, and when FCM/VoIP tokens change. A VoIP token arriving during a registration causes a follow-up registration.
- Android 13+ notification permission and Android 14+ full-screen intent access must be granted. Unlocked phones may show a heads-up call notification instead of taking over the whole screen. Explicit Android **Force stop** prevents delivery until reopening; OEM restrictions and user settings can also affect delivery.
- iOS needs the APNs key in Firebase for ordinary alerts, plus `APNS_KEY_PATH`, `APNS_KEY_ID`, `APNS_TEAM_ID`, and `APNS_BUNDLE_ID` for PushKit calls. Enable Push Notifications and the existing VoIP/remote-notification background modes in the signed provisioning profile. CallKit audio activation is forwarded to WebRTC.

## Acceptance

Use two real accounts and signed Android/iOS devices. Check foreground/background/locked/swipe-away calls, native accept/decline, expired calls, calls while another app is open, fresh login while offline followed by reconnect, and token rotation. Check incoming mail, failed sends, a provider bounce, friend request/accept/reject, meeting invitation and chat alerts in every app state. With two web sessions, verify list previews, unread decreases, request badges, open notification-center contents, and updates after reconnect without reloading. Confirm that a non-member cannot authorize a workspace channel.

The sandbox tests simulate providers and native method channels. They do not certify production FCM/APNs transport, device notification permissions, iOS compilation, or physical audio routing.


## When the phone says the website could not reach Firebase

This is a server-side check failure; changing the Android overlay permission cannot repair it. Older builds used this message for every exception, including rejected Google credentials and local cache errors. The updated server separates these failures and continues delivery if only the token cache is unavailable.

After uploading the updated website files, run from the Laravel application directory:

```sh
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan push:status --user=123 --probe
```

Replace `123` with the affected signed-in user's ID. Run with the same PHP version and environment as the website. The probe prints no device tokens or private keys. It validates the selected user's registrations **without sending an alert**, so a successful probe alone will not update “Last push handled by the app.” Check a real call/message afterwards.

The output identifies the failing stage (`authentication` or `delivery`) and a repair hint:

- `provider_dns_error`: repair hosting DNS for `oauth2.googleapis.com` and `fcm.googleapis.com`.
- `provider_connection_refused` / `provider_timeout`: ask the host to allow outbound HTTPS on port 443 to both hosts, and check proxy and IPv4/IPv6 routing.
- `provider_tls_error`: update the server CA trust bundle and the active PHP `curl.cainfo` / `openssl.cafile` settings. Keep TLS certificate verification enabled.
- `authentication_rejected`: check that the service-account key is active, its account is enabled, and the server clock is correct. Use the matching Firebase project's key; do not share the JSON file.
- `PERMISSION_DENIED`: verify Firebase Cloud Messaging API (HTTP v1) is enabled and the service account has permission to send messages in that project.
- `push_server_error`: inspect the `FCM operation failed` log entry and install the committed Composer dependencies. The entry includes only stage, safe error code and exception class.

If the token cache is broken, logs identify `token_cache_read`, `token_cache_write` or `token_cache_forget`. Repair the configured cache store; the push path now obtains a fresh Google token without requiring a working cache.

After a successful probe, place a call while the receiver uses another app, then repeat with the phone locked and with the app swiped away. A phone explicitly force-stopped through Android Settings must be reopened first. The app update provides the same specific error explanations in Settings → Notifications; the server CLI can diagnose the problem before rebuilding the app.

## Phone notification update: 1.0.3+4

Deploy the server files and rebuild/install Flutter **1.0.3+4**. Uploading PHP files alone cannot add the Android receiver or notification actions to an already installed app. Preserve production `.env`, Firebase credentials, `google-services.json`, and signing keys. Build the release with the same application ID and signing key as the installed app. After installing, open Dahimail and sign in once so the new version and FCM token are registered. Older Android versions keep the previous visible-push format until they upgrade.

Android incoming calls are posted by `NativePushReceiver` immediately on FCM receipt, before a Flutter engine starts. In 1.0.3+4 the full-screen intent opens a dedicated native `IncomingCallActivity`, showing the brand, caller name/avatar, call type and red Decline/green Answer controls. It does not wait for the Flutter splash screen, login gate or call poll to display the incoming call. They use the high-importance Incoming calls channel with Accept/Decline, a full-screen intent, ringtone, badge and lights where supported. Stale invitations and invitations cancelled before arrival cannot ring. Caller cancellation closes the native screen immediately. Decline dismisses ringing locally and sends a callee-authorized request through the background callback; Answer opens the app to join the call. Call alerts expire at their original deadline; moving an already-ringing app into the background retains that deadline. The receiver passes the message to FlutterFire with a marker to suppress duplicate alerts. A foreground service alone does not count as an open app.

New Android clients receive data-only message alerts, rendered natively with the brand icon, category, full sender name, body preview, optional sender avatar, View and inline Reply. These are the phone system notifications, including the heads-up banner and notification shade. Android/iOS determine the final system layout, lock-screen privacy, heads-up duration, badges and LED behavior; an app cannot force identical placement across phone manufacturers. iOS 1.0.2+3 registers View/Reply categories and the background action plugin callback. Its remote action envelope routes through flutter_local_notifications without a second Firebase navigation. iOS native compilation/device checks require a macOS host.

Reply is available only for incoming chat/email messages. It uses the signed-in recipient's stored token and fixed authorized API endpoints. Missed calls and delivery/status events have View only. Failed or uncertain sends keep the draft and show “Reply not confirmed”; open the conversation before retrying. Notifications for a different signed-in account cannot send a reply. Incoming email previews include subject and available body text; header-only sync notifications show the subject until message content is available. Email senders without a supplied profile picture use the app's avatar fallback.

Unfriending retains existing messages and conversation access for both users, including teammates. Sending is blocked until friendship is accepted again. Re-requesting, declining or cancelling a renewed request does not erase the former-friend history marker. The app shows its existing “no longer friends” read-only composer notice. Call/system entries never display message-delivery ticks.

### Deployment and phone verification

1. Merge the `dahimail_website/` files into the Laravel project root (for this server: `/home/dahimail.com/public_html`). Run `php artisan optimize:clear` and restart existing queue workers with `php artisan queue:restart`.
2. Merge `flutter_app/` into the Flutter source project. Run `flutter pub get`, then build a signed release APK/App Bundle using the existing project configuration and signing key. Install the updated app; do not upload Flutter source into the web document root.
3. Open Device notifications in the app. Allow notifications, the Incoming calls channel, and “Incoming calls on lock screen”/full-screen notifications on Android 14+. Enable heads-up/lock-screen display in the phone's channel settings. Some manufacturers also require allowing background/autostart activity.
4. With two accounts, test an incoming audio/video call while the recipient app is foregrounded, backgrounded, swiped away, and while the phone is locked. Confirm Accept joins, Decline rejects, and caller cancellation removes the alert. Test switching away during ringing. Force-stop in Android Settings suppresses FCM until the app is reopened; it is different from swiping away.
5. Send a chat and email. Check category/name/preview/avatar; use View and inline Reply. Unfriend and confirm both users can still read history but cannot send, including from an older notification's Reply button. Confirm missed calls have no ticks or Reply button.

Local automated tests and APK compilation do not prove delivery on a physical phone. Real FCM receipt, manufacturer restrictions, lock-screen presentation and iOS PushKit/APNs credentials must be checked on the deployed app. iPhone closed-app call screens still require the existing APNs VoIP configuration; Firebase credentials alone do not provide PushKit calls.

### System behavior for 1.0.3+4

- While another app is open: Android's high-priority incoming-call banner has Answer/Decline; tapping it opens the native incoming-call screen. Android controls whether a full-screen intent interrupts an unlocked screen.
- Phone locked or screen off: Android can wake and display the dedicated full-screen call Activity when full-screen call notifications are allowed. Without that permission it presents the permitted notification UI.
- App process stopped by the OS or swiped away: FCM can trigger the native receiver and call screen without opening the Flutter UI first. Android Settings → Force stop, disabled notifications, DND rules or manufacturer background restrictions can prevent this.
- Message/email notifications: high-importance system channels, brand icon, category, sender name, content preview, optional sender avatar, View and Reply. Android 6/7 now receive explicit sound/vibration defaults as well as high priority; channel settings govern newer Android versions.
- The incidental in-app notification tile/notification-center redesign from the 1.0.2 ZIP has been restored to its original UI. The new source ZIP includes those restored files so an earlier installation of that ZIP can be corrected.

Test with the recipient phone locked, while another app is visible, and after swiping Dahimail away. Record the installed app version, phone model and Android version if a banner or call screen is still missing. A Firebase provider probe only verifies server acceptance; it does not prove that the phone displayed an alert.

### Android delivery evidence and minimized-chat receipts (1.0.4+5)

Mobile conversation GET requests no longer mark messages read. The rebuilt app sends a bounded `through_message_id` acknowledgement only after rendering the active conversation while resumed and unlocked. Switching apps or covering the route invalidates pending display callbacks and stale fetch responses. Friend acknowledgements cannot include another conversation, private records, cleared history, or messages arriving after the displayed snapshot. Inbox acknowledgements leave the conversation unread if a newer message exists. Existing web behavior and explicit manual mark-as-read remain available. Deploy both PHP and the rebuilt app: older mobile builds do not send the new friend read acknowledgement.

`push:status --probe` is provider validation only. To send an actual harmless test notification to user 14's registered devices:

```bash
php artisan push:status --user=14 --test
```

Use the recipient's database user ID. The options `--test` and `--probe` are mutually exclusive, and both require `--user`. The test contains no private message content. Firebase acceptance does not prove phone receipt or that a banner was shown.

Install/open 1.0.4+5 and sign in, then minimize it and run the test. Reopen Settings → Device notifications → Check connection and copy the Android delivery summary. It distinguishes no native receipt, foreground receipt, notification permission/channel blocking, Dahimail mute/quiet hours, expired calls and successful posting to Android. It also reports channel importance: a channel below High cannot produce normal heads-up banners. Check full-screen intent permission separately in that page. Send this summary with the command output, installed version, phone model/Android version and whether the app was swiped away or force-stopped. Do not send credentials or device tokens.

Actual locked/background/swiped-away push delivery and call presentation still require physical-phone verification. Force stop in Android Settings suspends delivery until the app is opened again. A source ZIP or a PHP deployment alone cannot update the installed app.

### Notification layout and streamlined settings (1.0.5+6)

Phone alerts now use category → sender full name → content. Android uses a decorated native notification with the Dahimail logo on the left, a small category heading, sender and message underneath, and avatar on the right. The expanded alert keeps View and Reply for supported conversations. Friend events say “Sent you a friend request” or “Accepted your friend request.” iOS uses its native app icon, title/category, subtitle/sender and body; actions are available through the system notification. Font sizes, full-screen versus heads-up presentation and lock-screen privacy remain controlled by Android/iOS.

In 1.0.5+6, Device notifications was reduced to Background battery settings (superseded by 1.0.7+8 below). Removed connection diagnostics, local test button, sound/category toggles and quiet-hours controls. Retired local preferences are cleared on app startup, so hidden toggles cannot mute new alerts; OS notification permission, channels, Focus/DND and per-account server preferences still apply. Android opens battery settings; iOS shows directions to Battery in system Settings because iOS provides no public battery-settings deep link. Required OS permissions remain available under Permissions and during sign-in.

Incoming calls prioritize registrations updated most recently so old device requests are less likely to consume the ringing window. Native iOS PushKit delivery no longer waits for Firebase authentication. iOS still requires valid APNS_* configuration, PushKit registration and a correctly signed app; Firebase alone cannot wake an iPhone into CallKit.

The supplied Xiaomi Android12 screenshot showed a local test banner, missing/invalid Firebase credentials, no remote push receipt and no incoming-call channel. A local banner does not establish remote call delivery. After server deployment, an administrator must verify that FIREBASE_CREDENTIALS points to a valid JSON file readable by the PHP web process (keep it outside public_html), clear stale Laravel config, and run:

```bash
php artisan optimize:clear
php artisan queue:restart
php artisan push:status --user=14 --probe
php artisan push:status --user=14 --test
```

Use the recipient's actual user ID. The test is a harmless message notification, not a synthetic call. Then place a real call from another account while the recipient phone is locked and while another app is open. Check Xiaomi Autostart, battery/background activity and lock-screen notification permissions if Firebase accepts delivery but the phone remains silent. No physical Android/iOS call delivery has been verified in this sandbox. The 1.0.4 instructions to copy diagnostics from Device notifications are superseded by this simplified page; troubleshooting is administrator work.

### Native Firebase service for background Android calls (1.0.6+7)

Data-only delivery now enters `NativeMessagingService.onMessageReceived`, the Android Firebase service callback. It posts the native incoming-call alert before passing the message to Flutter. The former FlutterFire service callback intentionally did nothing and relied on a separate broadcast receiver; this update makes the service responsible for calls and v2 message alerts. The broadcast receiver is retained only for legacy notification-payload tap bookkeeping, avoiding duplicate data processing. Firebase token-refresh handling is inherited from FlutterFire.

A failing Flutter handoff no longer aborts delivery after native posting. Repeated call pushes do not extend an existing ringing deadline. Cancellation marks the call closed before Flutter starts, so a delayed invitation cannot ring after cancellation. The native caller screen still uses Android's high-importance full-screen call notification; a locked phone may open the full screen, while an unlocked phone normally receives an actionable call banner. DND, notification channels, Android14+ full-screen permission and manufacturer restrictions remain enforced by the phone.

Rebuild and install **1.0.6+7** with the existing Firebase project, application ID and signing key. This change cannot reach a phone through a PHP-only deployment. Open/sign in once after installing, then verify a real call while minimized, after swiping the app from Recents, while another app is open and with the screen locked. For the reported Xiaomi device, allow Autostart, background battery activity and lock-screen/background pop-up notifications in system app settings. Android Settings → Force stop puts the app in a stopped state and blocks FCM until it is manually reopened; no app-side change can guarantee incoming calls in that state.

The server must still accept the recipient's current FCM token and send an incoming call before it expires. A local notification test proves neither server delivery nor a working call push. Use the existing administrator `push:status --user=<recipient-id> --test` for actual remote notification delivery, then place a real call. Never infer phone receipt from Firebase acceptance alone.

### All Device notifications options restored (1.0.7+8)

Profile settings → Device notifications again includes connection status and Check connection, last remote push receipt, notification permission recovery, notification enable/category controls, sound selection and preview, vibration, sound while the app is open, quiet hours and From/Until times, and the local notification display test. Android also shows native delivery details and lock-screen incoming-call permission. Background battery settings remains on both platforms, with iPhone system Settings guidance.

Saved device preferences now survive app startup. Android's native message/email/activity path observes the restored enable/category/quiet-hours/sound/vibration controls while preserving the decorated banners and View/Reply actions. Incoming calls retain their urgent native path; the message alert controls and quiet hours do not silence ringing calls. OS permissions, notification channels and Focus/DND still apply. iOS remote alerts are presented by the OS using the server payload; device controls apply to alerts generated by the app. Settings erased by an earlier version cannot be recovered and start with defaults.

The local display test does not verify Firebase delivery. Check connection validates server/provider registration, and the last-push timestamp and Android diagnostics provide receipt evidence. Verify real message and call delivery from another account after rebuilding and installing 1.0.7+8. This restoration supersedes the earlier instruction that Device notifications diagnostics were removed.

## Mobile signaling and actionable-notification update

See `MOBILE_REALTIME_UPDATE.md` for the 1.0.3+4 deployment and device matrix.
Mobile `/api/v1/realtime/config` and `/api/v1/realtime/auth` require Sanctum;
auth signs only `private-user.<current user>`. SDP/ICE events are sent only to
the authorized target participant, after the signal is stored. Events larger
than 9 KB omit the inline signal and trigger REST replay. Termination broadcasts
wait for database commit but do not enter a background job queue. Polling is
kept for heartbeat, guests, reconnect replay and deployments without broadcasts.
The standalone website meeting page now loads the realtime client too.

### Test push arrives but normal events do not

The connection test uses the Activity category; chats, emails and friend events use New messages. Enable New messages in app notification settings and check its Android channel settings. After a second account sends a chat while the recipient app is in the background, inspect Phone delivery details before sending another test. `muted_in_app`, `quiet_hours` and `channel_blocked` identify local suppression; an unchanged last receipt requires investigating the deployed event producer and recipient account/device registration.

AppServiceProvider now checks listener registration after every provider has booted. It adds SendPushForNotification only when discovery or the deployed event cache omitted it. This supports older cached event manifests without sending database alerts twice. Upload the website PHP files as well as installing the previously rebuilt app. After deploying, run `php artisan event:clear` (or rebuild with `php artisan event:cache` as part of deployment), then restart long-running workers with `php artisan queue:restart`. This fallback covers database notifications; chat uses FriendChatService and FriendPush independently. Successful local tests cannot confirm which path is failing on the deployed server.
