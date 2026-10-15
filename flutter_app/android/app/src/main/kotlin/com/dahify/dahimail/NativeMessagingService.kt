package com.dahify.dahimail

import com.google.firebase.messaging.RemoteMessage
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService

/**
 * Firebase starts/binds this service for data messages even with no UI process.
 * Post the incoming-call notification first; only then start Flutter for syncing.
 * Inheriting FlutterFire preserves its onNewToken registration callback.
 */
open class NativeMessagingService : FlutterFirebaseMessagingService() {
    protected open fun receiver(): NativePushReceiver = NativePushReceiver()

    override fun onMessageReceived(message: RemoteMessage) {
        // Legacy notification payloads are handled by Firebase/our receiver.
        // Current server calls and Android v2 alerts are always data-only.
        if (message.notification != null) return
        receiver().handleMessage(applicationContext, message.toIntent())
    }
}