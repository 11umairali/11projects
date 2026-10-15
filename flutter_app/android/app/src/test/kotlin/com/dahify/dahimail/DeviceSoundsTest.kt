package com.dahify.dahimail

import android.app.Application
import android.content.Context
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.os.Looper
import org.junit.*
import org.junit.Assert.*
import org.junit.runner.RunWith
import org.robolectric.*
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config
import org.robolectric.shadows.ShadowMediaPlayer
import org.robolectric.shadows.util.DataSource
import org.robolectric.util.ReflectionHelpers
import java.time.Duration

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [33], application = Application::class)
class DeviceSoundsTest {
    private val context: Context get() = RuntimeEnvironment.getApplication()
    private fun player(): MediaPlayer? = ReflectionHelpers.getField(DeviceSounds, "ringtone")
    @Before fun setup() {
        DeviceSounds.incomingCallId?.let { DeviceSounds.stopIncoming(it) }
        DeviceSounds.stopRingtone()
        context.getSystemService(AudioManager::class.java).ringerMode = AudioManager.RINGER_MODE_NORMAL
        ShadowMediaPlayer.addMediaInfo(DataSource.toDataSource(context, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)), ShadowMediaPlayer.MediaInfo(1000, 0))
    }
    @After fun cleanup() {
        DeviceSounds.incomingCallId?.let { DeviceSounds.stopIncoming(it) }
        DeviceSounds.stopRingtone()
    }
    @Test fun backgroundCallKeepsLoopingThroughFlutterPauseAndHomeUntilCancelled() {
        DeviceSounds.startIncoming(context, 42, System.currentTimeMillis() + 45000)
        val ringing = player()!!
        assertTrue(ringing.isPlaying)
        assertTrue(ringing.isLooping)
        DeviceSounds.stopRingtone() // MainActivity pause / late Dart foreground stop.
        DeviceSounds.startRingtone(context) // Returning to the app must not double-ring.
        shadowOf(Looper.getMainLooper()).idleFor(Duration.ofSeconds(5))
        assertSame(ringing, player())
        assertTrue(ringing.isPlaying)
        DeviceSounds.stopIncoming(99) // Stale cancellation cannot stop this call.
        assertTrue(ringing.isPlaying)
        DeviceSounds.stopIncoming(42)
        assertNull(player())
        assertNull(DeviceSounds.incomingCallId)
    }
    @Test fun duplicateHandoffsCannotExtendRingingDeadline() {
        DeviceSounds.startIncoming(context, 42, System.currentTimeMillis() + 5000)
        shadowOf(Looper.getMainLooper()).idleFor(Duration.ofSeconds(3))
        DeviceSounds.startIncoming(context, 42, System.currentTimeMillis() + 45000)
        shadowOf(Looper.getMainLooper()).idleFor(Duration.ofSeconds(3))
        assertNull(player())
        assertNull(DeviceSounds.incomingCallId)
    }
    @Test fun silentAndVibrateModesNeverPlayAudioAndExpiredCallsNeverStart() {
        for (mode in listOf(AudioManager.RINGER_MODE_SILENT, AudioManager.RINGER_MODE_VIBRATE)) {
            context.getSystemService(AudioManager::class.java).ringerMode = mode
            DeviceSounds.startIncoming(context, 42, System.currentTimeMillis() + 45000)
            assertNull(player())
            DeviceSounds.stopIncoming(42)
        }
        DeviceSounds.startIncoming(context, 42, System.currentTimeMillis() - 1)
        assertNull(DeviceSounds.incomingCallId)
    }
}
