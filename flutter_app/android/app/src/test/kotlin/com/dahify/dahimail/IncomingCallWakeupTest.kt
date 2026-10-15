package com.dahify.dahimail

import android.app.Application
import android.app.KeyguardManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Looper
import android.os.PowerManager
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.Robolectric
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config
import org.robolectric.annotation.Implementation
import org.robolectric.annotation.Implements
import org.robolectric.shadows.ShadowNotificationManager
import org.robolectric.shadows.ShadowPowerManager
import java.time.Duration

@Implements(NotificationManager::class)
class FullScreenAccessManager : ShadowNotificationManager() {
    var fullScreenAllowed = true
    @Implementation(minSdk = 34)
    protected fun canUseFullScreenIntent(): Boolean = fullScreenAllowed
}

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class, shadows = [FullScreenAccessManager::class])
class IncomingCallWakeupTest {
    private lateinit var app: Application
    private lateinit var manager: NotificationManager
    private lateinit var power: PowerManager
    private lateinit var keyguard: KeyguardManager
    private fun call() = mapOf("call_id" to "42", "sent_at" to "${System.currentTimeMillis() / 1000}",
        "ttl" to "45", "caller_name" to "Alice Example")
    private fun advance(ms: Long = 800) = shadowOf(Looper.getMainLooper()).idleFor(Duration.ofMillis(ms))

    @Before fun setup() {
        app = RuntimeEnvironment.getApplication()
        manager = app.getSystemService(NotificationManager::class.java)
        power = app.getSystemService(PowerManager::class.java)
        keyguard = app.getSystemService(KeyguardManager::class.java)
        app.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit().clear().commit()
        MainActivity.isResumed = false
        IncomingCallActivity.visibleCallId = -1
        shadowOf(power).setIsInteractive(false)
        shadowOf(keyguard).setKeyguardLocked(true)
    }

    @After fun cleanup() {
        NativePushReceiver.dismissCall(app, 42)
        advance(10)
        IncomingCallActivity.visibleCallId = -1
    }

    @Test fun sleepingLockedPhoneGetsOneBoundedWakeAndNativeLaunchAfterPosting() {
        NativePushReceiver.showCall(app, call())
        assertNotNull(manager.activeNotifications.single().notification.fullScreenIntent)
        advance(700)
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
        advance(100)
        val wake = ShadowPowerManager.getLatestWakeLock()
        assertNotNull(wake)
        assertTrue(wake.isHeld)
        assertTrue(keyguard.isKeyguardLocked)
        val launch = shadowOf(app).nextStartedActivity
        assertEquals(IncomingCallActivity::class.java.name, launch.component?.className)
        assertTrue(launch.getStringExtra("call_data")!!.contains("42"))
        advance(3100)
        assertFalse(wake.isHeld)
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun launchRequestWithoutActivityResumeIsReportedAsUnconfirmed() {
        NativePushReceiver.showCall(app, call())
        advance()
        assertEquals("lock_screen_launch_requested", app.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            .getString("last_wake_result", null))
        advance(1300)
        assertEquals("launch_not_observed", app.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            .getString("last_wake_result", null))
    }

    @Test fun normalSystemLaunchCancelsTheFallback() {
        NativePushReceiver.showCall(app, call())
        val intent = shadowOf(manager.activeNotifications.single().notification.fullScreenIntent).savedIntent
        val activity = Robolectric.buildActivity(IncomingCallActivity::class.java, intent).create().start().resume()
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
        assertEquals("call_screen_resumed", app.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            .getString("last_wake_result", null))
        activity.pause().stop().destroy()
    }

    @Test fun cancellationBeforeRetryDoesNotWakeOrLaunch() {
        NativePushReceiver.showCall(app, call())
        NativePushReceiver.dismissCall(app, 42)
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun minimizationBeforeRetryDoesNotWakeOrLaunch() {
        val data = call()
        NativePushReceiver.showCall(app, data)
        NativePushReceiver.minimizeCall(app, data)
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun cancellationReleasesTheWakeLockImmediately() {
        NativePushReceiver.showCall(app, call())
        advance()
        val wake = ShadowPowerManager.getLatestWakeLock()
        assertTrue(wake.isHeld)
        NativePushReceiver.dismissCall(app, 42)
        advance(1)
        assertFalse(wake.isHeld)
    }

    @Test fun expiryBeforeRetryDoesNotWakeOrLaunch() {
        NativePushReceiver.showCall(app, call())
        app.getSharedPreferences("native_push", Context.MODE_PRIVATE).edit()
            // Robolectric advances handler uptime, not System.currentTimeMillis().
            // Make the stored server deadline expire after posting but before retry.
            .putLong("ringing_42", System.currentTimeMillis() - 1).commit()
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun fullScreenPermissionDenialDoesNotWakeOrLaunch() {
        (shadowOf(manager) as FullScreenAccessManager).fullScreenAllowed = false
        NativePushReceiver.showCall(app, call())
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
        assertEquals("full_screen_access_denied", app.getSharedPreferences("native_push", Context.MODE_PRIVATE)
            .getString("last_wake_result", null))
    }

    @Test fun quietChannelDoesNotWakeOrLaunch() {
        manager.createNotificationChannel(NotificationChannel("dm_calls_v7", "Calls", NotificationManager.IMPORTANCE_LOW))
        NativePushReceiver.showCall(app, call())
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun doNotDisturbDoesNotWakeOrLaunch() {
        manager.setInterruptionFilter(NotificationManager.INTERRUPTION_FILTER_NONE)
        NativePushReceiver.showCall(app, call())
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test fun unlockingBeforeRetryDoesNotLaunchOverTheUser() {
        NativePushReceiver.showCall(app, call())
        shadowOf(power).setIsInteractive(true)
        shadowOf(keyguard).setKeyguardLocked(false)
        advance()
        assertNull(ShadowPowerManager.getLatestWakeLock())
        assertNull(shadowOf(app).nextStartedActivity)
    }

    @Test @Config(sdk = [35]) fun android15ScreenOffBeforeKeyguardEngagesStillGetsRetry() {
        shadowOf(keyguard).setKeyguardLocked(false)
        NativePushReceiver.showCall(app, call())
        advance()
        assertTrue(ShadowPowerManager.getLatestWakeLock().isHeld)
        assertEquals(IncomingCallActivity::class.java.name, shadowOf(app).nextStartedActivity.component?.className)
    }
}
