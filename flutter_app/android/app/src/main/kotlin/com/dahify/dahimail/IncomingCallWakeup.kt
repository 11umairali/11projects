package com.dahify.dahimail

import android.app.ActivityOptions
import android.app.KeyguardManager
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import androidx.core.app.NotificationManagerCompat

/** One bounded recovery attempt when a posted full-screen intent has not opened. */
internal object IncomingCallWakeup {
    private val handler = Handler(Looper.getMainLooper())
    private val pending = mutableMapOf<Int, Runnable>()
    private val locks = mutableMapOf<Int, PowerManager.WakeLock>()

    private fun onMain(action: () -> Unit) {
        if (Looper.myLooper() == Looper.getMainLooper()) action() else handler.post(action)
    }

    fun schedule(context: Context, id: Int) = onMain {
        cancelNow(id)
        val app = context.applicationContext
        val attempt = Runnable {
            pending.remove(id)
            retry(app, id)
        }
        pending[id] = attempt
        // Give System UI the first opportunity, while the display is still off.
        handler.postDelayed(attempt, 750)
    }

    @Suppress("DEPRECATION")
    private fun retry(context: Context, id: Int) {
        if (IncomingCallActivity.visibleCallId == id) return
        if (NativePushReceiver.isMinimized(context, id)) { record(context, "call_minimized"); return }
        if (NativePushReceiver.callExpiry(context, id) <= System.currentTimeMillis()) { record(context, "call_expired"); return }
        val power = context.getSystemService(PowerManager::class.java)
        val keyguard = context.getSystemService(KeyguardManager::class.java)
        if (power.isInteractive && !keyguard.isKeyguardLocked) { record(context, "phone_unlocked"); return }
        val manager = context.getSystemService(NotificationManager::class.java)
        if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) { record(context, "notifications_blocked"); return }
        if (Build.VERSION.SDK_INT >= 34 && !manager.canUseFullScreenIntent()) {
            record(context, "full_screen_access_denied")
            return
        }
        // A recovery attempt must not override DND or a lowered/blocked channel.
        if (manager.currentInterruptionFilter != NotificationManager.INTERRUPTION_FILTER_ALL) { record(context, "dnd_suppressed"); return }
        val notification = manager.activeNotifications.firstOrNull { it.id == 100000 + id }?.notification
        if (notification == null) { record(context, "call_notification_missing"); return }
        if (Build.VERSION.SDK_INT >= 26 &&
            (manager.getNotificationChannel(notification.channelId)?.importance ?: 0) < NotificationManager.IMPORTANCE_HIGH) {
            record(context, "call_channel_not_high"); return
        }
        val fullScreen = notification.fullScreenIntent
        if (fullScreen == null) { record(context, "full_screen_intent_missing"); return }
        try {
            if (!power.isInteractive) {
                val wake = power.newWakeLock(PowerManager.SCREEN_BRIGHT_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP,
                    "Dahimail:incoming-call")
                wake.setReferenceCounted(false)
                wake.acquire(3000)
                locks[id] = wake
                handler.postDelayed({ if (locks[id] === wake) release(id) }, 3000)
            }
            // Explicit sender opt-in is required on Android 14+. Android still
            // enforces its background-activity rules; this does not unlock a phone.
            val options = if (Build.VERSION.SDK_INT >= 34) ActivityOptions.makeBasic().apply {
                setPendingIntentBackgroundActivityStartMode(ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED)
            }.toBundle() else null
            fullScreen.send(context, 0, null, null, null, null, options)
            record(context, "lock_screen_launch_requested")
            // PendingIntent.send() returning does not prove Android displayed the UI.
            handler.postDelayed({
                val last = context.getSharedPreferences("native_push", Context.MODE_PRIVATE).getString("last_wake_result", "")
                if (last == "lock_screen_launch_requested" && IncomingCallActivity.visibleCallId != id &&
                    NativePushReceiver.callExpiry(context, id) > System.currentTimeMillis() &&
                    !NativePushReceiver.isMinimized(context, id) && keyguard.isKeyguardLocked) {
                    record(context, "launch_not_observed")
                }
            }, 1200)
        } catch (error: Exception) {
            release(id)
            record(context, "lock_screen_launch_failed")
            android.util.Log.w("DahimailPush", "Lock-screen call retry failed: ${error.javaClass.simpleName}")
        }
    }

    fun cancel(id: Int) = onMain { cancelNow(id) }

    private fun cancelNow(id: Int) {
        pending.remove(id)?.let { handler.removeCallbacks(it) }
        release(id)
    }

    private fun release(id: Int) {
        locks.remove(id)?.let { if (it.isHeld) it.release() }
    }

    private fun record(context: Context, result: String) {
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit()
            .putString("last_wake_result", result).apply()
        android.util.Log.i("DahimailPush", "Incoming call screen: $result")
    }
}
