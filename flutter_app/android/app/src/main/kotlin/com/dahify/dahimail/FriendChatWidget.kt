package com.dahify.dahimail

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.util.Base64
import android.view.View
import android.os.Build
import android.widget.RemoteViews
import org.json.JSONObject
import java.util.UUID

/** A launcher tile for one conversation. No token or message content leaves Flutter. */
class FriendChatWidget : AppWidgetProvider() {
    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) {
        ids.forEach { update(context, it) }
    }

    override fun onDeleted(context: Context, ids: IntArray) {
        val edit = prefs(context).edit()
        ids.forEach { edit.remove("widget_$it") }
        edit.apply()
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        if (intent.action != PINNED) return
        val token = intent.data?.lastPathSegment ?: return
        val key = "pending_$token"
        val stored = prefs(context).getString(key, null) ?: return
        prefs(context).edit().remove(key).apply()
        val target = runCatching { JSONObject(stored) }.getOrNull() ?: return
        if (target.optInt("owner_id") != prefs(context).getInt("account", 0)) return
        val id = intent.getIntExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, AppWidgetManager.INVALID_APPWIDGET_ID)
        if (!owns(context, id)) return
        save(context, id, target)
    }

    companion object {
        const val PINNED = "com.dahify.dahimail.FRIEND_WIDGET_PINNED"
        const val EXTRA_ID = "friend_widget_id"
        private fun prefs(context: Context) = context.getSharedPreferences("friend_widgets", Context.MODE_PRIVATE)
        private fun manager(context: Context) = AppWidgetManager.getInstance(context)
        private fun component(context: Context) = ComponentName(context, FriendChatWidget::class.java)

        private fun owns(context: Context, id: Int) = id > 0 && manager(context).getAppWidgetIds(component(context)).contains(id)

        fun account(context: Context, owner: Int) {
            require(owner >= 0)
            val p = prefs(context)
            if (p.getInt("account", 0) == owner) return
            // A device can be shared: discard names and destinations on account changes.
            p.edit().clear().putInt("account", owner).commit()
            manager(context).getAppWidgetIds(component(context)).forEach { update(context, it) }
        }

        fun clear(context: Context) {
            prefs(context).edit().clear().commit()
            manager(context).getAppWidgetIds(component(context)).forEach { update(context, it) }
        }

        private fun target(owner: Int, friend: Int, name: String, avatar: ByteArray?): JSONObject {
            require(owner > 0 && friend > 0 && name.isNotBlank())
            return JSONObject().put("owner_id", owner).put("friend_id", friend).put("name", name.trim().take(120)).apply {
                // Flutter sends only a 256px PNG; never keep source-size images in launcher state.
                if (avatar != null && avatar.size <= 300_000) put("avatar", Base64.encodeToString(avatar, Base64.NO_WRAP))
            }
        }

        fun configure(context: Context, id: Int, owner: Int, friend: Int, name: String, avatar: ByteArray? = null) {
            require(owns(context, id)) { "The widget was removed. Add it again." }
            val values = target(owner, friend, name, avatar)
            account(context, owner)
            save(context, id, values)
        }

        private fun save(context: Context, id: Int, values: JSONObject) {
            prefs(context).edit().putString("widget_$id", values.toString()).commit()
            update(context, id)
        }

        fun pin(context: Context, owner: Int, friend: Int, name: String, avatar: ByteArray? = null): Boolean {
            val values = target(owner, friend, name, avatar)
            val mgr = manager(context)
            if (Build.VERSION.SDK_INT < 26 || !mgr.isRequestPinAppWidgetSupported) return false
            account(context, owner)
            val token = UUID.randomUUID().toString()
            val p = prefs(context)
            val edit = p.edit()
            val now = System.currentTimeMillis()
            // Pin prompts can be cancelled. Bound the temporary selections kept locally.
            p.all.filterKeys { it.startsWith("pending_") }.forEach { (key, raw) ->
                val savedAt = runCatching { JSONObject(raw.toString()).optLong("saved_at") }.getOrDefault(0L)
                if (now - savedAt > 86_400_000L) edit.remove(key)
            }
            edit.putString("pending_$token", values.put("saved_at", now).toString()).commit()
            val callback = Intent(context, FriendChatWidget::class.java).setAction(PINNED)
                .setData(Uri.parse("dahimail-widget://pin/$token"))
            // The launcher fills in EXTRA_APPWIDGET_ID; the explicit receiver is not exported.
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= 31) PendingIntent.FLAG_MUTABLE else 0)
            val sender = PendingIntent.getBroadcast(context, 0, callback, flags)
            val extras = android.os.Bundle().apply {
                putParcelable(AppWidgetManager.EXTRA_APPWIDGET_PREVIEW, views(context, values))
            }
            val requested = mgr.requestPinAppWidget(component(context), extras, sender)
            if (!requested) p.edit().remove("pending_$token").apply()
            return requested
        }

        /** Resolve the ID from current state, so removed/cleared widgets cannot replay old names. */
        fun action(context: Context, id: Int): Map<String, Any>? {
            if (!owns(context, id)) return null
            val values = read(context, id)
            return if (values == null) mapOf("type" to "home_friend_setup", "widget_id" to id)
            else mapOf("type" to "home_friend_chat", "from_id" to values.getInt("friend_id"),
                "recipient_user_id" to values.getInt("owner_id"))
        }

        private fun read(context: Context, id: Int): JSONObject? {
            val p = prefs(context)
            val values = runCatching { JSONObject(p.getString("widget_$id", null) ?: return null) }.getOrNull() ?: return null
            return values.takeIf { it.optInt("owner_id") > 0 && it.optInt("friend_id") > 0 && it.optInt("owner_id") == p.getInt("account", 0) }
        }

        private fun views(context: Context, values: JSONObject?): RemoteViews {
            val name = values?.optString("name")?.takeIf { it.isNotBlank() }
            return RemoteViews(context.packageName, R.layout.friend_chat_widget).apply {
                setTextViewText(R.id.widget_name, name ?: context.getString(R.string.friend_widget_choose))
                setTextViewText(R.id.widget_hint, context.getString(if (name == null) R.string.friend_widget_setup else R.string.friend_widget_open))
                val initial = name?.let { String(Character.toChars(it.codePointAt(0))).uppercase(java.util.Locale.getDefault()) } ?: "+"
                setTextViewText(R.id.widget_initial, initial)
                val photo = runCatching {
                    val encoded = values?.optString("avatar") ?: return@runCatching null
                    if (encoded.isEmpty() || encoded.length > 400_000) return@runCatching null
                    val bytes = Base64.decode(encoded, Base64.NO_WRAP)
                    val pngHeader = byteArrayOf(0x89.toByte(), 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a)
                    if (bytes.size < 8 || !bytes.copyOfRange(0, 8).contentEquals(pngHeader)) return@runCatching null
                    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
                    if (bounds.outWidth !in 1..256 || bounds.outHeight !in 1..256) return@runCatching null
                    BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
                }.getOrNull()
                setViewVisibility(R.id.widget_photo, if (photo == null) View.GONE else View.VISIBLE)
                setViewVisibility(R.id.widget_initial, if (photo == null) View.VISIBLE else View.GONE)
                if (photo == null) setImageViewResource(R.id.widget_photo, 0)
                else setImageViewBitmap(R.id.widget_photo, photo)
                setViewVisibility(R.id.widget_hint, if (name == null) View.VISIBLE else View.GONE)
                setContentDescription(R.id.widget_root, if (name == null) context.getString(R.string.friend_widget_choose) else context.getString(R.string.friend_widget_chat_with, name))
            }
        }

        fun update(context: Context, id: Int) {
            val remote = views(context, read(context, id))
            val launch = Intent(context, MainActivity::class.java)
                // A private extra avoids Flutter treating this as a named-route deep link.
                .setAction("com.dahify.dahimail.OPEN_FRIEND_WIDGET")
                .putExtra(EXTRA_ID, id).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            remote.setOnClickPendingIntent(R.id.widget_root, PendingIntent.getActivity(context, id, launch,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE))
            manager(context).updateAppWidget(id, remote)
        }
    }
}
