package com.dahify.dahimail

import android.app.Application
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.os.Looper
import org.json.JSONObject
import org.junit.Test
import org.junit.Assert.*
import org.junit.runner.RunWith
import org.robolectric.Robolectric
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config

class RefusedIncomingCallService : IncomingCallService() {
    override fun promote(id: Int, notification: android.app.Notification) {
        throw SecurityException("Synthetic foreground promotion refusal")
    }
}

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class IncomingCallServiceTest {
    @Test fun ringingServiceKeepsNotificationAndStopsOnCancellation() {
        val context = RuntimeEnvironment.getApplication()
        val data = mapOf("call_id" to "42", "sent_at" to "${System.currentTimeMillis() / 1000}", "ttl" to "45")
        NativePushReceiver.showCall(context, data)
        val controller = Robolectric.buildService(IncomingCallService::class.java,
            Intent(context, IncomingCallService::class.java).putExtra("call_data", JSONObject(data).toString())).create().startCommand(0, 1)
        assertNotNull(shadowOf(controller.get()).lastForegroundNotification)
        NativePushReceiver.minimizeCall(context, data)
        assertNull(context.getSystemService(NotificationManager::class.java).activeNotifications.single().notification.fullScreenIntent)
        NativePushReceiver.dismissCall(context, 42)
        shadowOf(Looper.getMainLooper()).idle()
        assertEquals(0, context.getSystemService(NotificationManager::class.java).activeNotifications.size)
        controller.destroy()
    }
    @Test fun refusedForegroundPromotionKeepsAudibleCallWithOriginalDeadlineAndActions() {
        val context = RuntimeEnvironment.getApplication()
        val manager = context.getSystemService(NotificationManager::class.java)
        val data = mapOf("call_id" to "44", "sent_at" to "${System.currentTimeMillis() / 1000}", "ttl" to "20")
        NativePushReceiver.showCall(context, data)
        val expiry = NativePushReceiver.callExpiry(context, 44)
        val controller = Robolectric.buildService(RefusedIncomingCallService::class.java,
            Intent(context, RefusedIncomingCallService::class.java).putExtra("call_data", JSONObject(data).toString())).create().startCommand(0, 1)
        val notification = manager.activeNotifications.single().notification
        assertEquals("dm_calls_fallback_v1", notification.channelId)
        assertNotNull(manager.getNotificationChannel(notification.channelId).sound)
        assertNotNull(notification.fullScreenIntent)
        assertEquals(2, notification.actions.size)
        assertTrue(notification.timeoutAfter in 1..20000)
        assertEquals(expiry, NativePushReceiver.callExpiry(context, 44))
        assertTrue(shadowOf(controller.get()).isStoppedBySelf)
        assertNull(DeviceSounds.incomingCallId)
        controller.destroy()
        assertEquals(1, manager.activeNotifications.size)
        NativePushReceiver.dismissCall(context, 44)
        assertEquals(0, manager.activeNotifications.size)
    }

    @Test fun fallbackForMinimizedCallRemainsAudibleWithoutReopeningFullScreen() {
        val context = RuntimeEnvironment.getApplication()
        val data = mapOf("call_id" to "46", "sent_at" to "${System.currentTimeMillis() / 1000}", "ttl" to "20")
        NativePushReceiver.showCall(context, data)
        NativePushReceiver.minimizeCall(context, data)
        NativePushReceiver.useSystemRingtone(context, 46)
        val notification = context.getSystemService(NotificationManager::class.java).activeNotifications.single().notification
        assertNull(notification.fullScreenIntent)
        assertEquals("dm_calls_fallback_v1", notification.channelId)
        assertEquals(0, notification.flags and android.app.Notification.FLAG_ONLY_ALERT_ONCE)
        NativePushReceiver.dismissCall(context, 46)
    }

    @Test fun lateServiceFailureCannotResurrectCancelledCall() {
        val context = RuntimeEnvironment.getApplication()
        NativePushReceiver.dismissCall(context, 45)
        NativePushReceiver.useSystemRingtone(context, 45)
        assertEquals(0, context.getSystemService(NotificationManager::class.java).activeNotifications.size)
    }

    @Test fun expiredServiceIntentNeverResurrectsCall() {
        val context = RuntimeEnvironment.getApplication()
        val data = mapOf("call_id" to "43", "sent_at" to "0")
        val controller = Robolectric.buildService(IncomingCallService::class.java,
            Intent(context, IncomingCallService::class.java).putExtra("call_data", JSONObject(data).toString())).create().startCommand(0, 1)
        assertNull(shadowOf(controller.get()).lastForegroundNotification)
        assertTrue(shadowOf(controller.get()).isStoppedBySelf)
        controller.destroy()
    }
}
