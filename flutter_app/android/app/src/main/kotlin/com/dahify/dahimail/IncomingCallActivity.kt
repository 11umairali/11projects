package com.dahify.dahimail

import android.app.Activity
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.WindowManager
import android.view.View
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import org.json.JSONObject

/** The ringing screen has no Flutter dependency. Only Answer starts the app. */
class IncomingCallActivity : Activity() {
    private val handler = Handler(Looper.getMainLooper())
    private var callId = -1
    private var expiresAt = 0L
    private var responding = false
    private var minimizing = false
    private var registered = false
    private var callData: Map<String, String> = emptyMap()
    private val closed = object : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            if (intent.getIntExtra("call_id", -1) == callId) finish()
        }
    }
    private val timeout = Runnable { finish() }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON or WindowManager.LayoutParams.FLAG_SECURE)
        if (Build.VERSION.SDK_INT >= 27) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON)
        }
        ContextCompat.registerReceiver(this, closed, IntentFilter(NativePushReceiver.CALL_CLOSED), ContextCompat.RECEIVER_NOT_EXPORTED)
        registered = true
        if (Build.VERSION.SDK_INT >= 33) {
            onBackInvokedDispatcher.registerOnBackInvokedCallback(android.window.OnBackInvokedDispatcher.PRIORITY_DEFAULT) { minimize() }
        }
        display(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        display(intent)
    }

    private fun display(intent: Intent) {
        handler.removeCallbacks(timeout)
        val json = try { JSONObject(intent.getStringExtra("call_data") ?: "{}") } catch (_: Exception) { finish(); return }
        callData = json.keys().asSequence().associateWith { json.optString(it) }
        callId = callData["call_id"]?.toIntOrNull() ?: -1
        expiresAt = NativePushReceiver.callExpiry(this, callId)
        if (callId <= 0 || expiresAt <= System.currentTimeMillis()) { finish(); return }
        if (NativePushReceiver.isMinimized(this, callId) && !intent.getBooleanExtra("user_open", false)) {
            finish(); return
        }
        NativePushReceiver.setMinimized(this, callId, false)
        responding = false
        minimizing = false
        handler.postDelayed(timeout, expiresAt - System.currentTimeMillis())
        setContentView(R.layout.activity_incoming_call)
        val root = findViewById<View>(R.id.incoming_root)
        WindowCompat.setDecorFitsSystemWindows(window, false)
        val bars = WindowCompat.getInsetsController(window, root)
        bars.isAppearanceLightStatusBars = false
        bars.isAppearanceLightNavigationBars = false
        @Suppress("DEPRECATION")
        window.statusBarColor = getColor(R.color.incoming_background)
        @Suppress("DEPRECATION")
        window.navigationBarColor = getColor(R.color.incoming_background)
        if (Build.VERSION.SDK_INT >= 29) {
            window.isStatusBarContrastEnforced = false
            window.isNavigationBarContrastEnforced = false
            root.isForceDarkAllowed = false
        }
        ViewCompat.setOnApplyWindowInsetsListener(root) { view, insets ->
            val safe = insets.getInsets(WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.displayCutout())
            view.setPadding(safe.left, safe.top, safe.right, safe.bottom)
            insets
        }
        ViewCompat.requestApplyInsets(root)
        IncomingCallContent.bind(this, root, callData, onAccept = {
            responding = true
            NativePushReceiver.dismissCall(this, callId)
            startActivity(NativePushReceiver.answerActivityIntent(this, callId, callData))
            finish()
        }, onDecline = {
            responding = true
            NativePushReceiver.declineIntent(this, callData).send()
            finish()
        })

    }

    override fun onResume() {
        super.onResume()
        if (!isFinishing && !minimizing) {
            visibleCallId = callId
            getSharedPreferences("native_push", MODE_PRIVATE).edit()
                .putString("last_wake_result", "call_screen_resumed").apply()
            android.util.Log.i("DahimailPush", "Incoming call activity resumed")
            IncomingCallWakeup.cancel(callId)
            IncomingCallOverlay.hide(callId)
        }
    }

    override fun onPause() {
        if (visibleCallId == callId) visibleCallId = -1
        super.onPause()
    }

    private fun minimize() {
        if (minimizing || responding || isFinishing) return
        minimizing = true
        if (visibleCallId == callId) visibleCallId = -1
        // Keep the normal notification available after the user leaves the screen.
        // Finish after key dispatch to avoid reentrant window teardown.
        if (NativePushReceiver.callExpiry(this, callId) > System.currentTimeMillis()) {
            NativePushReceiver.minimizeCall(this, callData)
        }
        handler.post { finish() }
    }

    @Suppress("DEPRECATION")
    override fun onBackPressed() = minimize()

    override fun onUserLeaveHint() {
        super.onUserLeaveHint()
        minimize()
    }

    override fun onDestroy() {
        if (visibleCallId == callId) visibleCallId = -1
        handler.removeCallbacksAndMessages(null)
        if (registered) unregisterReceiver(closed)
        super.onDestroy()
    }
    companion object { @Volatile internal var visibleCallId = -1 }
}
