package com.dahify.dahimail

import android.widget.RemoteViews
import android.app.KeyguardManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.BitmapShader
import android.graphics.Shader
import android.graphics.Matrix
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import androidx.core.app.NotificationManagerCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.RemoteInput
import androidx.core.app.Person
import androidx.core.graphics.drawable.IconCompat
import com.dexterous.flutterlocalnotifications.ActionBroadcastReceiver
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingReceiver
import com.google.firebase.messaging.RemoteMessage
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.Calendar
import java.io.ByteArrayOutputStream
import java.util.concurrent.Executors

/** Native posting runs before Flutter. FCM's service owns data-message delivery. */
open class NativePushReceiver : FlutterFirebaseMessagingReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        // Preserve FlutterFire's legacy notification tap bookkeeping. Data-only
        // calls/messages are handled by NativeMessagingService, exactly once.
        val extras = intent.extras ?: return
        if (RemoteMessage(extras).notification != null) {
            if (extras.getString("type") in listOf("call", "call_cancel")) handleMessage(context, intent)
            else forwardToFlutter(context, intent)
        }
    }

    internal fun handleMessage(context: Context, intent: Intent) {
        val extras = intent.extras ?: return
        val data = extras.keySet().associateWith { extras.get(it)?.toString().orEmpty() }
        val type = data["type"]
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit()
            .putString("last_type", type ?: "unknown").putLong("last_received", System.currentTimeMillis()).apply()
        val id = data["call_id"]?.toIntOrNull()
        if (type == "call_cancel" && id != null) {
            // A delayed invitation cannot ring after its cancellation was seen.
            dismissCall(context, id)
            recordResult(context, "call_cancelled")
        }
        val foreground = isAppVisible(context)
        if (foreground) recordResult(context, "foreground")
        if (!foreground && type != "call_cancel" && (type == "call" || !data["title"].isNullOrBlank())) {
            try {
                val shown = if (type == "call") showCall(context, data) else showAlert(context, data)
                if (shown) intent.putExtra("dm_native_shown", "1")
            } catch (_: Exception) {
                recordResult(context, "posting_failed")
                // Leave delivery to the existing Dart fallback if native posting fails.
            }
        }
        try {
            forwardToFlutter(context, intent)
        } catch (error: Exception) {
            // A Flutter startup failure must not crash the FCM delivery service
            // or remove the native alert already posted above.
            android.util.Log.w("DahimailPush", "Flutter handoff failed: ${error.javaClass.simpleName}")
        }
    }

    protected open fun forwardToFlutter(context: Context, intent: Intent) {
        super.onReceive(context, intent)
    }

    companion object {
        private val images = Executors.newSingleThreadExecutor()
        const val CALL_CLOSED = "com.dahify.dahimail.CALL_CLOSED"
        private const val CALL_CHANNEL = "dm_calls_v7"
        private const val ALERT_CHANNEL = "dm_alerts_v5"

        internal fun isAppVisible(context: Context): Boolean =
            MainActivity.isResumed &&
                context.getSystemService(PowerManager::class.java).isInteractive &&
                !context.getSystemService(KeyguardManager::class.java).isKeyguardLocked

        private fun recordResult(context: Context, result: String) {
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putString("last_result", result).apply()
        }

        @Suppress("DEPRECATION")
        private fun wakeDisplay(context: Context, tag: String) {
            val power = context.getSystemService(PowerManager::class.java) ?: return
            if (power.isInteractive) return
            val wake = power.newWakeLock(PowerManager.SCREEN_BRIGHT_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP, "Dahimail:$tag")
            wake.setReferenceCounted(false)
            wake.acquire(3500)
        }

        private fun canPost(context: Context, channelId: String): Boolean {
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putString("last_channel", channelId).apply()
            if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) {
                recordResult(context, "notifications_blocked"); return false
            }
            if (Build.VERSION.SDK_INT >= 26 && context.getSystemService(NotificationManager::class.java).getNotificationChannel(channelId)?.importance == NotificationManager.IMPORTANCE_NONE) {
                recordResult(context, "channel_blocked"); return false
            }
            return true
        }

        internal fun diagnostics(context: Context): Map<String, Any> {
            val prefs = context.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            val manager = context.getSystemService(NotificationManager::class.java)
            val channelId = prefs.getString("last_channel", "") ?: ""
            return mapOf("device" to "${Build.MANUFACTURER} ${Build.MODEL} / Android ${Build.VERSION.RELEASE}", "enabled" to NotificationManagerCompat.from(context).areNotificationsEnabled(),
                "last_type" to (prefs.getString("last_type", "none") ?: "none"),
                "last_result" to (prefs.getString("last_result", "not_received") ?: "not_received"),
                "channel_importance" to (if (Build.VERSION.SDK_INT >= 26) manager.getNotificationChannel(channelId)?.importance ?: -1 else -1),
                "call_importance" to (if (Build.VERSION.SDK_INT >= 26) manager.getNotificationChannel(CALL_CHANNEL)?.importance ?: -1 else -1))
        }

        internal fun callExpiry(context: Context, id: Int): Long =
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).getLong("ringing_$id", 0)

        internal fun isMinimized(context: Context, id: Int): Boolean =
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).getBoolean("minimized_$id", false)

        internal fun setMinimized(context: Context, id: Int, minimized: Boolean) {
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putBoolean("minimized_$id", minimized).apply()
        }

        @Synchronized internal fun minimizeCall(context: Context, data: Map<String, String>) {
            val id = data["call_id"]?.toIntOrNull() ?: return
            if (callExpiry(context, id) <= System.currentTimeMillis()) return
            setMinimized(context, id, true)
            try {
                val manager = context.getSystemService(NotificationManager::class.java)
                manager.activeNotifications.firstOrNull { it.id == 100000 + id }?.notification?.let {
                    // A later avatar update or queued full-screen intent must not reopen the screen.
                    val notification = if (Build.VERSION.SDK_INT >= 24) {
                        val builder = android.app.Notification.Builder.recoverBuilder(context, it)
                            .setFullScreenIntent(null, false).setOnlyAlertOnce(true)
                        if (Build.VERSION.SDK_INT >= 26) {
                            builder.setTimeoutAfter((callExpiry(context, id) - System.currentTimeMillis()).coerceAtLeast(1))
                        }
                        builder.build()
                    } else it.apply {
                        fullScreenIntent = null
                        flags = flags or android.app.Notification.FLAG_ONLY_ALERT_ONCE
                    }
                    manager.notify(100000 + id, notification)
                }
            } catch (error: RuntimeException) {
                android.util.Log.w("DahimailPush", "Call notification update failed: ${error.javaClass.simpleName}")
            }
            IncomingCallOverlay.hide(id)
        }

        @Synchronized internal fun dismissCall(context: Context, id: Int) {
            context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit()
                .remove("ringing_$id").remove("minimized_$id").putLong("cancelled_$id", System.currentTimeMillis()).apply()
            DeviceSounds.stopIncoming(id)
            IncomingCallService.stop(context, id)
            IncomingCallOverlay.hide(id)
            context.getSystemService(NotificationManager::class.java).cancel(100000 + id)
            context.sendBroadcast(Intent(CALL_CLOSED).setPackage(context.packageName).putExtra("call_id", id))
        }

        internal fun callScreenIntent(context: Context, data: Map<String, String>): Intent =
            Intent(context, IncomingCallActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP
                this.data = Uri.parse("dahimail://incoming/${data["call_id"]}")
                putExtra("call_data", JSONObject(data).toString())
            }

        internal fun answerActivityIntent(context: Context, id: Int, callData: Map<String, String> = emptyMap()): Intent =
            Intent(context, MainActivity::class.java).apply {
                putExtra("call_data", JSONObject(callData + ("call_id" to id.toString())).toString())
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP
                action = "SELECT_FOREGROUND_NOTIFICATION"
                data = Uri.parse("dahimail://notification/$id/call_accept")
                putExtra("notificationId", 100000 + id)
                putExtra("payload", "call_incoming:$id")
                putExtra("actionId", "call_accept")
                putExtra("cancelNotification", true)
            }

        internal fun declineIntent(context: Context, data: Map<String, String>): PendingIntent {
            val id = data["call_id"]!!.toInt()
            val intent = Intent(context, IncomingCallDeclineReceiver::class.java).apply {
                action = ActionBroadcastReceiver.ACTION_TAPPED
                this.data = Uri.parse("dahimail://incoming/$id/decline")
                putExtra("notificationId", 100000 + id)
                putExtra("payload", JSONObject(data).toString())
                putExtra("actionId", "call_decline_background")
                putExtra("cancelNotification", true)
            }
            return PendingIntent.getBroadcast(context, id, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        }

        private fun launch(context: Context, id: Int, payload: String, action: String? = null): PendingIntent {
            val intent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP
                this.action = if (action == null) "SELECT_NOTIFICATION" else "SELECT_FOREGROUND_NOTIFICATION"
                data = Uri.parse("dahimail://notification/$id/${action ?: "view"}")
                putExtra("notificationId", id)
                putExtra("payload", payload)
                putExtra("actionId", action)
                putExtra("cancelNotification", true)
            }
            return PendingIntent.getActivity(context, id, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        }

        // System actions keep View and RemoteInput Reply inside the notification.
        // Only Reply is mutable, so Android can attach the user's typed input.
        private fun messageAction(context: Context, id: Int, payload: String, actionId: String, input: Boolean = false): PendingIntent {
            val intent = Intent(context, ActionBroadcastReceiver::class.java).apply {
                action = ActionBroadcastReceiver.ACTION_TAPPED
                data = Uri.parse("dahimail://notification/$id/$actionId")
                putExtra("notificationId", id)
                putExtra("payload", payload)
                putExtra("actionId", actionId)
                putExtra("cancelNotification", false)
            }
            val mutable = if (input) {
                if (Build.VERSION.SDK_INT >= 31) PendingIntent.FLAG_MUTABLE else 0
            } else PendingIntent.FLAG_IMMUTABLE
            return PendingIntent.getBroadcast(context, id, intent, PendingIntent.FLAG_UPDATE_CURRENT or mutable)
        }

        private fun channel(context: Context, calls: Boolean, alertId: String = ALERT_CHANNEL, vibrate: Boolean = true, playSound: Boolean = true) {
            if (Build.VERSION.SDK_INT < 26) return
            val id = if (calls && alertId == ALERT_CHANNEL) CALL_CHANNEL else alertId
            val manager = context.getSystemService(NotificationManager::class.java)
            if (manager.getNotificationChannel(id) != null) return
            val channel = NotificationChannel(id, if (calls) "Incoming calls" else "Messages & updates", NotificationManager.IMPORTANCE_HIGH).apply {
                enableVibration(if (calls) true else vibrate)
                enableLights(true)
                lightColor = Color.rgb(22, 163, 74)
                setShowBadge(true)
                lockscreenVisibility = if (calls) NotificationCompat.VISIBILITY_PUBLIC else NotificationCompat.VISIBILITY_PRIVATE
                setSound(if (!playSound || (calls && id == CALL_CHANNEL)) null else android.media.RingtoneManager.getDefaultUri(if (calls) android.media.RingtoneManager.TYPE_RINGTONE else android.media.RingtoneManager.TYPE_NOTIFICATION),
                    AudioAttributes.Builder().setUsage(if (calls) AudioAttributes.USAGE_NOTIFICATION_RINGTONE else AudioAttributes.USAGE_NOTIFICATION).build())
            }
            manager.createNotificationChannel(channel)
        }

        /** Firebase may allow service creation but reject its later foreground promotion. */
        @Synchronized internal fun useSystemRingtone(context: Context, callId: Int) {
            val remaining = callExpiry(context, callId) - System.currentTimeMillis()
            if (remaining <= 0) return
            val manager = context.getSystemService(NotificationManager::class.java)
            val current = manager.activeNotifications.firstOrNull { it.id == 100000 + callId }?.notification ?: return
            val fallbackChannel = "dm_calls_fallback_v1"
            channel(context, true, fallbackChannel)
            // Respect both the original call channel and the fallback channel settings.
            if (!canPost(context, CALL_CHANNEL) || !canPost(context, fallbackChannel)) return
            val builder = NotificationCompat.Builder(context, current)
                .setOnlyAlertOnce(false) // The previous post used a silent channel.
                .setChannelId(fallbackChannel)
                .setSound(android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_RINGTONE))
                .setTimeoutAfter(remaining.coerceAtMost(45000))
            if (isMinimized(context, callId)) builder.setFullScreenIntent(null, false)
            manager.notify(100000 + callId, builder.build().apply {
                flags = flags or android.app.Notification.FLAG_INSISTENT
            })
            DeviceSounds.stopIncoming(callId)
            recordResult(context, "posted_call_system_ringtone")
        }

        @Synchronized internal fun showCall(context: Context, data: Map<String, String>): Boolean {
            val callId = data["call_id"]?.toIntOrNull() ?: return false
            val sent = data["sent_at"]?.toLongOrNull() ?: return false
            val postedAt = System.currentTimeMillis()
            val originalExpiry = callExpiry(context, callId)
            val deadline = (sent + (data["ttl"]?.toLongOrNull() ?: 45)) * 1000
            val remaining = (if (originalExpiry > 0) minOf(originalExpiry, deadline) else deadline) - postedAt
            val preferences = context.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            if (remaining <= 0 || preferences.getLong("cancelled_$callId", 0) >= sent * 1000) { recordResult(context, "call_expired_or_cancelled"); return true }
            // Bound retained cancellation markers to the short call delivery window.
            preferences.all.filter { it.key.startsWith("cancelled_") && (it.value as? Long ?: 0) < System.currentTimeMillis() - 120000 }
                .keys.forEach { preferences.edit().remove(it).apply() }
            // A Flutter pause caused by opening the native screen also sends a
            // minimized handoff. It must not collapse a call already presented by
            // Android. Only the native screen's explicit button handlers do that.
            // Redelivery/handoff must not restart ringing or launch a second screen.
            if (callExpiry(context, callId) > postedAt && context.getSystemService(NotificationManager::class.java)
                    .activeNotifications.any { it.id == 100000 + callId }) return true
            channel(context, true)
            if (!canPost(context, CALL_CHANNEL)) return true
            val id = 100000 + callId
            val launchOptions = if (Build.VERSION.SDK_INT >= 35) android.app.ActivityOptions.makeBasic().apply {
                setPendingIntentCreatorBackgroundActivityStartMode(android.app.ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED)
            }.toBundle() else null
            val open = PendingIntent.getActivity(context, id, callScreenIntent(context, data).putExtra("user_open", true).setAction("open_call"), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE, launchOptions)
            val fullScreen = PendingIntent.getActivity(context, id, callScreenIntent(context, data), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE, launchOptions)
            // A Flutter pause can mean the phone just locked, not that the user
            // dismissed the incoming call. Always retain the lock-screen intent.
            val locked = context.getSystemService(KeyguardManager::class.java).isKeyguardLocked ||
                !context.getSystemService(PowerManager::class.java).isInteractive
            val minimized = (data["minimized"] == "true" && !locked) || isMinimized(context, callId)
            val accept = PendingIntent.getActivity(context, id, answerActivityIntent(context, callId, data), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
            val decline = declineIntent(context, data)
            val name = data["caller_name"].orEmpty().ifBlank { "Someone" }
            val video = data["video"] in listOf("1", "true") && data["audio_only"] !in listOf("1", "true")
            val label = if (data["group"] in listOf("1", "true")) "Meeting invitation" else if (video) "Incoming video call" else "Incoming audio call"
            val builder = NotificationCompat.Builder(context, CALL_CHANNEL)
                .setLargeIcon(avatarPlaceholder(name))
                .setSmallIcon(R.drawable.ic_stat_notify).setContentTitle(name).setContentText(label).setSubText("Call")
                .setPriority(NotificationCompat.PRIORITY_MAX).setCategory(NotificationCompat.CATEGORY_CALL)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC).setOngoing(true).setAutoCancel(false)
                .setTimeoutAfter(remaining.coerceAtMost(45000)).setFullScreenIntent(if (minimized) null else fullScreen, true).setContentIntent(open)
                .setSound(null)
                .setDefaults(NotificationCompat.DEFAULT_VIBRATE or NotificationCompat.DEFAULT_LIGHTS)
            if (Build.VERSION.SDK_INT >= 31) {
                builder.setStyle(NotificationCompat.CallStyle.forIncomingCall(
                    Person.Builder().setName(name).setIcon(IconCompat.createWithBitmap(avatarPlaceholder(name))).setImportant(true).build(), decline, accept).setIsVideo(video))
            } else {
                builder.addAction(0, "Decline", decline).addAction(0, "Accept", accept)
            }
            val notification = builder.build().apply { flags = flags or android.app.Notification.FLAG_INSISTENT }
            val manager = context.getSystemService(NotificationManager::class.java)
            preferences.edit().putLong("ringing_$callId", postedAt + remaining.coerceAtMost(45000)).apply()
            // Let Android evaluate the sleeping/locked state before waking the display.
            // IncomingCallActivity uses setTurnScreenOn + setShowWhenLocked. A wake
            // lock here can turn a screen-off full-screen call into a heads-up banner.
            wakeDisplay(context, "incoming-call")
            manager.notify(id, notification)
            // System UI normally launches the full-screen intent itself. On OEMs that
            // suppress that launch while the process is cold, perform one bounded,
            // permission-checked retry so a locked phone still wakes for the call.
            if (!minimized) IncomingCallWakeup.schedule(context, callId)
            recordResult(context, "posted_call")
            if (minimized) minimizeCall(context, data)
            // Own ringtone lifetime independently of Activity/notification focus.
            DeviceSounds.startIncoming(context, callId, callExpiry(context, callId))
            if (!IncomingCallService.present(context, data)) {
                useSystemRingtone(context, callId)
            }
            val avatar = data["caller_avatar"].orEmpty()
            if (avatar.startsWith("https://")) images.execute {
                val bitmap = fetchAvatar(avatar) ?: return@execute
                // Serialize image updates with cancellation and ringtone fallback.
                synchronized(this) {
                    val timeout = remaining - (System.currentTimeMillis() - postedAt)
                    if (timeout <= 0) return@execute
                    val current = manager.activeNotifications.firstOrNull { it.id == id }?.notification ?: return@execute
                    // Foreground service promotion can fail after the initial post.
                    // An avatar must not restore the original silent channel afterwards.
                    if (Build.VERSION.SDK_INT >= 26) builder.setChannelId(current.channelId)
                    else builder.setSound(current.sound)
                    builder.setFullScreenIntent(if (isMinimized(context, callId)) null else fullScreen, true).setLargeIcon(bitmap).setOnlyAlertOnce(true).setTimeoutAfter(timeout.coerceAtMost(45000))
                    if (Build.VERSION.SDK_INT >= 31) builder.setStyle(NotificationCompat.CallStyle.forIncomingCall(
                        Person.Builder().setName(name).setIcon(IconCompat.createWithBitmap(bitmap)).setImportant(true).build(), decline, accept).setIsVideo(video))
                    manager.notify(id, builder.build().apply { flags = flags or android.app.Notification.FLAG_INSISTENT })
                }
            }
            return true
        }

        @Synchronized internal fun showAlert(context: Context, data: Map<String, String>): Boolean {
            if (data["title"].isNullOrBlank()) return false
            val prefs = context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
            val type = data["type"].orEmpty()
            val category = if (Regex("billing|payment|plan|trial").containsMatchIn(type)) "billing"
                else if (Regex("message|conversation|reply|email|chat|inbox|friend|mail").containsMatchIn(type)) "messages" else "activity"
            if (!prefs.getBoolean("flutter.n_enabled", true) || !prefs.getBoolean("flutter.n_$category", true)) { recordResult(context, "muted_in_app"); return true }
            if (prefs.getBoolean("flutter.q_enabled", false)) {
                val now = Calendar.getInstance()
                val minute = now.get(Calendar.HOUR_OF_DAY) * 60 + now.get(Calendar.MINUTE)
                val start = prefs.getLong("flutter.q_start", 1320).toInt()
                val end = prefs.getLong("flutter.q_end", 420).toInt()
                if (if (start <= end) minute in start until end else minute >= start || minute < end) { recordResult(context, "quiet_hours"); return true }
            }
            val sound = if (data["play_sound"] in listOf("false", "0")) "silent" else "default"
            val vibrate = prefs.getBoolean("flutter.n_vibrate", true)
            val channelId = "${ALERT_CHANNEL}_${category}_${sound}_$vibrate"
            channel(context, false, channelId, vibrate, sound == "default")
            if (!canPost(context, channelId)) return true
            val key = data["notification_id"].takeUnless { it.isNullOrBlank() }
                ?: data["message_id"].takeUnless { it.isNullOrBlank() } ?: data["google.message_id"] ?: return false
            val receipts = context.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            val receiptKey = "alert_delivered_$key"
            val cutoff = System.currentTimeMillis() - 24 * 60 * 60 * 1000L
            if (receipts.getLong(receiptKey, 0) > cutoff) return true
            val id = key.hashCode() and 0x7fffffff
            // Polling and older pushes may contain only a local conversation URL
            // or chat sender ID. Match Dart's target rules before exposing Reply.
            val replyTarget = notificationReplyTarget(data)
            val actionData = data + replyTarget
            val payload = JSONObject(actionData).toString()
            val view = launch(context, id, payload)
            val body = when (type) {
                "friend_request" -> "Sent you a friend request"
                "friend_accepted" -> "Accepted your friend request"
                else -> data["body"].orEmpty()
            }
            val sender = data["sender_name"].orEmpty().ifBlank { data["title"].orEmpty() }
            val builder = NotificationCompat.Builder(context, channelId)
                .setSmallIcon(R.drawable.ic_stat_notify)
                .setContentTitle(sender).setContentText(body)
                .setPriority(NotificationCompat.PRIORITY_HIGH).setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
                .setAutoCancel(true).setContentIntent(view).setOnlyAlertOnce(true)
                .setDefaults(NotificationCompat.DEFAULT_LIGHTS or (if (vibrate) NotificationCompat.DEFAULT_VIBRATE else 0) or
                    (if (sound == "default") NotificationCompat.DEFAULT_SOUND else 0))
                .setCategory(if (replyTarget.isNotEmpty()) NotificationCompat.CATEGORY_MESSAGE else NotificationCompat.CATEGORY_EVENT)
            builder.addAction(NotificationCompat.Action.Builder(0, context.getString(R.string.notification_view),
                messageAction(context, id, payload, "notification_view"))
                .setShowsUserInterface(false).build())
            if (replyTarget.isNotEmpty()) {
                builder.addAction(NotificationCompat.Action.Builder(0, context.getString(R.string.notification_reply),
                    messageAction(context, id, payload, "notification_reply", true))
                    .addRemoteInput(RemoteInput.Builder("FlutterLocalNotificationsPluginInputResult")
                        .setLabel(context.getString(R.string.notification_reply)).build())
                    .setSemanticAction(NotificationCompat.Action.SEMANTIC_ACTION_REPLY)
                    .setAllowGeneratedReplies(true).setShowsUserInterface(false).build())
            }
            val postedAt = notificationTime(data)
            fun card(avatar: Bitmap, compact: Boolean): RemoteViews = RemoteViews(context.packageName,
                if (compact) R.layout.notification_message_compact else R.layout.notification_message).apply {
                setTextViewText(R.id.notification_sender, sender)
                setTextViewText(R.id.notification_body, body)
                setTextViewText(R.id.notification_time, relativeTime(postedAt))
                setImageViewResource(R.id.notification_type_icon, notificationIcon(data))
                setImageViewBitmap(R.id.notification_avatar, avatar)
            }
            fun applyCard(avatar: Bitmap) {
                builder.setCustomContentView(card(avatar, true))
                    .setCustomBigContentView(card(avatar, false))
                    .setCustomHeadsUpContentView(card(avatar, false))
            }
            // The system owns the action row/RemoteInput. Our content contains
            // only the brand/category, sender, message, relative time and avatar.
            builder.setStyle(NotificationCompat.DecoratedCustomViewStyle())
                .setWhen(postedAt).setShowWhen(false)
            applyCard(avatarPlaceholder(sender))
            val manager = context.getSystemService(NotificationManager::class.java)
            wakeDisplay(context, "notification")
            manager.notify(id, builder.build())
            receipts.edit().apply {
                receipts.all.filter { it.key.startsWith("alert_delivered_") }
                    .entries.sortedByDescending { (it.value as? Long) ?: 0 }.drop(99)
                    .forEach { remove(it.key) }
                putLong(receiptKey, System.currentTimeMillis())
            }.apply()
            recordResult(context, "posted_alert")
            // Avatar is optional. Never delay the alert or resurrect a dismissed one.
            val avatar = data["sender_avatar"].orEmpty()
            if (avatar.startsWith("https://")) images.execute {
                val bitmap = fetchAvatar(avatar) ?: return@execute
                if (manager.activeNotifications.any { it.id == id && it.notification.`when` == postedAt && it.notification.extras.getCharSequence(android.app.Notification.EXTRA_TEXT)?.toString() == body }) {
                    applyCard(circularAvatar(bitmap))
                    manager.notify(id, builder.build())
                }
            }
            return true
        }

        internal fun notificationReplyTarget(data: Map<String, String>): Map<String, String> {
            val type = data["type"]
            if (type !in listOf("chat", "email_received", "new_email", "contact_reply", "message", "new_conversation")) return emptyMap()
            val kind = data["reply_kind"]
            val id = data["reply_id"]?.toIntOrNull()
            if (id != null && id > 0 && (kind == "conversation" || (kind == "friend" && type == "chat")))
                return mapOf("reply_kind" to kind!!, "reply_id" to id.toString())
            if (type == "chat") {
                val peer = data["from_id"]?.toIntOrNull()
                if (peer != null && peer > 0) return mapOf("reply_kind" to "friend", "reply_id" to peer.toString())
            }
            val url = Uri.parse(data["action_url"].orEmpty())
            if (url.isHierarchical && url.path == "/inbox") {
                val conversation = (url.getQueryParameter("cid") ?: url.getQueryParameter("conversation"))?.toIntOrNull()
                if (conversation != null && conversation > 0) return mapOf("reply_kind" to "conversation", "reply_id" to conversation.toString())
            }
            return emptyMap()
        }

        internal fun notificationTime(data: Map<String, String>, now: Long = System.currentTimeMillis()): Long {
            val raw = data["created_at_iso"] ?: data["created_at"] ?: data["sent_at"] ?: return now
            return try {
                val epoch = raw.toLongOrNull()
                val time = if (epoch != null) {
                    if (epoch < 100000000000L) epoch * 1000 else epoch
                } else java.time.Instant.parse(raw).toEpochMilli()
                time.coerceAtMost(now)
            } catch (_: Exception) { now }
        }

        internal fun relativeTime(time: Long, now: Long = System.currentTimeMillis()): String {
            val seconds = (now - time).coerceAtLeast(0) / 1000
            return when {
                seconds < 60 -> "Just now"
                seconds < 3600 -> "${seconds / 60}m ago"
                seconds < 86400 -> "${seconds / 3600}h ago"
                else -> "${seconds / 86400}d ago"
            }
        }

        private fun circularAvatar(source: Bitmap): Bitmap {
            val size = minOf(source.width, source.height)
            val bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
            val shader = BitmapShader(source, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP)
            shader.setLocalMatrix(Matrix().apply { setTranslate(-(source.width - size) / 2f, -(source.height - size) / 2f) })
            Canvas(bitmap).drawCircle(size / 2f, size / 2f, size / 2f, Paint(Paint.ANTI_ALIAS_FLAG).apply { this.shader = shader })
            return bitmap
        }

        internal fun notificationIcon(data: Map<String, String>): Int {
            val type = data["type"].orEmpty().lowercase()
            val video = data["audio_only"] !in listOf("1", "true") &&
                (data["video"] in listOf("1", "true") || type.contains("video") || data["body"].orEmpty().lowercase().contains("video call"))
            return when {
                type.contains("meeting") || (type.contains("call") && (data["group"] in listOf("1", "true") || data["group_invite"] in listOf("1", "true"))) -> R.drawable.ic_notify_meeting
                type.contains("call") -> if (video) R.drawable.ic_notify_video else R.drawable.ic_notify_call
                type.startsWith("friend") -> R.drawable.ic_notify_friend
                type.contains("email") || type.contains("mail_") -> R.drawable.ic_notify_email
                type == "chat" || type.contains("message") || type.contains("conversation") || type.contains("reply") -> R.drawable.ic_notify_chat
                else -> R.drawable.ic_stat_notify
            }
        }

        internal fun avatarPlaceholder(name: String): Bitmap {
            val bitmap = Bitmap.createBitmap(128, 128, Bitmap.Config.ARGB_8888)
            val canvas = Canvas(bitmap)
            val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.rgb(28, 115, 94) }
            canvas.drawCircle(64f, 64f, 64f, paint)
            val initials = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2).map { it.take(1) }.joinToString("").uppercase().ifBlank { "D" }
            paint.color = Color.WHITE; paint.textSize = 48f; paint.textAlign = Paint.Align.CENTER; paint.typeface = Typeface.DEFAULT_BOLD
            canvas.drawText(initials, 64f, 64f - (paint.ascent() + paint.descent()) / 2, paint)
            return bitmap
        }

        internal fun fetchAvatar(avatar: String): Bitmap? {
            var connection: HttpURLConnection? = null
            try {
                connection = URL(avatar).openConnection() as HttpURLConnection
                connection.connectTimeout = 1500; connection.readTimeout = 1500
                connection.instanceFollowRedirects = false
                if (connection.responseCode != 200 || !connection.contentType.orEmpty().startsWith("image/")) return null
                val bytes = connection.inputStream.use { input ->
                    val out = ByteArrayOutputStream()
                    val buffer = ByteArray(8192)
                    while (out.size() <= 1024 * 1024) {
                        val count = input.read(buffer)
                        if (count < 0) break
                        out.write(buffer, 0, count)
                    }
                    out.toByteArray()
                }
                if (bytes.size > 1024 * 1024) return null
                val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
                val options = BitmapFactory.Options().apply { inSampleSize = maxOf(1, maxOf(bounds.outWidth, bounds.outHeight) / 128) }
                return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, options)
            } catch (_: Exception) { return null }
            finally { connection?.disconnect() }
        }

    }
}
