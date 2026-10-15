package com.dahify.dahimail

import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.Handler
import android.os.Looper
import org.json.JSONObject

/** Keeps the ringing window alive after Firebase's delivery service returns. */
open class IncomingCallService : Service() {
    private val handler = Handler(Looper.getMainLooper())
    private var callId = -1
    private val expire = Runnable {
        if (callId > 0) NativePushReceiver.dismissCall(this, callId)
        stopSelf()
    }
    override fun onBind(intent: Intent?) = null
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val raw = intent?.getStringExtra("call_data") ?: return START_NOT_STICKY.also { stopSelf() }
        val data = try { JSONObject(raw).let { j -> j.keys().asSequence().associateWith { j.optString(it) } } }
            catch (_: Exception) { stopSelf(); return START_NOT_STICKY }
        callId = data["call_id"]?.toIntOrNull() ?: -1
        val remaining = NativePushReceiver.callExpiry(this, callId) - System.currentTimeMillis()
        val notification = getSystemService(NotificationManager::class.java).activeNotifications.firstOrNull { it.id == 100000 + callId }?.notification
        if (remaining <= 0 || notification == null) { stopSelf(); return START_NOT_STICKY }
        try {
            promote(callId, notification)
            activeId = callId
            DeviceSounds.startIncoming(this, callId, NativePushReceiver.callExpiry(this, callId))
            IncomingCallOverlay.present(applicationContext, data, NativePushReceiver.isMinimized(this, callId))
            handler.removeCallbacks(expire)
            handler.postDelayed(expire, remaining)
        } catch (_: RuntimeException) {
            // startForegroundService() returning successfully does not prove that
            // startForeground() succeeded. Preserve audible delivery in either case.
            try { NativePushReceiver.useSystemRingtone(this, callId) }
            catch (error: RuntimeException) {
                android.util.Log.w("DahimailPush", "Call fallback failed: ${error.javaClass.simpleName}")
            } finally { stopSelf() }
        }
        return START_NOT_STICKY
    }
    protected open fun promote(id: Int, notification: android.app.Notification) {
        if (Build.VERSION.SDK_INT >= 34) startForeground(100000 + id, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_SHORT_SERVICE)
        else startForeground(100000 + id, notification)
    }
    override fun onTimeout(startId: Int) { expire.run() }
    override fun onDestroy() {
        handler.removeCallbacksAndMessages(null)
        DeviceSounds.stopIncoming(callId)
        if (activeId == callId) activeId = -1
        stopForeground(STOP_FOREGROUND_DETACH)
        super.onDestroy()
    }
    companion object {
        @Volatile private var activeId = -1
        fun present(context: Context, data: Map<String, String>): Boolean {
            // Show immediately; service startup must not delay the ringing window.
            IncomingCallOverlay.present(context, data, NativePushReceiver.isMinimized(context, data["call_id"]?.toIntOrNull() ?: -1))
            try {
                val intent = Intent(context, IncomingCallService::class.java).putExtra("call_data", JSONObject(data).toString())
                if (Build.VERSION.SDK_INT >= 26) context.startForegroundService(intent) else context.startService(intent)
                return true
            } catch (_: RuntimeException) { return false }
        }
        fun stop(context: Context, id: Int) {
            if (activeId == id) context.stopService(Intent(context, IncomingCallService::class.java))
        }
    }
}
