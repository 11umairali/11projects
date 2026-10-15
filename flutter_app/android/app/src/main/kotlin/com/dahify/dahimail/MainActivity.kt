package com.dahify.dahimail

import android.app.PictureInPictureParams
import android.content.Intent
import android.content.res.Configuration
import android.net.Uri
import android.util.Rational
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import java.util.TimeZone
import org.json.JSONObject
import io.flutter.plugin.common.MethodChannel
import android.os.Handler
import android.os.Looper
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.engine.renderer.FlutterUiDisplayListener

// FlutterFragmentActivity (not FlutterActivity) is required by the fingerprint / face prompt (local_auth).
class MainActivity : FlutterFragmentActivity() {
    companion object { @Volatile var isResumed = false }
    override fun onResume() { super.onResume(); isResumed = true }
    override fun onPause() { isResumed = false; DeviceSounds.stopRingtone(); super.onPause() }

    @Volatile private var uiReady = false
    private var launchChannel: MethodChannel? = null
    private var launchReady = false
    private var callChannel: MethodChannel? = null
    private var widgetChannel: MethodChannel? = null
    private var widgetReady = false
    private var pendingWidgetAction: Map<String, Any>? = null
    /** Dart sets this while any call or meeting is running: pressing Home then shrinks the call into Picture-in-Picture (like WhatsApp). */
    private var pipArmed = false
    private val callRecorder by lazy { CallAudioRecorder(applicationContext) }

    override fun onUserLeaveHint() {
        super.onUserLeaveHint()
        if (pipArmed && Build.VERSION.SDK_INT >= 26) {
            try { enterPictureInPictureMode(PictureInPictureParams.Builder().setAspectRatio(Rational(16, 9)).build()) } catch (_: Exception) {}
        }
    }

    override fun onPictureInPictureModeChanged(isInPictureInPictureMode: Boolean, newConfig: Configuration) {
        super.onPictureInPictureModeChanged(isInPictureInPictureMode, newConfig)
        callChannel?.invokeMethod("pip", isInPictureInPictureMode)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        val accepting = intent?.getStringExtra("actionId") == "call_accept"
        if (accepting) setTheme(R.style.CallLaunchTheme)
        val splash = installSplashScreen()
        super.onCreate(savedInstanceState)
        if (accepting) setTheme(R.style.CallTheme)
        // ONE splash: keep it until Flutter has drawn its first real screen (the app holds that frame back until it is ready).
        splash.setKeepOnScreenCondition { !uiReady }
        showOverLockscreenForCall(intent)
        // Safety net: release the launch surface promptly, whatever happens.
        Handler(Looper.getMainLooper()).postDelayed({ uiReady = true }, 700)
    }

