package com.dahify.dahimail

import android.app.KeyguardManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.graphics.PixelFormat
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.view.ContextThemeWrapper
import android.view.Gravity
import android.view.KeyEvent
import android.view.LayoutInflater
import android.view.View
import android.view.WindowManager
import android.widget.FrameLayout
import androidx.core.content.ContextCompat

/** Short-lived ringing windows; lifetime is bounded by the original call deadline.
 * Uses the overlay grant even when Android silently refuses a background Activity.
 * The notification remains the durable fallback if this process/window is removed.
 */
internal object IncomingCallOverlay {
    private val handler = Handler(Looper.getMainLooper())
    private var root: View? = null
    private var owner: Context? = null
    private var currentId = -1
    private var receiver: BroadcastReceiver? = null
    private val timeout = Runnable { hide(currentId) }

    private fun onMain(action: () -> Unit) {
        // Never tear down or replace a window inside its key/touch dispatch.
        handler.post(action)
    }

    fun present(context: Context, data: Map<String, String>, minimized: Boolean) = onMain {
        if (NativePushReceiver.isAppVisible(context)) return@onMain
        val id = data["call_id"]?.toIntOrNull() ?: return@onMain
        if (NativePushReceiver.callExpiry(context, id) <= System.currentTimeMillis()) return@onMain
        if (minimized || NativePushReceiver.isMinimized(context, id)) {
            hideNow(); return@onMain
        }
        // An Activity may already have opened from Android's full-screen intent.
        if (IncomingCallActivity.visibleCallId == id) return@onMain
        if (currentId == id && root != null) return@onMain
        val shown = show(context, data)
        if (CallOverlayService.canDraw(context)) {
            val launch = Runnable {
                if (shown != null && root !== shown) return@Runnable
                if (NativePushReceiver.callExpiry(context, id) <= System.currentTimeMillis() ||
                    NativePushReceiver.isMinimized(context, id) || IncomingCallActivity.visibleCallId == id) return@Runnable
                try { context.startActivity(NativePushReceiver.callScreenIntent(context, data)) }
                catch (error: Exception) {
                    android.util.Log.w("DahimailPush", "Call screen launch failed: ${error.javaClass.simpleName}")
                }
            }
            // Android 15+ needs the overlay to be visible before its background
            // launch exemption applies. Keep it until the Activity actually resumes.
            if (shown != null) shown.postOnAnimation { handler.post(launch) } else handler.post(launch)
        }
    }

    private fun show(context: Context, data: Map<String, String>): View? {
        val id = data["call_id"]?.toIntOrNull() ?: return null
        hideNow()
        if (!CallOverlayService.canDraw(context) || context.getSystemService(KeyguardManager::class.java).isKeyguardLocked ||
            !context.getSystemService(android.os.PowerManager::class.java).isInteractive) return null
        val app = context.applicationContext
        try {
            val themed = ContextThemeWrapper(app, android.R.style.Theme_Material_NoActionBar)
            val content = LayoutInflater.from(themed).inflate(
                R.layout.activity_incoming_call, null)
            val view = object : FrameLayout(themed) {
                // Handle Back before focused action buttons or ScrollView descendants.
                override fun dispatchKeyEvent(event: KeyEvent): Boolean {
                    if (event.keyCode == KeyEvent.KEYCODE_BACK) {
                        if (event.action == KeyEvent.ACTION_UP) NativePushReceiver.minimizeCall(app, data)
                        return true
                    }
                    return super.dispatchKeyEvent(event)
                }
            }.apply { addView(content); isFocusableInTouchMode = true }
            IncomingCallContent.bind(themed, view, data, onAccept = {
                // Keep the visible overlay until launch; Android 15 uses it for the
                // background Activity launch exemption even on an Accept tap.
                app.startActivity(NativePushReceiver.answerActivityIntent(app, id, data))
                NativePushReceiver.dismissCall(app, id)
            }, onDecline = {
                NativePushReceiver.declineIntent(app, data).send()
                hide(id)
            })
            val params = WindowManager.LayoutParams(
                WindowManager.LayoutParams.MATCH_PARENT,
                WindowManager.LayoutParams.MATCH_PARENT,
                if (Build.VERSION.SDK_INT >= 26) WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY else 2002,
                WindowManager.LayoutParams.FLAG_SECURE or
                    WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON,
                PixelFormat.TRANSLUCENT
            ).apply { gravity = Gravity.TOP }
            app.getSystemService(WindowManager::class.java).addView(view, params)
            root = view; owner = app; currentId = id
            view.requestFocus()
            receiver = object : BroadcastReceiver() {
                override fun onReceive(context: Context, intent: Intent) {
                    when (intent.action) {
                        Intent.ACTION_CLOSE_SYSTEM_DIALOGS -> {
                            if (intent.getStringExtra("reason") in listOf("homekey", "recentapps")) {
                                NativePushReceiver.minimizeCall(app, data)
                            }
                        }
                        Intent.ACTION_SCREEN_OFF -> hide(id)
                    }
                }
            }
            // These are protected system broadcasts; apps cannot spoof them.
            ContextCompat.registerReceiver(app, receiver, IntentFilter().apply {
                addAction(Intent.ACTION_CLOSE_SYSTEM_DIALOGS); addAction(Intent.ACTION_SCREEN_OFF)
            }, ContextCompat.RECEIVER_EXPORTED)
            handler.postDelayed(timeout, NativePushReceiver.callExpiry(app, id) - System.currentTimeMillis())
            return view
        } catch (error: Exception) {
            hideNow()
            android.util.Log.w("DahimailPush", "Call overlay unavailable: ${error.javaClass.simpleName}")
            return null
        }
    }

    fun hide(id: Int) = onMain { if (currentId == id) hideNow() }

    private fun hideNow() {
        handler.removeCallbacks(timeout)
        owner?.let { app ->
            receiver?.let { try { app.unregisterReceiver(it) } catch (_: IllegalArgumentException) {} }
            root?.let { try { app.getSystemService(WindowManager::class.java).removeView(it) } catch (_: IllegalArgumentException) {} }
        }
        root = null; owner = null; receiver = null; currentId = -1
    }
}
