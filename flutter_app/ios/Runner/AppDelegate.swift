import Flutter
import workmanager_apple
import flutter_local_notifications
import UIKit
import PushKit
import CallKit
import AVFoundation
import WebRTC

/// iPhone calls that behave like real phone calls:
///  • PushKit (VoIP push from our server) wakes the app even when it is closed / the phone is locked,
///  • CallKit shows the native full-screen call screen (or banner) with Answer / Decline, over every other app,
///  • the call then shows in the status bar (green pill) while the person uses another app.
/// Apple rule: EVERY VoIP push must be reported to CallKit immediately – otherwise iOS stops delivering them. handleVoipPush always does.
/// Talks to Dart through the channel "com.dahify.dahimail/callkit" (lib/core/callkit.dart).
@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate, PKPushRegistryDelegate, CXProviderDelegate {
  private var channel: FlutterMethodChannel?
  private var voipRegistry: PKPushRegistry?
  private var provider: CXProvider?
  private let callController = CXCallController()
  private var voipToken: String?
  private var calls: [UUID: [String: Any]] = [:]   // every call CallKit currently knows (ringing or answered)
  private var answered = Set<UUID>()
  private var pendingEvents: [[String: Any]] = []  // events that happened before Flutter was running (app was closed)
  private var dartReady = false
  private var callTone: AVAudioPlayer?

  #if DEBUG
  private let isSandbox = true
  #else
  private let isSandbox = false
  #endif

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    WorkmanagerPlugin.setPluginRegistrantCallback { registry in
      GeneratedPluginRegistrant.register(with: registry)
    }
    WorkmanagerPlugin.registerPeriodicTask(withIdentifier: "com.dahify.dahimail.refresh", earliestBeginInSeconds: 900)
    FlutterLocalNotificationsPlugin.setPluginRegistrantCallback { registry in
      GeneratedPluginRegistrant.register(with: registry)
    }
    UNUserNotificationCenter.current().delegate = self
    // Register before Dart starts, including launches caused by remote actions.
    let view = UNNotificationAction(identifier: "notification_view", title: "View", options: [.authenticationRequired])
    let reply = UNTextInputNotificationAction(identifier: "notification_reply", title: "Reply", options: [.authenticationRequired], textInputButtonTitle: "Send", textInputPlaceholder: "Write a reply")
    UNUserNotificationCenter.current().setNotificationCategories([
      UNNotificationCategory(identifier: "MESSAGE_VIEW", actions: [view], intentIdentifiers: [], options: []),
      UNNotificationCategory(identifier: "MESSAGE_REPLY", actions: [view, reply], intentIdentifiers: [], options: [])
    ])
    setupCallKit()
    setupPushKit()
    application.registerForRemoteNotifications() // APNs token for normal pushes (needed before Firebase can give a token)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    let share = FlutterMethodChannel(name: "com.dahify.dahimail/share", binaryMessenger: engineBridge.applicationRegistrar.messenger())
    share.setMethodCallHandler { [weak self] call, result in
      guard call.method == "shareText" else { result(FlutterMethodNotImplemented); return }
      let activeWindow = UIApplication.shared.connectedScenes
        .compactMap { $0 as? UIWindowScene }
        .filter { $0.activationState == .foregroundActive }
        .flatMap { $0.windows }.first { $0.isKeyWindow }
      guard let args = call.arguments as? [String: Any], let text = args["text"] as? String,
            var presenter = activeWindow?.rootViewController ?? self?.window?.rootViewController else {
        result(FlutterError(code: "share_failed", message: "Sharing is unavailable", details: nil)); return
      }
      while let presented = presenter.presentedViewController { presenter = presented }
      let sheet = UIActivityViewController(activityItems: [text], applicationActivities: nil)
      sheet.popoverPresentationController?.sourceView = presenter.view
      sheet.popoverPresentationController?.sourceRect = CGRect(x: presenter.view.bounds.midX, y: presenter.view.bounds.midY, width: 1, height: 1)
      presenter.present(sheet, animated: true)
      result(nil)
    }
    let tones = FlutterMethodChannel(name: "com.dahify.dahimail/call_tones", binaryMessenger: engineBridge.applicationRegistrar.messenger())
    tones.setMethodCallHandler { [weak self] call, result in
      guard let self = self else { result(nil); return }
      switch call.method {
      case "dialing", "connected":
        self.callTone?.stop()
        self.callTone = nil
        let connected = call.method == "connected"
        let key = FlutterDartProject.lookupKey(forAsset: "assets/sounds/\(connected ? "connected" : "dialing").wav")
        if let path = Bundle.main.path(forResource: key, ofType: nil) {
          do {
            // CallKit owns activation for an answered system call. For an
            // outgoing call, activate the existing WebRTC recording session.
            if self.calls.isEmpty { try AVAudioSession.sharedInstance().setActive(true) }
            let player = try AVAudioPlayer(contentsOf: URL(fileURLWithPath: path))
            player.numberOfLoops = connected ? 0 : -1
            player.volume = 0.6
            self.callTone = player
            player.play()
          } catch { self.callTone = nil }
        }
        result(nil)
      case "stop":
        self.callTone?.stop()
        self.callTone = nil
        result(nil)
      default: result(FlutterMethodNotImplemented)
      }
    }
    let ch = FlutterMethodChannel(name: "com.dahify.dahimail/callkit", binaryMessenger: engineBridge.applicationRegistrar.messenger())
    ch.setMethodCallHandler { [weak self] call, result in self?.handle(call, result) }
    channel = ch
  }

  // MARK: - setup

  private func setupCallKit() {
    let cfg = CXProviderConfiguration(localizedName: "Dahimail")
    cfg.supportsVideo = true
    cfg.maximumCallsPerCallGroup = 1
    cfg.supportedHandleTypes = [.generic]
    cfg.includesCallsInRecents = false
    let p = CXProvider(configuration: cfg)
    p.setDelegate(self, queue: nil)
    provider = p
  }

  private func setupPushKit() {
    let r = PKPushRegistry(queue: DispatchQueue.main)
    r.delegate = self
    r.desiredPushTypes = [.voIP]
    voipRegistry = r
  }

  // MARK: - Dart ⇄ native

  private func emit(_ event: String, _ args: [String: Any]) {
    if dartReady, let ch = channel {
      DispatchQueue.main.async { ch.invokeMethod(event, arguments: args) }
    } else {
      pendingEvents.append(["event": event, "args": args])
    }
  }

  private func handle(_ call: FlutterMethodCall, _ result: @escaping FlutterResult) {
    let a = call.arguments as? [String: Any] ?? [:]
    switch call.method {
    case "ready":
      dartReady = true
      let events = pendingEvents
      pendingEvents = []
      let tok: Any = (voipToken != nil) ? (voipToken as Any) : (NSNull() as Any)
      result(["events": events, "token": tok, "sandbox": isSandbox])
    case "endAll":                       // the call is over in the app (hang up, room closed)
      for (id, _) in calls { finish(id, reason: .remoteEnded) }
      result(true)
    case "endByCallId":                  // the server says: caller hung up / answered elsewhere / missed
      let cid = (a["callId"] as? NSNumber)?.intValue ?? -1
      let reason = a["reason"] as? String ?? ""
      for (id, info) in calls where (info["call_id"] as? Int) == cid {
        if reason == "answered" && answered.contains(id) { continue } // THIS phone answered – keep the call
        finish(id, reason: reason == "declined" ? .declinedElsewhere : (reason == "answered" ? .answeredElsewhere : .unanswered))
      }
      result(true)
    case "syncRinging":                  // ring screens that the server no longer knows about must disappear
      let active = Set((a["activeIds"] as? [NSNumber] ?? []).map { $0.intValue })
      for (id, info) in calls where !answered.contains(id) {
        if let cid = info["call_id"] as? Int, !active.contains(cid) { finish(id, reason: .unanswered) }
      }
      result(true)
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  /// Ends a call on the system screen: a connected call needs an end-request, a ringing one is "reported ended".
  private func finish(_ id: UUID, reason: CXCallEndedReason) {
    if answered.contains(id) {
      callController.request(CXTransaction(action: CXEndCallAction(call: id))) { _ in }
    } else {
      provider?.reportCall(with: id, endedAt: Date(), reason: reason)
      calls[id] = nil
    }
  }

  // MARK: - PushKit

  func pushRegistry(_ registry: PKPushRegistry, didUpdate pushCredentials: PKPushCredentials, for type: PKPushType) {
    guard type == .voIP else { return }
    let token = pushCredentials.token.map { String(format: "%02x", $0) }.joined()
    voipToken = token
    emit("voipToken", ["token": token, "sandbox": isSandbox])
  }

  func pushRegistry(_ registry: PKPushRegistry, didInvalidatePushTokenFor type: PKPushType) {
    guard type == .voIP else { return }
    voipToken = nil
    emit("voipToken", ["token": "", "sandbox": isSandbox])
  }

  func pushRegistry(_ registry: PKPushRegistry, didReceiveIncomingPushWith payload: PKPushPayload, for type: PKPushType, completion: @escaping () -> Void) {
    guard type == .voIP else { completion(); return }
    handleVoipPush(payload.dictionaryPayload, completion: completion)
  }

  private func str(_ v: Any?) -> String {
    if let s = v as? String { return s }
    if let n = v as? NSNumber { return n.stringValue }
    return ""
  }

  private func handleVoipPush(_ d: [AnyHashable: Any], completion: @escaping () -> Void) {
    let uuid = UUID(uuidString: str(d["uuid"])) ?? UUID()
    let name = str(d["caller_name"]).isEmpty ? "Dahimail" : str(d["caller_name"])
    let isVideo = str(d["video"]) == "1" && str(d["audio_only"]) != "1"
    let sent = Double(str(d["sent_at"])) ?? 0
    let ttl = min(45, max(0, Double(str(d["ttl"])) ?? 45))
    let remaining = min(ttl, sent > 0 ? ttl - (Date().timeIntervalSince1970 - sent) : ttl)
    let stale = remaining <= 0   // the phone was off / offline: a "ghost" call

    let info: [String: Any] = [
      "call_id": Int(str(d["call_id"])) ?? -1,
      "uuid": uuid.uuidString,
      "caller_id": Int(str(d["caller_id"])) ?? -1,
      "caller_name": name,
      "meeting": str(d["meeting"]),
      "video": str(d["video"]) == "1",
      "audio_only": str(d["audio_only"]) == "1",
      "group": str(d["group"]) == "1",
    ]
    let update = CXCallUpdate()
    update.remoteHandle = CXHandle(type: .generic, value: name)
    update.localizedCallerName = name
    update.hasVideo = isVideo
    update.supportsHolding = false
    update.supportsGrouping = false
    update.supportsUngrouping = false
    update.supportsDTMF = false
    // APNs may redeliver the same invitation. Still report every VoIP push,
    // but a duplicate-report error must not erase the original active call.
    let duplicate = calls[uuid] != nil
    if !duplicate { calls[uuid] = info }
    guard let provider = provider else { completion(); return }

    provider.reportNewIncomingCall(with: uuid, update: update) { [weak self] error in
      guard let self = self else { completion(); return }
      defer { completion() }
      if duplicate { return }
      if error != nil {
        self.calls[uuid] = nil           // e.g. Do Not Disturb filtered it, or the same call was reported twice
      } else if stale {
        self.provider?.reportCall(with: uuid, endedAt: Date(), reason: .unanswered)
        self.calls[uuid] = nil
      } else {
        self.emit("ringing", info)
        // safety: a ring nobody answers must not stay on screen forever (the server also sends a cancel push)
        DispatchQueue.main.asyncAfter(deadline: .now() + max(0, remaining)) { [weak self] in
          guard let self = self, self.calls[uuid] != nil, !self.answered.contains(uuid) else { return }
          self.provider?.reportCall(with: uuid, endedAt: Date(), reason: .unanswered)
          self.calls[uuid] = nil
          self.emit("callEnded", info.merging(["answered": false, "timeout": true]) { $1 })
        }
      }
    }
  }

  // MARK: - silent push from the server: "caller hung up" / "answered on another device"

  override func application(
    _ application: UIApplication,
    didReceiveRemoteNotification userInfo: [AnyHashable: Any],
    fetchCompletionHandler completionHandler: @escaping (UIBackgroundFetchResult) -> Void
  ) {
    if str(userInfo["type"]) == "call_cancel" {
      let cid = Int(str(userInfo["call_id"])) ?? -1
      let reason = str(userInfo["reason"])
      for (id, info) in calls where (info["call_id"] as? Int) == cid {
        if reason == "answered" && answered.contains(id) { continue }
        finish(id, reason: reason == "declined" ? .declinedElsewhere : (reason == "answered" ? .answeredElsewhere : .unanswered))
        emit("callEnded", info.merging(["answered": false, "remote": true]) { $1 })
      }
    }
    super.application(application, didReceiveRemoteNotification: userInfo, fetchCompletionHandler: completionHandler)
  }

  // MARK: - CallKit

  func providerDidReset(_ provider: CXProvider) {
    calls.removeAll()
    answered.removeAll()
  }

  func provider(_ provider: CXProvider, perform action: CXAnswerCallAction) {
    do {
      try AVAudioSession.sharedInstance().setCategory(.playAndRecord, mode: .voiceChat, options: [.allowBluetooth, .allowBluetoothA2DP])
    } catch {
      action.fail()
      return
    }
    answered.insert(action.callUUID)
    emit("callAnswered", calls[action.callUUID] ?? [:])
    action.fulfill()
  }

  func provider(_ provider: CXProvider, perform action: CXEndCallAction) {
    let info = calls[action.callUUID] ?? [:]
    let wasAnswered = answered.contains(action.callUUID)
    calls[action.callUUID] = nil
    answered.remove(action.callUUID)
    emit("callEnded", info.merging(["answered": wasAnswered]) { $1 })
    action.fulfill()
  }

  func provider(_ provider: CXProvider, perform action: CXSetMutedCallAction) {
    emit("muted", (calls[action.callUUID] ?? [:]).merging(["muted": action.isMuted]) { $1 })
    action.fulfill()
  }

  func provider(_ provider: CXProvider, didActivate audioSession: AVAudioSession) {
    RTCAudioSession.sharedInstance().audioSessionDidActivate(audioSession)
    RTCAudioSession.sharedInstance().isAudioEnabled = true
    emit("audioActive", [:])
  }

  func provider(_ provider: CXProvider, didDeactivate audioSession: AVAudioSession) {
    RTCAudioSession.sharedInstance().audioSessionDidDeactivate(audioSession)
  }
}
