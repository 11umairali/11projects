package com.dahify.dahimail

import android.content.Context
import android.content.Intent
import com.dexterous.flutterlocalnotifications.ActionBroadcastReceiver
import org.json.JSONObject

/** Stop the phone's ringing UI immediately, then send decline via the background callback. */
class IncomingCallDeclineReceiver : ActionBroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != ACTION_TAPPED || intent.getStringExtra("actionId") != "call_decline_background") return
        val id = try { JSONObject(intent.getStringExtra("payload") ?: "{}").optString("call_id").toIntOrNull() } catch (_: Exception) { null }
        if (id == null || id <= 0) return
        NativePushReceiver.dismissCall(context, id)
        super.onReceive(context, intent)
    }
}
