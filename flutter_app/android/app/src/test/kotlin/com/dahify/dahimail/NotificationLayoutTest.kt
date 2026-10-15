package com.dahify.dahimail

import android.app.Application
import android.content.res.Configuration
import android.graphics.Bitmap
import android.graphics.Canvas
import android.view.View
import android.widget.ImageView
import android.widget.RemoteViews
import android.widget.TextView
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import org.robolectric.annotation.GraphicsMode
import java.io.File

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [33], application = Application::class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
class NotificationLayoutTest {
    @Test fun collapsedContentKeepsAvatarAndBadgeInsideAndroids48dpLimit() {
        val application = RuntimeEnvironment.getApplication()
        for (fontScale in listOf(1f, 1.3f)) {
            val config = Configuration(application.resources.configuration).apply { this.fontScale = fontScale }
            val context = application.createConfigurationContext(config)
            val density = context.resources.displayMetrics.density
            for (widthDp in listOf(280, 360)) {
                val compact = RemoteViews(context.packageName, R.layout.notification_message_compact).apply {
                    setTextViewText(R.id.notification_sender, "Alexandra Morgan")
                    setTextViewText(R.id.notification_body, "The new designs are ready for your review.")
                    setTextViewText(R.id.notification_time, "2m ago")
                    setImageViewResource(R.id.notification_type_icon, R.drawable.ic_notify_email)
                    setImageViewResource(R.id.notification_avatar, R.mipmap.ic_launcher)
                }.apply(context, null)
                val width = (widthDp * density).toInt()
                val height = (48 * density).toInt()
                compact.measure(View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY), View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY))
                compact.layout(0, 0, width, height)
                val avatar = compact.findViewById<ImageView>(R.id.notification_avatar)
                val time = compact.findViewById<TextView>(R.id.notification_time)
                val column = avatar.parent as View
                assertTrue("Trailing avatar clipped at fontScale=$fontScale", column.top + avatar.bottom <= height - compact.paddingBottom)
                assertTrue(time.bottom <= avatar.top)
                val badge = compact.findViewById<ImageView>(R.id.notification_type_icon)
                val frame = badge.parent as View
                assertTrue(badge.right <= frame.width && badge.bottom <= frame.height)
                assertTrue(frame.bottom <= height)
                val sender = compact.findViewById<TextView>(R.id.notification_sender)
                val body = compact.findViewById<TextView>(R.id.notification_body)
                val content = body.parent as View
                assertTrue("Body clipped", content.top + body.bottom <= height - compact.paddingBottom)
                assertTrue(sender.bottom <= body.top)
                assertTrue(content.right <= column.left)
                System.getenv("NOTIFICATION_PREVIEW_DIR")?.let { directory ->
                    if (fontScale == 1f && widthDp == 360) {
                        val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
                        Canvas(bitmap).apply { drawColor(android.graphics.Color.WHITE); compact.draw(this) }
                        File(directory).mkdirs()
                        File(directory, "android-compact-content.png").outputStream().use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
                        bitmap.recycle()
                    }
                }
            }
        }
    }
    @Test fun expandedMessageContainsOnlyViewReplyAndRendersTheDeliveredCustomViews() {
        val context = RuntimeEnvironment.getApplication()
        val manager = context.getSystemService(android.app.NotificationManager::class.java)
        manager.cancelAll()
        NativePushReceiver.showAlert(context, mapOf("notification_id" to "layout-preview", "type" to "chat",
            "title" to "New message", "sender_name" to "Alexandra Morgan", "body" to "The new designs are ready for your review.",
            "reply_kind" to "friend", "reply_id" to "2", "recipient_user_id" to "1", "message_id" to "42",
            "sent_at" to "${System.currentTimeMillis() / 1000 - 120}"))
        val notification = manager.activeNotifications.single().notification
        assertEquals(listOf("View", "Reply"), notification.actions.map { it.title.toString() })
        val density = context.resources.displayMetrics.density
        for ((name, remote) in listOf("expanded" to notification.bigContentView, "heads-up" to notification.headsUpContentView)) {
            val view = remote.apply(context, null)
            val width = (360 * density).toInt()
            view.measure(View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY), View.MeasureSpec.makeMeasureSpec((400 * density).toInt(), View.MeasureSpec.AT_MOST))
            view.layout(0, 0, width, view.measuredHeight)
            assertNotNull(view.findViewById<ImageView>(R.id.notification_avatar).drawable)
            assertEquals("Alexandra Morgan", view.findViewById<TextView>(R.id.notification_sender).text)
            System.getenv("NOTIFICATION_PREVIEW_DIR")?.let { directory ->
                val bitmap = Bitmap.createBitmap(width, view.height, Bitmap.Config.ARGB_8888)
                Canvas(bitmap).apply { drawColor(android.graphics.Color.WHITE); view.draw(this) }
                File(directory).mkdirs()
                File(directory, "android-$name.png").outputStream().use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
                bitmap.recycle()
            }
        }
        manager.cancelAll()
    }

}
