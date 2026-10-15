package com.dahify.dahimail

import android.app.Application
import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.graphics.Bitmap
import android.widget.ImageView
import android.view.View
import java.io.ByteArrayOutputStream
import android.widget.TextView
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [33], application = Application::class)
class FriendChatWidgetTest {
    private lateinit var context: Context
    private lateinit var manager: AppWidgetManager

    @Before fun setup() {
        context = RuntimeEnvironment.getApplication()
        manager = AppWidgetManager.getInstance(context)
        FriendChatWidget.clear(context)
    }

    private fun add(): Int = shadowOf(manager).createWidgets(FriendChatWidget::class.java, R.layout.friend_chat_widget, 1)[0]

    private fun label(id: Int): String = shadowOf(manager).getViewFor(id).findViewById<TextView>(R.id.widget_name).text.toString()

    @Test fun `each widget opens its own friend with the owning account`() {
        val first = add()
        val second = add()
        FriendChatWidget.configure(context, first, 7, 11, "William")
        FriendChatWidget.configure(context, second, 7, 12, "Sara")
        assertEquals("William", label(first))
        assertEquals("Sara", label(second))
        assertEquals(mapOf("type" to "home_friend_chat", "from_id" to 11, "recipient_user_id" to 7), FriendChatWidget.action(context, first))
        assertEquals(12, FriendChatWidget.action(context, second)?.get("from_id"))
        // The actual launcher tap carries only the widget ID; it resolves current local state.
        shadowOf(manager).getViewFor(first).findViewById<android.view.View>(R.id.widget_root).performClick()
        val launch = shadowOf(context as Application).nextStartedActivity
        assertEquals(first, launch.getIntExtra(FriendChatWidget.EXTRA_ID, -1))
        assertNull(launch.data) // Flutter's automatic URL router must not consume this tap.
    }

    @Test fun `an unconfigured tile opens friend selection`() {
        val id = add()
        assertEquals(mapOf("type" to "home_friend_setup", "widget_id" to id), FriendChatWidget.action(context, id))
        assertEquals("Choose a friend", label(id))
    }

    @Test fun `signout removes names destinations and pending pin selections`() {
        val id = add()
        FriendChatWidget.configure(context, id, 7, 11, "William")
        val prefs = context.getSharedPreferences("friend_widgets", Context.MODE_PRIVATE)
        prefs.edit().putString("pending_test", "private selection").commit()
        FriendChatWidget.clear(context)
        assertEquals("Choose a friend", label(id))
        assertEquals("home_friend_setup", FriendChatWidget.action(context, id)?.get("type"))
        assertTrue(prefs.all.isEmpty())
    }

    @Test fun `switching accounts clears the old friend`() {
        val id = add()
        FriendChatWidget.configure(context, id, 7, 11, "William")
        FriendChatWidget.account(context, 8)
        assertEquals("Choose a friend", label(id))
        assertEquals("home_friend_setup", FriendChatWidget.action(context, id)?.get("type"))
    }

    @Test fun `pin callback assigns the selected friend to the returned widget id only once`() {
        val id = add()
        FriendChatWidget.account(context, 7)
        val prefs = context.getSharedPreferences("friend_widgets", Context.MODE_PRIVATE)
        prefs.edit().putString("pending_test", JSONObject().put("owner_id", 7).put("friend_id", 11).put("name", "William").toString()).commit()
        val intent = Intent(context, FriendChatWidget::class.java).setAction(FriendChatWidget.PINNED)
            .setData(Uri.parse("dahimail-widget://pin/test")).putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, id)
        FriendChatWidget().onReceive(context, intent)
        assertEquals("William", label(id))
        assertFalse(prefs.contains("pending_test"))
        FriendChatWidget.clear(context)
        FriendChatWidget().onReceive(context, intent)
        assertEquals("Choose a friend", label(id))
    }

    @Test fun `photo replaces the initial and the larger tile preserves the full name`() {
        val id = add()
        val image = Bitmap.createBitmap(256, 256, Bitmap.Config.ARGB_8888)
        image.eraseColor(android.graphics.Color.BLUE)
        val bytes = ByteArrayOutputStream().also { image.compress(Bitmap.CompressFormat.PNG, 100, it) }.toByteArray()
        val name = "William Alexander Robertson"
        FriendChatWidget.configure(context, id, 7, 11, name, bytes)
        val view = shadowOf(manager).getViewFor(id)
        val photo = view.findViewById<ImageView>(R.id.widget_photo)
        assertEquals(View.VISIBLE, photo.visibility)
        assertNotNull(photo.drawable)
        assertEquals(View.GONE, view.findViewById<TextView>(R.id.widget_initial).visibility)
        val label = view.findViewById<TextView>(R.id.widget_name)
        assertEquals(name, label.text.toString())
        assertEquals(3, label.maxLines)
        assertEquals(View.GONE, view.findViewById<TextView>(R.id.widget_hint).visibility)
        assertEquals((76 * context.resources.displayMetrics.density).toInt(), photo.parent.let { (it as View).layoutParams.width })
        FriendChatWidget.clear(context)
        val cleared = shadowOf(manager).getViewFor(id)
        assertEquals(View.GONE, cleared.findViewById<ImageView>(R.id.widget_photo).visibility)
        assertNull(cleared.findViewById<ImageView>(R.id.widget_photo).drawable)
        image.recycle()
    }

    @Test fun `invalid oversized and missing photos use a large initial`() {
        val id = add()
        for (bytes in listOf(null, byteArrayOf(1, 2, 3), ByteArray(300_001))) {
            FriendChatWidget.configure(context, id, 7, 11, "William", bytes)
            val view = shadowOf(manager).getViewFor(id)
            assertEquals(View.GONE, view.findViewById<ImageView>(R.id.widget_photo).visibility)
            assertEquals(View.VISIBLE, view.findViewById<TextView>(R.id.widget_initial).visibility)
            assertEquals("W", view.findViewById<TextView>(R.id.widget_initial).text.toString())
        }
    }

    @Test fun `deleted and unknown widgets cannot launch or receive a destination`() {
        val id = add()
        FriendChatWidget.configure(context, id, 7, 11, "William")
        FriendChatWidget().onDeleted(context, intArrayOf(id))
        assertFalse(context.getSharedPreferences("friend_widgets", Context.MODE_PRIVATE).contains("widget_$id"))
        assertNull(FriendChatWidget.action(context, 99999))
        try {
            FriendChatWidget.configure(context, 99999, 7, 11, "William")
            fail("Unknown widget should be rejected")
        } catch (_: IllegalArgumentException) {}
    }
}