    // A call notification (full-screen intent) opens the app on top of the lock screen, like a normal phone call.
    private fun showOverLockscreenForCall(i: Intent?) {
        if (i?.getStringExtra("actionId") in listOf("call_accept", "call_decline")) {
            i?.getStringExtra("payload")?.substringAfter("call_incoming:")?.toIntOrNull()?.let { NativePushReceiver.dismissCall(this, it) }
        }
        if (Build.VERSION.SDK_INT >= 27) {
            val incomingCall = i?.getStringExtra("payload")?.startsWith("call_incoming:") == true
            setShowWhenLocked(incomingCall)
            setTurnScreenOn(incomingCall)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        showOverLockscreenForCall(intent)
        if (launchReady) acceptedCall(intent)?.let { launchChannel?.invokeMethod("accepted", it) }
        val id = intent.getIntExtra(FriendChatWidget.EXTRA_ID, -1)
        intent.removeExtra(FriendChatWidget.EXTRA_ID)
        val action = FriendChatWidget.action(this, id) ?: return
        if (widgetReady) widgetChannel?.invokeMethod("open", action)
        else pendingWidgetAction = action
    }

    private fun acceptedCall(i: Intent?): Map<String, String>? {
        if (i?.getStringExtra("actionId") != "call_accept") return null
        val id = i.getStringExtra("payload")?.substringAfter("call_incoming:")?.toIntOrNull() ?: return null
        val json = try { JSONObject(i.getStringExtra("call_data") ?: "{}") } catch (_: Exception) { JSONObject() }
        return json.keys().asSequence().associateWith { json.optString(it) } + ("call_id" to id.toString())
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        launchChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/call_launch").also { ch ->
            ch.setMethodCallHandler { call, result ->
                if (call.method == "initialAcceptedCall") {
                    launchReady = true
                    result.success(acceptedCall(intent))
                } else result.notImplemented()
            }
        }
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/call_tones").setMethodCallHandler { call, result ->
            when (call.method) {
                "dialing" -> { DeviceSounds.playCallTone(this, false); result.success(null) }
                "connected" -> { DeviceSounds.playCallTone(this, true); result.success(null) }
                "stop" -> { DeviceSounds.stopCallTone(); result.success(null) }
                else -> result.notImplemented()
            }
        }
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/share").setMethodCallHandler { call, result ->
            if (call.method != "shareText") { result.notImplemented() }
            else {
                try {
                    val share = Intent(Intent.ACTION_SEND).apply {
                        type = "text/plain"
                        putExtra(Intent.EXTRA_TEXT, call.argument<String>("text") ?: "")
                    }
                    startActivity(Intent.createChooser(share, "Invite to Dahimail"))
                    result.success(null)
                } catch (e: Exception) { result.error("share_failed", "Unable to open sharing apps", null) }
            }
        }
        widgetChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/friend_widget").also { ch ->
            ch.setMethodCallHandler { call, result ->
                try {
                    when (call.method) {
                        "initialAction" -> {
                            widgetReady = true
                            val id = intent.getIntExtra(FriendChatWidget.EXTRA_ID, -1)
                            intent.removeExtra(FriendChatWidget.EXTRA_ID)
                            val action = pendingWidgetAction ?: FriendChatWidget.action(this, id)
                            pendingWidgetAction = null
                            result.success(action)
                        }
                        "account" -> { FriendChatWidget.account(this, (call.arguments as? Number)?.toInt() ?: 0); result.success(null) }
                        "clear" -> { FriendChatWidget.clear(this); result.success(null) }
                        "pin", "configure" -> {
                            val owner = call.argument<Number>("owner_id")?.toInt() ?: 0
                            val friend = call.argument<Number>("friend_id")?.toInt() ?: 0
                            val name = call.argument<String>("name") ?: ""
                            if (call.method == "pin") result.success(FriendChatWidget.pin(this, owner, friend, name, call.argument<ByteArray>("avatar")))
                            else {
                                FriendChatWidget.configure(this, call.argument<Number>("widget_id")?.toInt() ?: -1, owner, friend, name, call.argument<ByteArray>("avatar"))
                                result.success(null)
                            }
                        }
                        else -> result.notImplemented()
                    }
                } catch (error: Exception) { result.error("friend_widget", error.message, null) }
            }
        }
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/recording").setMethodCallHandler { call, result ->
            try {
                val args = call.arguments as? Map<*, *> ?: emptyMap<Any, Any>()
                val tracks = (args["tracks"] as? List<*>)?.mapNotNull { raw ->
                    (raw as? Map<*, *>)?.entries?.associate { it.key.toString() to it.value.toString() }
                } ?: emptyList()
                when (call.method) {
                    "available" -> result.success(true)
                    "start" -> result.success(callRecorder.start(tracks, (args["max_mb"] as? Number)?.toInt() ?: 100))
                    "tracks" -> { callRecorder.update(tracks); result.success(callRecorder.status()) }
                    "stop" -> result.success(callRecorder.stop())
                    else -> result.notImplemented()
                }
            } catch (error: Exception) { result.error("recording", error.message, null) }
        }
        // Facts read straight from the phone (used by lib/core/device_env.dart)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/device").setMethodCallHandler { call, result ->
            when (call.method) {
                "startDefaultRingtone" -> { DeviceSounds.startRingtone(this); result.success(null) }
                "stopDefaultRingtone" -> { DeviceSounds.stopRingtone(); result.success(null) }
                "playDefaultNotification" -> { DeviceSounds.playNotification(this); result.success(null) }
                "dismissIncomingCall" -> {
                    val id = (call.arguments as? Number)?.toInt()
                    if (id != null && id > 0) NativePushReceiver.dismissCall(this, id)
                    result.success(null)
                }
                "showIncomingCall" -> {
                    val values = (call.arguments as? Map<*, *>)?.entries?.associate { it.key.toString() to it.value.toString() } ?: emptyMap()
                    try { result.success(NativePushReceiver.showCall(this, values)) }
                    catch (_: Exception) { result.success(false) }
                }
                "showSystemAlert" -> {
                    val values = (call.arguments as? Map<*, *>)?.entries?.associate { it.key.toString() to it.value.toString() } ?: emptyMap()
                    try { result.success(NativePushReceiver.showAlert(this, values)) }
                    catch (_: Exception) { result.success(false) }
                }
                "timezone" -> result.success(TimeZone.getDefault().id)
                "notificationDiagnostics" -> result.success(NativePushReceiver.diagnostics(this))
                "lastNativePushAt" -> result.success(getSharedPreferences("native_push", MODE_PRIVATE).getLong("last_received", 0))
                "canFullScreenIntent" -> {
                    val nm = getSystemService(android.app.NotificationManager::class.java)
                    result.success(if (Build.VERSION.SDK_INT >= 34) nm.canUseFullScreenIntent() else true)
                }
                "openFullScreenIntentSettings" -> {
                    try {
                        if (Build.VERSION.SDK_INT >= 34) {
                            val i = Intent(Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT, android.net.Uri.parse("package:" + packageName))
                            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                            startActivity(i)
                        }
                        result.success(true)
                    } catch (e: Exception) { result.success(false) }
                }
                "openNotificationSettings" -> {
                    try {
                        val i = Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, packageName)
                        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        startActivity(i)
                        result.success(true)
                    } catch (e: Exception) { result.success(false) }
                }
                else -> result.notImplemented()
            }
        }
        // Floating call window, Picture-in-Picture and battery settings (lib/core/native_calls.dart)
        callChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.dahify.dahimail/call").also { ch ->
            CallOverlayService.listener = { action -> runOnUiThread { ch.invokeMethod("overlayAction", action) } }
            ch.setMethodCallHandler { call, result ->
                when (call.method) {
                    "overlayCanDraw" -> result.success(CallOverlayService.canDraw(this))
                    "overlayRequest" -> {
                        try {
                            val i = Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:" + packageName))
                            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK); startActivity(i); result.success(true)
                        } catch (e: Exception) { result.success(false) }
                    }
                    "overlayShow" -> {
                        CallOverlayService.show(this, call.argument<String>("title") ?: "Call", (call.argument<Number>("since")?.toLong() ?: 0L), call.argument<Boolean>("muted") ?: false)
                        result.success(true)
                    }
                    "overlayUpdate" -> {
                        CallOverlayService.update(call.argument<String>("title") ?: "Call", (call.argument<Number>("since")?.toLong() ?: 0L), call.argument<Boolean>("muted") ?: false)
                        result.success(true)
                    }
                    "overlayHide" -> { CallOverlayService.hide(this); result.success(true) }
                    "pipArm" -> { pipArmed = call.argument<Boolean>("on") ?: false; result.success(true) }
                    "openBatterySettings" -> {
                        try {
                            // The generic list is easy to miss and does not help users configure this app.
                            // Open the app-specific exemption page first; some OEMs reject it, so retain a
                            // deterministic fallback to the app details / generic battery list.
                            val direct = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                                Uri.parse("package:$packageName"))
                            startActivity(direct)
                            result.success(true)
                        } catch (e: Exception) {
                            try {
                                val app = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                                    Uri.parse("package:$packageName"))
                                startActivity(app)
                                result.success(true)
                            } catch (e2: Exception) {
                                try {
                                    startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS))
                                    result.success(true)
                                } catch (e3: Exception) { result.success(false) }
                            }
                        }
                    }
                    else -> result.notImplemented()
                }
            }
        }
        flutterEngine.renderer.addIsDisplayingFlutterUiListener(object : FlutterUiDisplayListener {
            override fun onFlutterUiDisplayed() { uiReady = true }
            override fun onFlutterUiNoLongerDisplayed() {}
        })
    }
}
