package com.dahify.dahimail

import android.app.Service
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.LinearLayout
import android.widget.TextView

/**
 * The small floating call window ("bubble") that stays above OTHER apps while a call runs and DahiMail is in the background –
 * like the call chip of WhatsApp / Messenger. Needs the "Display over other apps" permission. Drag it anywhere; tap the name to return to the full call controls.
 * Plain Android views – no Flutter engine needed, so it keeps working even if Flutter is busy.
 */
class CallOverlayService : Service() {
    companion object {
        /** set by MainActivity: forwards "expand" | "mute" | "end" to Dart */
        @Volatile var listener: ((String) -> Unit)? = null
        @Volatile private var instance: CallOverlayService? = null

        fun canDraw(ctx: Context): Boolean = Build.VERSION.SDK_INT < 23 || Settings.canDrawOverlays(ctx)

        fun show(ctx: Context, title: String, sinceMillis: Long, muted: Boolean) {
            if (!canDraw(ctx)) return
            val i = Intent(ctx, CallOverlayService::class.java).putExtra("title", title).putExtra("since", sinceMillis).putExtra("muted", muted)
            try { ctx.startService(i) } catch (_: Exception) {}
        }

        fun update(title: String, sinceMillis: Long, muted: Boolean) { instance?.paint(title, sinceMillis, muted) }

        fun hide(ctx: Context) { try { ctx.stopService(Intent(ctx, CallOverlayService::class.java)) } catch (_: Exception) {} }
    }

    private var wm: WindowManager? = null
    private var root: LinearLayout? = null
    private var nameView: TextView? = null
    private var timeView: TextView? = null
    private var since = 0L
    private val handler = Handler(Looper.getMainLooper())
    private val tick = object : Runnable {
        override fun run() {
            val s = if (since > 0) ((System.currentTimeMillis() - since) / 1000).coerceAtLeast(0) else 0
            timeView?.text = if (since > 0) String.format("%d:%02d", s / 60, s % 60) else "Connecting…"
            handler.postDelayed(this, 1000)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        instance = this
        val title = intent?.getStringExtra("title") ?: "Call"
        since = intent?.getLongExtra("since", 0L) ?: 0L
        val muted = intent?.getBooleanExtra("muted", false) ?: false
        if (root == null) build()
        paint(title, since, muted)
        return START_NOT_STICKY
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    private fun pill(color: Int, radius: Int) = GradientDrawable().apply { setColor(color); cornerRadius = dp(radius).toFloat() }

    private fun build() {
        wm = getSystemService(WINDOW_SERVICE) as WindowManager
        val box = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL; gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(14), dp(8), dp(8), dp(8)); background = pill(Color.parseColor("#E61E1E2A"), 28); elevation = dp(8).toFloat()
        }
        val dot = TextView(this).apply { text = "📞"; textSize = 18f }
        val col = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(8), 0, dp(6), 0) }
        nameView = TextView(this).apply { setTextColor(Color.WHITE); textSize = 13f; typeface = Typeface.DEFAULT_BOLD; maxLines = 1; maxWidth = dp(120) }
        timeView = TextView(this).apply { setTextColor(Color.parseColor("#69F0AE")); textSize = 12f }
        col.addView(nameView); col.addView(timeView)
        col.setOnClickListener { listener?.invoke("expand"); bringAppToFront() }
        box.addView(dot); box.addView(col)

        val type = if (Build.VERSION.SDK_INT >= 26) WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY else 2002 /* TYPE_PHONE, Android 6 and older */
        val lp = WindowManager.LayoutParams(
            WindowManager.LayoutParams.WRAP_CONTENT, WindowManager.LayoutParams.WRAP_CONTENT, type,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS, PixelFormat.TRANSLUCENT
        ).apply { gravity = Gravity.TOP or Gravity.START; x = dp(12); y = dp(90) }

        // drag anywhere (the buttons and the name still receive their taps)
        var sx = 0; var sy = 0; var tx = 0f; var ty = 0f; var moved = false
        box.setOnTouchListener { _, e ->
            when (e.action) {
                MotionEvent.ACTION_DOWN -> { sx = lp.x; sy = lp.y; tx = e.rawX; ty = e.rawY; moved = false; false }
                MotionEvent.ACTION_MOVE -> {
                    val dx = (e.rawX - tx).toInt(); val dy = (e.rawY - ty).toInt()
                    if (Math.abs(dx) + Math.abs(dy) > dp(6)) moved = true
                    if (moved) { lp.x = sx + dx; lp.y = sy + dy; try { wm?.updateViewLayout(box, lp) } catch (_: Exception) {} }
                    moved
                }
                else -> moved
            }
        }
        try { wm?.addView(box, lp); root = box } catch (_: Exception) { stopSelf() }
        handler.removeCallbacks(tick); handler.post(tick)
    }

    fun paint(title: String, sinceMillis: Long, muted: Boolean) {
        since = sinceMillis
        nameView?.text = title
        nameView?.contentDescription = if (muted) "$title, microphone muted. Tap to return" else "$title. Tap to return"
    }

    private fun bringAppToFront() {
        val i = packageManager.getLaunchIntentForPackage(packageName) ?: return
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_REORDER_TO_FRONT or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        try { startActivity(i) } catch (_: Exception) {}
    }

    override fun onDestroy() {
        handler.removeCallbacks(tick)
        try { root?.let { wm?.removeView(it) } } catch (_: Exception) {}
        root = null; instance = null
        super.onDestroy()
    }
}
