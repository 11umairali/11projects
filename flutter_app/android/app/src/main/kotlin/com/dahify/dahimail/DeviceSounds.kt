package com.dahify.dahimail

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.Ringtone
import android.media.RingtoneManager
import android.os.Handler
import android.os.Looper
import io.flutter.FlutterInjector

/** Foreground sounds follow system defaults and the phone's silent/vibrate mode. */
internal object DeviceSounds {
    @Volatile internal var incomingCallId: Int? = null
        private set
    private var ringtone: MediaPlayer? = null
    private var notification: Ringtone? = null
    private var callTone: MediaPlayer? = null
    private val handler = Handler(Looper.getMainLooper())
    private val deadline = Runnable { synchronized(this) { releaseRingtone(); incomingCallId = null } }

    @Synchronized fun startRingtone(context: Context) {
        if (incomingCallId != null) return
        playRingtone(context, 45000)
    }

    @Synchronized fun startIncoming(context: Context, id: Int, expiresAt: Long) {
        val remaining = expiresAt - System.currentTimeMillis()
        if (remaining <= 0 || incomingCallId == id) return
        releaseRingtone()
        incomingCallId = id
        playRingtone(context, remaining.coerceAtMost(45000))
    }

    @Synchronized fun stopIncoming(id: Int) {
        if (incomingCallId != id) return
        incomingCallId = null
        releaseRingtone()
    }

    private fun playRingtone(context: Context, duration: Long) {
        releaseRingtone()
        handler.postDelayed(deadline, duration)
        if (context.getSystemService(AudioManager::class.java).ringerMode != AudioManager.RINGER_MODE_NORMAL) return
        val player = MediaPlayer()
        try {
            player.setAudioAttributes(AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE).build())
            player.setDataSource(context, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE))
            player.isLooping = true
            player.prepare()
            player.start()
            ringtone = player

        } catch (_: Exception) { player.release() }
    }

    // Flutter lifecycle callbacks only own the foreground player, never a
    // call already handed to the native ringing service.
    @Synchronized fun stopRingtone() {
        if (incomingCallId == null) releaseRingtone()
    }

    private fun releaseRingtone() {
        handler.removeCallbacks(deadline)
        ringtone?.release()
        ringtone = null
    }

    @Synchronized fun playNotification(context: Context) {
        notification?.stop()
        if (context.getSystemService(AudioManager::class.java).ringerMode != AudioManager.RINGER_MODE_NORMAL) return
        try {
            notification = RingtoneManager.getRingtone(context, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION))
            notification?.audioAttributes = AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build()
            notification?.play()
        } catch (_: Exception) {}
    }

    /** Use the call route without taking audio focus away from WebRTC. */
    @Synchronized fun playCallTone(context: Context, connected: Boolean) {
        stopCallTone()
        val player = MediaPlayer()
        try {
            player.setAudioAttributes(AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build())
            val asset = if (connected) "connected" else "dialing"
            val key = FlutterInjector.instance().flutterLoader()
                .getLookupKeyForAsset("assets/sounds/$asset.wav")
            // WAV assets may be compressed by the APK packager, so use a
            // private cache copy instead of relying on AssetManager.openFd.
            val file = java.io.File(context.cacheDir, "call-tone-$asset.wav")
            context.assets.open(key).use { input -> file.outputStream().use { input.copyTo(it) } }
            player.setDataSource(file.path)
            player.isLooping = !connected
            player.setVolume(0.6f, 0.6f)
            player.setOnCompletionListener { synchronized(this) {
                if (callTone === it) stopCallTone()
            } }
            player.prepare()
            callTone = player
            player.start()
        } catch (_: Exception) {
            if (callTone === player) callTone = null
            player.release()
        }
    }

    @Synchronized fun stopCallTone() {
        callTone?.release()
        callTone = null
    }
}
