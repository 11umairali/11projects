package com.dahify.dahimail

import android.app.Application
import android.app.Notification
import android.app.NotificationManager
import android.graphics.Bitmap
import android.graphics.Canvas
import android.view.View
import android.view.ViewGroup
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
    private fun children(view: View): List<View> = listOf(view) + if (view is ViewGroup)
        (0 until view.childCount).flatMap { children(view.getChildAt(it)) } else emptyList()

    @Test fun referenceCardRendersBrandSenderTimeAvatarAndNativeInlineActions() {
        android.os.SystemClock.setCurrentTimeMillis(System.currentTimeMillis())
        val context = RuntimeEnvironment.getApplication()
        val manager = context.getSystemService(NotificationManager::class.java)
        manager.cancelAll()
        context.getSharedPreferences("native_push", 0).edit().clear().commit()
        NativePushReceiver.showAlert(context, mapOf("notification_id" to "native-message-layout", "type" to "chat",
            "title" to "New message", "sender_name" to "Alexandra Morgan", "body" to "The new designs are ready for your review.",
            "reply_kind" to "friend", "reply_id" to "2", "recipient_user_id" to "1", "message_id" to "42",
            "sent_at" to "${System.currentTimeMillis() / 1000 - 120}"))
        val notification = manager.activeNotifications.single().notification
        assertNotNull(notification.contentView)
        assertNotNull(notification.bigContentView)
        assertNotNull(notification.headsUpContentView)
        assertTrue(notification.extras.getString(Notification.EXTRA_TEMPLATE)?.contains("DecoratedCustomViewStyle") == true)
        assertEquals(listOf("View", "Reply"), notification.actions.map { it.title.toString() })
        val framework = Notification.Builder.recoverBuilder(context, notification)
        val density = context.resources.displayMetrics.density
        for ((name, remote) in listOf("expanded" to framework.createBigContentView(), "heads-up" to framework.createHeadsUpContentView())) {
            assertNotNull(remote)
            val view = remote.apply(context, null)
            val width = (360 * density).toInt()
            view.measure(View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY), View.MeasureSpec.makeMeasureSpec((500 * density).toInt(), View.MeasureSpec.AT_MOST))
            view.layout(0, 0, width, view.measuredHeight)
            val text = children(view).filterIsInstance<TextView>().filter { it.visibility == View.VISIBLE }.map { it.text.toString() }
            assertTrue("System View action not rendered: $text", text.contains("View"))
            assertTrue("System Reply action not rendered: $text", text.contains("Reply"))
            assertEquals(1, children(view).count { it.id == R.id.notification_brand })
            assertTrue(text.contains("Alexandra Morgan"))
            val avatar = view.findViewById<View>(R.id.notification_avatar)
            val time = view.findViewById<View>(R.id.notification_time)
            assertNotNull(avatar)
            assertNotNull(time)
            assertTrue(avatar.top >= time.bottom)
            assertTrue(text.contains("2m ago"))
            System.getenv("NOTIFICATION_PREVIEW_DIR")?.let { directory ->
                val bitmap = Bitmap.createBitmap(width, view.height, Bitmap.Config.ARGB_8888)
                Canvas(bitmap).apply { drawColor(android.graphics.Color.WHITE); view.draw(this) }
                File(directory).mkdirs()
                File(directory, "android-reference-$name.png").outputStream().use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
                bitmap.recycle()
            }
        }
    }
}
