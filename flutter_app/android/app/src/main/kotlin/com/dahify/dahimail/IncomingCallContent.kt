package com.dahify.dahimail

import android.content.Context
import android.view.View
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.TextView
import androidx.core.widget.TextViewCompat
import java.util.Locale
import java.util.concurrent.Executors
import kotlin.math.roundToInt

/** One native presentation for the lock-screen Activity and over-app window. */
internal object IncomingCallContent {
    private val images = Executors.newSingleThreadExecutor()
    fun bind(context: Context, root: View, callData: Map<String, String>, onAccept: () -> Unit, onDecline: () -> Unit) {
        val callId = callData["call_id"]?.toIntOrNull() ?: return
        var responding = false
        val name = callData["caller_name"].orEmpty().ifBlank { "Someone" }
        val video = callData["video"] in listOf("1", "true") && callData["audio_only"] !in listOf("1", "true")
        val meeting = callData["group"] in listOf("1", "true") || callData["group_invite"] in listOf("1", "true")
        val label = if (meeting) "Incoming Meeting…" else if (video) "Incoming Video Call…" else "Incoming Audio Call…"
        root.findViewById<TextView>(R.id.incoming_name).text = name
        val status = root.findViewById<TextView>(R.id.incoming_status).apply { text = label }
        val initials = name.trim().split(Regex("\\s+")).filter { it.isNotEmpty() }.take(2)
            .joinToString("") { String(Character.toChars(it.codePointAt(0))) }.uppercase(Locale.ROOT)
        val initialsView = root.findViewById<TextView>(R.id.incoming_initials).apply { text = initials }
        // Pin typography to the same Roboto metrics as IncomingCallView, independent
        // of the app's light/dark theme and Android's default widget styling.
        fun lineHeight(view: View) {
            if (view is TextView) TextViewCompat.setLineHeight(view, (view.textSize * 1.2f).roundToInt())
            if (view is android.view.ViewGroup) for (i in 0 until view.childCount) lineHeight(view.getChildAt(i))
        }
        lineHeight(root)
        val avatar = root.findViewById<ImageView>(R.id.incoming_avatar)
        val accept = root.findViewById<ImageButton>(R.id.incoming_accept)
        val decline = root.findViewById<ImageButton>(R.id.incoming_decline)
        accept.setImageResource(if (video) R.drawable.ic_incoming_video else R.drawable.ic_incoming_accept)
        fun respond(action: () -> Unit) {
            if (responding || NativePushReceiver.callExpiry(context, callId) <= System.currentTimeMillis()) return
            responding = true
            status.text = "Please wait…"
            accept.isEnabled = false; decline.isEnabled = false
            accept.alpha = .5f; decline.alpha = .5f
            action()
        }
        accept.setOnClickListener { respond(onAccept) }
        decline.setOnClickListener { respond(onDecline) }
        val avatarUrl = callData["caller_avatar"].orEmpty()

        if (avatarUrl.startsWith("https://")) images.execute {
            val bitmap = NativePushReceiver.fetchAvatar(avatarUrl) ?: return@execute
            root.post { if (root.isAttachedToWindow) {
                avatar.setImageBitmap(bitmap)
                initialsView.visibility = View.INVISIBLE
            } }
        }
    }
}
