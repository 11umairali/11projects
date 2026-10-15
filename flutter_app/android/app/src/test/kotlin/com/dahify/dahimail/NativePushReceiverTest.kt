package com.dahify.dahimail

import android.app.Application
import android.app.Notification
import android.app.NotificationManager
import android.app.KeyguardManager
import android.content.Context
import android.content.ContextWrapper
import android.content.Intent
import androidx.core.app.NotificationCompat
import org.junit.Assert.*
import org.junit.After
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.Robolectric
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.TextView
import java.time.Duration
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config
import org.robolectric.shadows.ShadowSettings

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [33], application = Application::class)
class NativePushReceiverTest {
    private lateinit var context: Context
    private lateinit var manager: NotificationManager
    private fun call(sent: Long = System.currentTimeMillis() / 1000) = mapOf(
        "call_id" to "42", "sent_at" to "$sent", "ttl" to "45", "caller_name" to "Alice Example", "video" to "1")

    @Before fun setup() {
        context = RuntimeEnvironment.getApplication()
        manager = context.getSystemService(NotificationManager::class.java)
        manager.cancelAll()
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().clear().commit()
        context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE).edit().clear().commit()
        MainActivity.isResumed = false
        ShadowSettings.setCanDrawOverlays(false)
    }

    @After fun clearWindows() {
        DeviceSounds.incomingCallId?.let { DeviceSounds.stopIncoming(it) }
        IncomingCallOverlay.hide(42)
        frames()
        IncomingCallActivity.visibleCallId = -1
    }

    private fun frames() = shadowOf(android.os.Looper.getMainLooper()).idleFor(Duration.ofMillis(32))

    private fun overlay(): View? = (shadowOf(context.getSystemService(android.view.WindowManager::class.java)) as org.robolectric.shadows.ShadowWindowManagerImpl)
        .views.firstOrNull { (it.layoutParams as? android.view.WindowManager.LayoutParams)?.type == android.view.WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY }

    @Test fun answerCarriesRoomMetadataWithoutDependingOnFlutterPolling() {
        val data = call() + mapOf("meeting" to "synthetic-room", "audio_only" to "true", "recipient_user_id" to "7")
        val answer = NativePushReceiver.answerActivityIntent(context, 42, data)
        val metadata = org.json.JSONObject(answer.getStringExtra("call_data")!!)
        assertEquals("synthetic-room", metadata.getString("meeting"))
        assertEquals("true", metadata.getString("audio_only"))
        assertEquals("7", metadata.getString("recipient_user_id"))
        assertEquals("42", metadata.getString("call_id"))
        assertEquals("call_accept", answer.getStringExtra("actionId"))
    }

    @Test fun deniedActivityLaunchStillShowsFullScreenOverlayThenBackDismissesIt() {
        ShadowSettings.setCanDrawOverlays(true)
        val restricted = object : ContextWrapper(context) {
            override fun startActivity(intent: Intent) { throw SecurityException("Synthetic restriction") }
        }
        val data = call()
        NativePushReceiver.showCall(restricted, data)
        frames()
        val full = overlay()!!
        assertEquals(android.view.WindowManager.LayoutParams.MATCH_PARENT, full.layoutParams.height)
        assertNotNull(full.findViewById<View>(R.id.incoming_root))
        full.dispatchKeyEvent(android.view.KeyEvent(android.view.KeyEvent.ACTION_DOWN, android.view.KeyEvent.KEYCODE_BACK))
        full.dispatchKeyEvent(android.view.KeyEvent(android.view.KeyEvent.ACTION_UP, android.view.KeyEvent.KEYCODE_BACK))
        assertSame(full, overlay()) // No window replacement during input dispatch.
        frames()
        assertTrue(NativePushReceiver.isMinimized(context, 42))
        assertNull(overlay())
        assertNull(manager.activeNotifications.single().notification.fullScreenIntent)
        assertNotNull(manager.activeNotifications.single().notification.contentIntent)
        NativePushReceiver.showCall(context, data)
        frames()
        assertNull(shadowOf(context as Application).nextStartedActivity)
        assertNull(overlay())

    }

    @Test fun flutterPauseHandoffDoesNotCollapseNewNativeFullScreenCall() {
        ShadowSettings.setCanDrawOverlays(true)
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val full = overlay()
        assertNotNull(full)
        val expiry = NativePushReceiver.callExpiry(context, 42)
        shadowOf(context as Application).nextStartedActivity
        NativePushReceiver.showCall(context, data + ("minimized" to "true"))
        frames()
        assertSame(full, overlay())
        assertFalse(NativePushReceiver.isMinimized(context, 42))
        assertNotNull(manager.activeNotifications.single().notification.fullScreenIntent)
        assertEquals(expiry, NativePushReceiver.callExpiry(context, 42))
        assertNull(shadowOf(context as Application).nextStartedActivity)
    }

    @Test fun overlayRemainsUntilActivityResumes() {
        ShadowSettings.setCanDrawOverlays(true)
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).create().start()
        frames()
        assertNotNull(overlay())
        assertEquals(-1, IncomingCallActivity.visibleCallId)
        controller.resume()
        frames()
        assertNull(overlay())
        assertEquals(42, IncomingCallActivity.visibleCallId)
        controller.pause().stop().destroy()
    }

    @Test fun volumeKeepsFullScreenOverlayWithoutCancellingCall() {
        ShadowSettings.setCanDrawOverlays(true)
        NativePushReceiver.showCall(context, call())
        frames()
        val full = overlay()!!
        full.dispatchKeyEvent(android.view.KeyEvent(android.view.KeyEvent.ACTION_DOWN, android.view.KeyEvent.KEYCODE_VOLUME_DOWN))
        assertSame(full, overlay())
        frames()
        assertSame(full, overlay())
        assertFalse(NativePushReceiver.isMinimized(context, 42))
        assertEquals(1, manager.activeNotifications.size)
    }

    @Test fun screenOffKeepsActivityAndFullScreenIntent() {
        val data = call()
        NativePushReceiver.showCall(context, data)
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        shadowOf(context.getSystemService(android.os.PowerManager::class.java)).setIsInteractive(false)
        context.sendBroadcast(Intent(Intent.ACTION_SCREEN_OFF))
        frames()
        assertFalse(controller.get().isFinishing)
        assertFalse(NativePushReceiver.isMinimized(context, 42))
        assertNull(overlay())
        assertNotNull(manager.activeNotifications.single().notification.fullScreenIntent)
        controller.pause().stop().destroy()
    }

    @Test fun sleepingPhoneIsNotWokenBeforeAndroidEvaluatesFullScreenIntent() {
        val power = context.getSystemService(android.os.PowerManager::class.java)
        val keyguard = context.getSystemService(KeyguardManager::class.java)
        shadowOf(power).setIsInteractive(false)
        shadowOf(keyguard).setKeyguardLocked(false) // Screen has turned off, keyguard has not locked yet.
        NativePushReceiver.showCall(context, call())
        assertNull(org.robolectric.shadows.ShadowPowerManager.getLatestWakeLock())
        assertFalse(power.isInteractive)
        val intent = shadowOf(manager.activeNotifications.single().notification.fullScreenIntent).savedIntent
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, intent).setup()
        assertTrue(shadowOf(controller.get()).turnScreenOn)
        assertTrue(shadowOf(controller.get()).showWhenLocked)
        controller.pause().stop().destroy()
    }

    @Test fun lockedFlutterHandoffRetainsFullScreenIntentAndShowsActivityOverKeyguard() {
        shadowOf(context.getSystemService(KeyguardManager::class.java)).setKeyguardLocked(true)
        shadowOf(context.getSystemService(android.os.PowerManager::class.java)).setIsInteractive(false)
        NativePushReceiver.showCall(context, call() + ("minimized" to "true"))
        val notification = manager.activeNotifications.single().notification
        assertNotNull(notification.fullScreenIntent)
        val intent = shadowOf(notification.fullScreenIntent).savedIntent
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, intent).setup()
        assertFalse(controller.get().isFinishing)
        assertFalse(NativePushReceiver.isMinimized(context, 42))
        assertTrue(shadowOf(controller.get()).showWhenLocked)
        assertTrue(shadowOf(controller.get()).turnScreenOn)
        controller.pause().stop().destroy()
    }

    @Test fun notificationRepostCannotExtendOriginalRingingDeadline() {
        val data = call()
        NativePushReceiver.showCall(context, data)
        val expiry = System.currentTimeMillis() + 5000
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putLong("ringing_42", expiry).commit()
        manager.cancelAll() // Foreground Flutter may remove the system notification.
        NativePushReceiver.showCall(context, data)
        assertEquals(expiry, NativePushReceiver.callExpiry(context, 42))
        assertTrue(manager.activeNotifications.single().notification.timeoutAfter <= 5000)
    }

    @Test fun homeAndRecentsMinimizeOverlayWithoutCancellingCall() {
        ShadowSettings.setCanDrawOverlays(true)
        for (reason in listOf("homekey", "recentapps")) {
            val data = call()
            NativePushReceiver.showCall(context, data)
        frames()
            IncomingCallOverlay.present(context, data, false)
        frames()
            context.sendBroadcast(Intent(Intent.ACTION_CLOSE_SYSTEM_DIALOGS).putExtra("reason", reason))
            shadowOf(android.os.Looper.getMainLooper()).idle()
            assertTrue(NativePushReceiver.isMinimized(context, 42))
            assertNull(overlay())
            assertTrue(NativePushReceiver.callExpiry(context, 42) > System.currentTimeMillis())
            NativePushReceiver.setMinimized(context, 42, false)
        }
    }

    @Test fun activityBackMinimizesAndOnlyUserTapCanReopenWithoutExtendingDeadline() {
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val expiry = NativePushReceiver.callExpiry(context, 42)
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        controller.get().onBackPressed()
        frames()
        assertTrue(controller.get().isFinishing)
        assertTrue(NativePushReceiver.isMinimized(context, 42))
        controller.pause().stop().destroy()
        val automatic = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).create()
        assertTrue(automatic.get().isFinishing)
        automatic.destroy()
        val tap = shadowOf(manager.activeNotifications.single().notification.contentIntent).savedIntent
        val reopened = Robolectric.buildActivity(IncomingCallActivity::class.java, tap).setup()
        assertFalse(reopened.get().isFinishing)
        assertFalse(NativePushReceiver.isMinimized(context, 42))
        assertEquals(expiry, NativePushReceiver.callExpiry(context, 42))
        org.robolectric.util.ReflectionHelpers.callInstanceMethod<Unit>(reopened.get(), "onUserLeaveHint")
        assertTrue(NativePushReceiver.isMinimized(context, 42))
        reopened.pause().stop().destroy()
    }

    @Test fun dismissedHandoffHasNotificationWithoutFloatingBanner() {
        ShadowSettings.setCanDrawOverlays(true)
        NativePushReceiver.showCall(context, call() + ("minimized" to "true"))
        frames()
        assertNull(shadowOf(context as Application).nextStartedActivity)
        assertNull(overlay())
        NativePushReceiver.dismissCall(context, 42)
        frames()
        assertNull(overlay())
        assertEquals(0, manager.activeNotifications.size)
    }

    @Test fun leavingScreenPreservesOriginalNotificationDeadlineAndActions() {
        ShadowSettings.setCanDrawOverlays(true)
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        shadowOf(context as Application).nextStartedActivity // Consume initial ringing launch.
        // Seed a call near its persisted deadline. Robolectric's looper clock
        // does not advance java.lang.System.currentTimeMillis used by delivery.
        val expiry = System.currentTimeMillis() + 5000
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putLong("ringing_42", expiry).commit()
        val before = System.currentTimeMillis()
        NativePushReceiver.minimizeCall(context, data)
        frames()
        val after = System.currentTimeMillis()
        val alert = manager.activeNotifications.single().notification
        assertTrue(alert.timeoutAfter in (expiry - after)..(expiry - before))
        assertTrue(alert.flags and Notification.FLAG_INSISTENT != 0)
        assertNull(overlay())
        assertNotNull(alert.contentIntent)
        assertTrue(alert.actions.isNotEmpty())
        assertNull(alert.fullScreenIntent)
    }

    @Test fun staleAutomaticPresentationDoesNotReopenDismissedScreen() {
        ShadowSettings.setCanDrawOverlays(true)
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        shadowOf(context as Application).nextStartedActivity
        NativePushReceiver.minimizeCall(context, data)
        frames()
        IncomingCallOverlay.present(context, data, false)
        frames()
        assertNull(overlay())
        assertNull(shadowOf(context as Application).nextStartedActivity)
    }

    @Test fun overlayExpiresAtOriginalDeadline() {
        ShadowSettings.setCanDrawOverlays(true)
        NativePushReceiver.showCall(context, call(System.currentTimeMillis() / 1000 - 43))
        frames()
        assertNotNull(overlay())
        shadowOf(android.os.Looper.getMainLooper()).idleFor(Duration.ofSeconds(3))
        assertNull(overlay())
    }

    @Test fun incomingCallPostsWithoutFlutterAndCarriesActionPayload() {
        assertTrue(NativePushReceiver.showCall(context, call()))
        frames()
        val alert = manager.activeNotifications.single().notification
        assertEquals(Notification.CATEGORY_CALL, alert.category)
        assertEquals("Alice Example", alert.extras.getCharSequence(Notification.EXTRA_TITLE).toString())
        assertNotNull(alert.fullScreenIntent)
        assertTrue(alert.flags and Notification.FLAG_INSISTENT != 0)
        val launch = shadowOf(alert.fullScreenIntent).savedIntent
        assertEquals(IncomingCallActivity::class.java.name, launch.component?.className)
        assertTrue(launch.getStringExtra("call_data")!!.contains("Alice Example"))
        val actions = alert.actions.map { shadowOf(it.actionIntent).savedIntent.getStringExtra("actionId") }
        assertTrue(actions.containsAll(listOf("call_accept", "call_decline_background")))
        assertEquals(NotificationManager.IMPORTANCE_HIGH, manager.getNotificationChannel("dm_calls_v7").importance)
        assertNull(manager.getNotificationChannel("dm_calls_v7").sound)
        assertNull(shadowOf(context as Application).nextStartedActivity)
    }

    @Test fun overlayGrantOpensRingingScreenOverOtherAppsOnlyOnce() {
        ShadowSettings.setCanDrawOverlays(true)
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val launched = shadowOf(context as Application).nextStartedActivity
        assertEquals(IncomingCallActivity::class.java.name, launched.component?.className)
        assertEquals(NativePushReceiver.callScreenIntent(context, data).getStringExtra("call_data"), launched.getStringExtra("call_data"))
        assertTrue(launched.flags and Intent.FLAG_ACTIVITY_NEW_TASK != 0)
        assertEquals(1, manager.activeNotifications.size)
        NativePushReceiver.showCall(context, data)
        frames()
        assertNull(shadowOf(context as Application).nextStartedActivity)
    }

    @Test fun overlayGrantAlsoOpensRingingScreenWhenPhoneIsLocked() {
        ShadowSettings.setCanDrawOverlays(true)
        MainActivity.isResumed = true
        shadowOf(context.getSystemService(KeyguardManager::class.java)).setKeyguardLocked(true)
        NativePushReceiver.showCall(context, call())
        frames()
        assertEquals(IncomingCallActivity::class.java.name, shadowOf(context as Application).nextStartedActivity.component?.className)
    }

    @Test fun foregroundAppDoesNotLaunchAnotherScreenWithOverlayGrant() {
        ShadowSettings.setCanDrawOverlays(true)
        MainActivity.isResumed = true
        NativePushReceiver.showCall(context, call())
        frames()
        assertNull(shadowOf(context as Application).nextStartedActivity)
    }

    @Test fun refusedActivityLaunchKeepsActionableFullScreenNotification() {
        ShadowSettings.setCanDrawOverlays(true)
        val restricted = object : ContextWrapper(context) {
            override fun startActivity(intent: Intent) { throw SecurityException("Synthetic launch restriction") }
        }
        assertTrue(NativePushReceiver.showCall(restricted, call()))
        frames()
        val notification = manager.activeNotifications.single().notification
        assertNotNull(notification.fullScreenIntent)
        assertEquals(2, notification.actions.size)
        assertTrue(NativePushReceiver.callExpiry(context, 42) > System.currentTimeMillis())
    }

    @Test fun overlayGrantDoesNotLaunchExpiredCancelledOrBlockedCalls() {
        ShadowSettings.setCanDrawOverlays(true)
        NativePushReceiver.showCall(context, call(System.currentTimeMillis() / 1000 - 60))
        frames()
        NativePushReceiver.dismissCall(context, 42)
        frames()
        NativePushReceiver.showCall(context, call())
        frames()
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().clear().commit()
        shadowOf(manager).setNotificationsEnabled(false)
        NativePushReceiver.showCall(context, call())
        frames()
        assertNull(shadowOf(context as Application).nextStartedActivity)
        assertEquals(0, manager.activeNotifications.size)
    }

    private fun views(view: View): List<View> = listOf(view) + if (view is ViewGroup) (0 until view.childCount).flatMap { views(view.getChildAt(it)) } else emptyList()

    @Test fun nativeScreenShowsCallerAndAnswerBeforeFlutterStarts() {
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        val activity = controller.get()
        val children = views(activity.window.decorView)
        assertTrue(children.filterIsInstance<TextView>().any { it.text == "Alice Example" })
        assertTrue(children.filterIsInstance<TextView>().any { it.text == "Incoming Video Call…" })
        val accept = activity.findViewById<ImageButton>(R.id.incoming_accept)
        val decline = activity.findViewById<ImageButton>(R.id.incoming_decline)
        val acceptPosition = IntArray(2).also { accept.getLocationOnScreen(it) }
        val declinePosition = IntArray(2).also { decline.getLocationOnScreen(it) }
        assertTrue(acceptPosition[0] > declinePosition[0])
        assertEquals("Accept", accept.contentDescription)
        assertEquals("Decline", decline.contentDescription)
        accept.performClick()
        frames()
        accept.performClick() // A second tap must not accept a second time.
        frames()
        val answer = shadowOf(activity).nextStartedActivity
        assertEquals(MainActivity::class.java.name, answer.component?.className)
        assertEquals("call_accept", answer.getStringExtra("actionId"))
        assertEquals("call_incoming:42", answer.getStringExtra("payload"))
        assertEquals(0, manager.activeNotifications.size)
        assertNull(shadowOf(activity).nextStartedActivity)
        assertTrue(activity.isFinishing)
        controller.pause().stop().destroy()
    }

    @Test @Config(qualifiers = "ldrtl") fun ringingScreenUsesTheSameDarkThemeAndActionOrderInRtl() {
        val data = call() + mapOf("group_invite" to "true", "audio_only" to "1")
        NativePushReceiver.showCall(context, data)
        frames()
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        val activity = controller.get()
        assertEquals("Incoming Meeting…", activity.findViewById<TextView>(R.id.incoming_status).text)
        assertEquals("AE", activity.findViewById<TextView>(R.id.incoming_initials).text)
        val root = activity.findViewById<View>(R.id.incoming_root)
        assertEquals(0xff111118.toInt(), (root.background as android.graphics.drawable.ColorDrawable).color)
        assertEquals(View.LAYOUT_DIRECTION_LTR, activity.findViewById<View>(R.id.incoming_actions).layoutDirection)
        val density = activity.resources.displayMetrics.density
        assertEquals((112 * density).toInt(), activity.findViewById<ImageView>(R.id.incoming_avatar).width)
        assertEquals((70 * density).toInt(), activity.findViewById<ImageButton>(R.id.incoming_accept).width)
        controller.pause().stop().destroy()
    }

    @Test fun cancellationClosesNativeScreenAndExpiredIntentCannotReopenIt() {
        val data = call()
        NativePushReceiver.showCall(context, data)
        frames()
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        NativePushReceiver.dismissCall(context, 42)
        frames()
        shadowOf(android.os.Looper.getMainLooper()).idle()
        assertTrue(controller.get().isFinishing)
        controller.pause().stop().destroy()
        val expired = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).create()
        assertTrue(expired.get().isFinishing)
        expired.destroy()
    }

    @Test fun nativeScreenClosesAtOriginalDeadline() {
        val data = call(System.currentTimeMillis() / 1000 - 40)
        NativePushReceiver.showCall(context, data)
        frames()
        val controller = Robolectric.buildActivity(IncomingCallActivity::class.java, NativePushReceiver.callScreenIntent(context, data)).setup()
        shadowOf(android.os.Looper.getMainLooper()).idleFor(Duration.ofSeconds(6))
        assertTrue(controller.get().isFinishing)
        controller.pause().stop().destroy()
    }

    @Test fun declineUsesBackgroundReceiverWithoutOpeningFlutterActivity() {
        val pending = NativePushReceiver.declineIntent(context, call() + mapOf("recipient_user_id" to "1"))
        val action = shadowOf(pending)
        assertTrue(action.isBroadcastIntent)
        assertEquals(IncomingCallDeclineReceiver::class.java.name, action.savedIntent.component?.className)
        assertEquals("call_decline_background", action.savedIntent.getStringExtra("actionId"))
        assertTrue(action.savedIntent.getStringExtra("payload")!!.contains("recipient_user_id"))
    }

    @Test @Config(sdk = [25]) fun preChannelPhonesAlsoReceiveAudibleHeadsUpMessages() {
        NativePushReceiver.showAlert(context, mapOf("notification_id" to "legacy", "type" to "chat", "title" to "Alice", "body" to "Hello"))
        val alert = manager.activeNotifications.single().notification
        assertEquals(Notification.PRIORITY_HIGH, alert.priority)
        assertTrue(alert.defaults and Notification.DEFAULT_SOUND != 0)
        assertTrue(alert.defaults and Notification.DEFAULT_VIBRATE != 0)
    }

    @Test fun expiredAndAlreadyCancelledCallsCannotRing() {
        assertTrue(NativePushReceiver.showCall(context, call(System.currentTimeMillis() / 1000 - 60)))
        frames()
        assertEquals(0, manager.activeNotifications.size)
        context.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().putLong("cancelled_42", System.currentTimeMillis()).commit()
        assertTrue(NativePushReceiver.showCall(context, call()))
        frames()
        assertEquals(0, manager.activeNotifications.size)
    }

    @Test fun messageHasBrandCategorySenderAndInlineReply() {
        val data = mapOf("notification_id" to "msg-1", "type" to "chat", "title" to "New message", "sender_name" to "Alice Example",
            "body" to "Hello there", "category_label" to "Chat", "reply_kind" to "friend", "reply_id" to "2", "recipient_user_id" to "1", "message_id" to "42")
        assertTrue(NativePushReceiver.showAlert(context, data))
        val alert = manager.activeNotifications.single().notification
        assertEquals(R.drawable.ic_stat_notify, alert.smallIcon.resId)
        assertNull(alert.getLargeIcon()) // Sender photo is in our trailing column, not duplicated by the OS.
        assertEquals("Chat", alert.extras.getCharSequence(Notification.EXTRA_SUB_TEXT).toString())
        assertEquals("Alice Example", alert.extras.getCharSequence(Notification.EXTRA_TITLE).toString())
        assertEquals("Hello there", alert.extras.getCharSequence(Notification.EXTRA_TEXT).toString())
        val reply = alert.actions.single { it.title == "Reply" }
        assertEquals("FlutterLocalNotificationsPluginInputResult", reply.remoteInputs.single().resultKey)
        assertEquals("notification_reply", shadowOf(reply.actionIntent).savedIntent.getStringExtra("actionId"))
        assertEquals("com.dexterous.flutterlocalnotifications.ActionBroadcastReceiver", shadowOf(reply.actionIntent).savedIntent.component?.className)
        assertTrue(alert.actions.any { it.title == "View" })
        assertEquals(listOf("View", "Reply"), alert.actions.map { it.title.toString() })
        assertEquals(NotificationCompat.CATEGORY_MESSAGE, alert.category)
    }

    @Test fun customLayoutPinsBrandBadgeAndSenderBesideRelativeTimeAndAvatar() {
        val posted = System.currentTimeMillis() - 120000
        val data = mapOf("notification_id" to "mail-layout", "type" to "email_received", "title" to "New email",
            "sender_name" to "Alice Example", "body" to "Subject and full preview", "sent_at" to "${posted / 1000}",
            "reply_kind" to "conversation", "reply_id" to "7", "recipient_user_id" to "1")
        assertTrue(NativePushReceiver.showAlert(context, data))
        val alert = manager.activeNotifications.single().notification
        assertNotNull(alert.contentView)
        assertNotNull(alert.bigContentView)
        assertNotNull(alert.headsUpContentView)
        val content = alert.headsUpContentView.apply(context, null)
        assertEquals("Alice Example", content.findViewById<TextView>(R.id.notification_sender).text)
        assertEquals("Subject and full preview", content.findViewById<TextView>(R.id.notification_body).text)
        assertEquals("2m ago", content.findViewById<TextView>(R.id.notification_time).text)
        assertEquals(2, content.findViewById<TextView>(R.id.notification_body).maxLines)
        val brand = content.findViewById<ImageView>(R.id.notification_brand)
        val badge = content.findViewById<ImageView>(R.id.notification_type_icon)
        assertSame(brand.parent, badge.parent)
        assertNotNull(content.findViewById<ImageView>(R.id.notification_avatar).drawable)
        assertEquals(listOf("View", "Reply"), alert.actions.map { it.title.toString() })
        val view = alert.actions.single { it.title == "View" }
        val payload = org.json.JSONObject(shadowOf(view.actionIntent).savedIntent.getStringExtra("payload")!!)
        assertEquals("view", payload.getString("notification_action"))
    }

    @Test fun notificationAgesHandleIsoEpochAndClockSkew() {
        val now = java.time.Instant.parse("2026-10-15T12:00:00Z").toEpochMilli()
        assertEquals("2m ago", NativePushReceiver.relativeTime(NativePushReceiver.notificationTime(mapOf("created_at_iso" to "2026-10-15T11:58:00Z"), now), now))
        assertEquals("1h ago", NativePushReceiver.relativeTime(now - 3600000, now))
        assertEquals("Just now", NativePushReceiver.relativeTime(now + 60000, now))
        assertEquals(now, NativePushReceiver.notificationTime(mapOf("sent_at" to "invalid"), now))
        assertEquals(R.drawable.ic_notify_friend, NativePushReceiver.notificationIcon(mapOf("type" to "friend_request")))
    }

    @Test fun disabledMessagesSuppressAlertsAndMissedCallsHaveNoReply() {
        val data = mapOf("notification_id" to "msg-2", "type" to "chat", "title" to "New message")
        context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE).edit().putBoolean("flutter.n_messages", false).commit()
        assertTrue(NativePushReceiver.showAlert(context, data))
        assertEquals(0, manager.activeNotifications.size)
        assertTrue(NativePushReceiver.showAlert(context, data + mapOf("type" to "missed_call")))
        assertEquals(listOf("View"), manager.activeNotifications.single().notification.actions.map { it.title })
    }
    @Test fun quietHoursMuteAlertsButUrgentCallsStillRing() {
        context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE).edit()
            .putBoolean("flutter.q_enabled", true).putLong("flutter.q_start", 0).putLong("flutter.q_end", 1440).commit()
        assertTrue(NativePushReceiver.showAlert(context, mapOf("notification_id" to "quiet", "type" to "chat", "title" to "Alice")))
        assertEquals(0, manager.activeNotifications.size)
        assertEquals("quiet_hours", NativePushReceiver.diagnostics(context)["last_result"])
        assertTrue(NativePushReceiver.showCall(context, call()))
        frames()
        assertEquals(Notification.CATEGORY_CALL, manager.activeNotifications.single().notification.category)
    }

    @Test fun phoneDefaultIgnoresLegacySoundChoiceAndRespectsVibrationPreference() {
        context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE).edit()
            .putString("flutter.n_sound_id", "silent").putBoolean("flutter.n_vibrate", false).commit()
        assertTrue(NativePushReceiver.showAlert(context, mapOf("notification_id" to "silent", "type" to "chat", "title" to "Alice")))
        val alert = manager.activeNotifications.single().notification
        val channel = manager.getNotificationChannel(alert.channelId)
        assertEquals(android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_NOTIFICATION).toString(), channel.sound.toString())
        assertFalse(channel.shouldVibrate())
        assertEquals(NotificationManager.IMPORTANCE_HIGH, channel.importance)
    }

    @Test fun blockedNotificationsExplainWhyNoBannerWasPosted() {
        shadowOf(manager).setNotificationsEnabled(false)
        assertTrue(NativePushReceiver.showCall(context, call()))
        frames()
        assertEquals(0, manager.activeNotifications.size)
        assertEquals("notifications_blocked", NativePushReceiver.diagnostics(context)["last_result"])
        assertEquals(false, NativePushReceiver.diagnostics(context)["enabled"])
    }

    @Test fun blockedCallChannelIsReportedWithoutPosting() {
        manager.createNotificationChannel(android.app.NotificationChannel("dm_calls_v7", "Incoming calls", NotificationManager.IMPORTANCE_NONE))
        assertTrue(NativePushReceiver.showCall(context, call()))
        frames()
        assertEquals(0, manager.activeNotifications.size)
        assertEquals("channel_blocked", NativePushReceiver.diagnostics(context)["last_result"])
        assertEquals(0, NativePushReceiver.diagnostics(context)["call_importance"])
    }

    @Test fun successfulPostIsDistinguishedFromNoPushReceived() {
        assertEquals("not_received", NativePushReceiver.diagnostics(context)["last_result"])
        NativePushReceiver.showAlert(context, mapOf("notification_id" to "test", "title" to "Test"))
        assertEquals("posted_alert", NativePushReceiver.diagnostics(context)["last_result"])
        assertEquals(4, NativePushReceiver.diagnostics(context)["channel_importance"])
    }

    @Test fun bannerUsesCustomExpandableLayoutWithSenderCategoryAndAvatar() {
        NativePushReceiver.showAlert(context, mapOf("notification_id" to "friend-1", "type" to "friend_request",
            "title" to "New request", "sender_name" to "Alice Example", "body" to "Open Friends"))
        val notification = manager.activeNotifications.single().notification
        assertEquals("Friend request", notification.extras.getCharSequence(Notification.EXTRA_SUB_TEXT).toString())
        assertEquals("Alice Example", notification.extras.getCharSequence(Notification.EXTRA_TITLE).toString())
        assertEquals("Sent you a friend request", notification.extras.getCharSequence(Notification.EXTRA_TEXT).toString())
        val expanded = notification.bigContentView.apply(context, null)
        assertEquals("Sent you a friend request", expanded.findViewById<TextView>(R.id.notification_body).text)
        assertNotNull(expanded.findViewById<ImageView>(R.id.notification_avatar).drawable)
        assertNull(notification.getLargeIcon())
        assertTrue(notification.`when` > 0)
        assertEquals(listOf("View"), notification.actions.map { it.title })
    }

    @Test fun mobileBannersIdentifyChatAudioVideoAndMeeting() {
        assertEquals(R.drawable.ic_notify_chat, NativePushReceiver.notificationIcon(mapOf("type" to "chat")))
        assertEquals(R.drawable.ic_notify_call, NativePushReceiver.notificationIcon(mapOf("type" to "missed_call", "video" to "true", "audio_only" to "true")))
        assertEquals(R.drawable.ic_notify_video, NativePushReceiver.notificationIcon(mapOf("type" to "missed_call", "video" to "true")))
        assertEquals(R.drawable.ic_notify_meeting, NativePushReceiver.notificationIcon(mapOf("type" to "friend_meeting")))
    }

}
