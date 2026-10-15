import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'api.dart';
import 'remote_media.dart';
import 'realtime.dart';
import 'rtc_diagnostics.dart';
import 'meeting_recording.dart';
import 'app_permissions.dart';
import 'call_audio.dart';
import 'call_tones.dart';
import 'rtc_local_tracks.dart';

typedef MJson = Map<String, dynamic>;

/// One other person in the room: the WebRTC connection, their video and what the server says about them.
class MeetingPeer {
  final int pid;
  RTCPeerConnection? pc;
  final RTCVideoRenderer renderer = RTCVideoRenderer();
  bool rendererReady = false, remoteSet = false, hasStream = false;
  /// true when this side sends the offer (its tracks are added up front); false when it answers (tracks attach after the offer).
  bool offerer = false;
  Timer? statsTimer;
  bool statsBusy = false;
  final remoteMedia = RemoteMedia();
  bool get hasVideo => remoteMedia.hasVideo;
  final queue = <RTCIceCandidate>[];
  MeetingPeer(this.pid);
}

/// A meeting room (several people, video + audio). Every person connects directly to every other person (a "mesh"), which is
/// good for about 6–8 people. The server only carries the set-up messages (offer / answer / candidates) and the room state.
/// The website room (/meet/code) uses the same endpoints, so app and website users can sit in the same meeting.
class MeetingEngine extends ChangeNotifier {
  final Api api;
  final String code;
  MeetingEngine(this.api, this.code);

  /// idle | joining | waiting | in | ended | error
  String phase = 'idle';
  bool terminationPending = false;
  String? message; // why we ended / the error
  MJson meeting = {};
  int pid = 0;
  String role = 'participant';
  List<MJson> participants = [];
  List<MJson> waiting = [];
  final chat = <MJson>[];
  int unreadChat = 0;
  bool chatOpen = false;
  bool micOn = true, camOn = true, hand = false, frontCamera = true;
  final audio = CallAudio();
  final _tones = CallTones();
  bool get speaker => audio.speaker;
  bool _mediaConnected = false;
  String get callProgress => _mediaConnected ? 'Connected' : _accepted ? 'Accepted · Connecting audio…' : 'Calling…';

  // ───── recording (consent and delivery controlled by the server) ─────
  late final _recorder = MeetingRecording(api, code, (text) { recNotice = text; if (!_disposed) notifyListeners(); });
  bool _disposed = false;
  List<Map<String, String>> get _recordingTracks => [
    for (final track in _local?.getAudioTracks() ?? <MediaStreamTrack>[]) {'id': track.id!, 'peer': 'local'},
    for (final peer in peers.values)
      for (final track in peer.renderer.srcObject?.getAudioTracks() ?? <MediaStreamTrack>[]) {'id': track.id!, 'peer': '${peer.pid}'},
  ];
  MJson rec = const {};
  DateTime _recStamp = DateTime.now();
  String? _recPrev;
  final _recShown = <String>{};
  /// one-shot message for the screen ("Recording saved…", "Somebody declined…")
  String? recNotice;
  bool get recEnabled => rec['enabled'] == true;
  MJson get recUi => rec['ui'] is Map ? Map<String, dynamic>.from(rec['ui'] as Map) : const {};
  MJson? get recSession => rec['session'] is Map ? Map<String, dynamic>.from(rec['session'] as Map) : null;
  MJson? get recConsent => rec['consent'] is Map ? Map<String, dynamic>.from(rec['consent'] as Map) : null;
  bool get recCanStart => _recorder.hasPending || (recEnabled && rec['can_start'] == true);
  bool get recCanStop => _recorder.hasPending || (recEnabled && rec['can_stop'] == true);
  bool get recRunning => recSession?['status'] == 'recording';
  int get recMode => (rec['mode'] as num?)?.toInt() ?? 0;
  int get recElapsed => ((recSession?['elapsed'] as num?)?.toInt() ?? 0) + DateTime.now().difference(_recStamp).inSeconds;

  /// The banner text the person must always be able to see while a recording runs (the server never lets both REC icon and banner be off).
  String? get recBanner {
    final s = recSession;
    if (!recEnabled || s == null) return null;
    final what = meeting['kind'] == 'call' ? 'call' : 'meeting';
    if (s['status'] == 'pending') {
      if (s['by_me'] != true) return null;
      final w = (s['waiting_for'] as List? ?? const []).join(', ');
      return 'Waiting for ${w.isEmpty ? 'everybody' : w} to allow recording…';
    }
    if (recUi['banner'] != true) return null;
    if (s['private'] == true) return s['by_me'] == true ? 'You are recording a private copy of this $what' : '${s['by'] ?? 'A participant'} is recording this $what';
    return 'This $what is being recorded${(s['by'] != null && recMode != 1) ? ' (started by ${s['by']})' : ''}';
  }

  void _applyRecording(dynamic raw) {
    rec = raw is Map ? Map<String, dynamic>.from(raw) : const {};
    _recStamp = DateTime.now();
    final cur = recSession?['status']?.toString();
    if (recEnabled && recUi['chime'] == true) {
      if (cur == 'recording' && _recPrev != 'recording') { SystemSound.play(SystemSoundType.alert); HapticFeedback.mediumImpact(); }
      if (_recPrev == 'recording' && cur != 'recording') { SystemSound.play(SystemSoundType.alert); HapticFeedback.lightImpact(); }
    }
    _recPrev = cur;
    unawaited(_recorder.sync(rec, pid, _recordingTracks));
    final l = rec['last'];
    if (recEnabled && l is Map) {
      final key = '${l['session_id']}${l['status']}${l['saved'] == true ? 's' : ''}';
      if (_recShown.add(key)) {
        final what = meeting['kind'] == 'call' ? 'call' : 'meeting';
        if (l['saved'] == true) { recNotice = 'Recording saved. ${what == 'meeting' ? 'A link is in the meeting chat.' : 'It is in your chat.'}'; }
        else if (l['status'] == 'declined') { recNotice = 'Nothing was recorded: ${l['reason'] == 'declined_late' ? 'somebody who joined did not agree.' : 'somebody declined.'}'; }
        else if (l['status'] == 'expired') { recNotice = 'Nothing was recorded: not everybody answered in time.'; }
        else if (l['status'] == 'failed') { recNotice = 'Recording could not start: no device in this $what can record. Join from the website to record.'; }
      }
    }
  }

  Future<void> recToggle() async {
    try {
      if (_recorder.hasPending) { await _recorder.retry(); return; }
      final path = recSession != null ? 'recording/stop' : 'recording/start';
      final j = Api.obj(await api.post('meetings/$code/$path', {'pid': pid}));
      _applyRecording(j);
    } on ApiException catch (e) {
      recNotice = e.message;
    } catch (_) {
      recNotice = 'Could not reach the server.';
    }
    notifyListeners();
  }

  Future<void> recRespond(int sessionId, bool accept) async {
    try {
      final j = Api.obj(await api.post('meetings/$code/recording/respond', {'pid': pid, 'session_id': sessionId, 'accept': accept}));
      _applyRecording(j);
    } catch (_) {}
    notifyListeners();
  }
  final localRenderer = RTCVideoRenderer();
  final peers = <int, MeetingPeer>{};

  MediaStream? _local;
  List<dynamic> _ice = [];
  int _sigLast = 0, _chatLast = 0;
  Timer? _loop, _statusLoop;
  bool _checkingStatus = false, _accepted = false;

  void _callState(String? status) {
    if (_closed || status == null) return;
    if (status == "active") {
      _accepted = true;
      _tones.accepted();
      _statusLoop?.cancel();
      notifyListeners();
    } else if (const ["declined", "cancelled", "missed", "ended"].contains(status)) {
      unawaited(_end(status == "declined" ? "Call declined" : "Call ended"));
    }
  }

  Future<void> _checkCallState() async {
    if (_closed || _checkingStatus || _accepted || pid == 0) return;
    _checkingStatus = true;
    try {
      final j = Api.obj(await api.getNoCache("meetings/$code/poll", query: {"pid": "$pid", "state_only": "1"}));
      if (j["meeting"] is Map) _callState(j["meeting"]["call_status"] as String?);
      if (j["me"] is Map && j["me"]["status"] == "ended" && !_closed) unawaited(_end("Call ended"));
    } catch (_) {} finally { _checkingStatus = false; }
  }
  StreamSubscription<Map<String, dynamic>>? _realtime;
  final _inbox = SignalInbox();
  Future<void> _signal(MJson m) => _inbox.add(m, () async {
    if (_closed) return;
    await _handleSignal((m['from'] as num).toInt(), '${m['type']}', '${m['payload']}');
  });

  void _live(MJson e) {
    if (_closed) return;
    if (e['type'] == 'reconnected') { _pollAgain = _polling; _poll(); return; }
    if (e['code'] != code) return;
    if (e['type'] == 'call_cancel') {
      if (e['group'] != true) _callState(e['status'] as String? ?? (e['reason'] == 'answered' ? 'active' : e['reason'] as String?));
      return;
    }
    if (e['type'] == 'meeting_ended') { _end(meeting['kind'] == 'call' ? 'The call was ended by another participant.' : 'The meeting was ended by another participant.'); return; }
    if (e['type'] == 'meeting_changed') { _pollAgain = _polling; _poll(); return; }
    if (e['type'] != 'meeting_signal' || e['to'] != pid) return;
    final m = e['signal'];
    // During peer creation use REST replay after initialization, so a socket
    // event cannot race the renderer/connection setup and lose an offer.
    if (_polling || m is! Map || peers[m['from']]?.pc == null) { _pollAgain = _polling; _poll(); return; }
    unawaited(_signal(Map<String, dynamic>.from(m)).catchError((_) {}));
  }
  bool _pollAgain = false;
  bool _polling = false, _closed = false, _rendererReady = false;
  int _misses = 0;
  bool _dead = false;

  @override
  void notifyListeners() {
    if (!_dead) super.notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _dead = true;
    super.dispose();
  }

  bool get isMod => role == 'host' || role == 'cohost';
  bool get isHost => role == 'host';
  String get title => '${meeting['title'] ?? 'Meeting'}';
  bool get isCall => meeting['kind'] == 'call';
  bool get isAudioCall => isCall && (meeting['audio_only'] == true || title.toLowerCase().contains('audio'));
  String get roomLabel => isCall ? (isAudioCall ? 'audio call' : 'video call') : 'meeting';
  String get url => '${meeting['url'] ?? ''}';
  bool get locked => meeting['locked'] == true;
  /// Only signed-in people can add friends (guests have no friends list).
  bool get canInvite => phase == 'in';
  List<MJson>? invitable;

  Future<void> loadInvitable() async {
    try {
      final j = Api.list(await api.getNoCache('meetings/$code/invitable', query: {'pid': '$pid'}));
      invitable = j;
      notifyListeners();
    } catch (_) {
      invitable = invitable ?? [];
      notifyListeners();
    }
  }

  Future<String?> invite(int userId) async {
    try {
      final raw = await api.post('meetings/$code/invite', {'pid': pid, 'user_id': userId});
      loadInvitable();
      return raw is Map ? raw['message'] as String? : 'Calling…';
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not add them';
    }
  }

  /// Host: more time for a scheduled meeting (the meeting is never cut off by itself).
  Future<String?> extend(int minutes) async {
    final d = ((meeting['duration'] as num?)?.toInt() ?? 60) + minutes;
    try {
      final raw = await api.put('meetings/$code', {'duration': d > 480 ? 480 : d});
      final end = DateTime.tryParse('${meeting['ends_at'] ?? ''}');
      if (end != null) meeting['ends_at'] = end.add(Duration(minutes: minutes)).toUtc().toIso8601String();
      meeting['duration'] = d;
      notifyListeners();
      return raw is Map ? raw['message'] as String? : 'Extended';
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not extend';
    }
  }

  bool get hasVideoTrack => (_local?.getVideoTracks().isNotEmpty ?? false);

  // ───────────── camera / microphone preview ─────────────
  /// Ask for permission and open camera + microphone (for the preview before joining and for the meeting itself).
  bool get rendererReady => _rendererReady && !_closed;
  Future<bool>? _preview;
  Future<bool> startPreview({required bool wantVideo}) => _preview ??= _startPreview(wantVideo: wantVideo).whenComplete(() => _preview = null);

  Future<bool> _startPreview({required bool wantVideo}) async {
    if (_closed) return false;
    if (_local != null) return true;
    final micOk = await AppPermissions.microphone();
    if (!micOk) {
      message = 'Microphone permission is needed. Allow it in your phone settings.';
      notifyListeners();
      return false;
    }
    await AppPermissions.bluetoothForCalls();
    if (_closed) return false;
    await audio.prepare();
    if (_closed) return false;
    var video = wantVideo;
    if (video && !(await AppPermissions.camera())) {
      video = false;
      camOn = false;
      message = 'Camera permission was not given – you will join with the camera off.';
    }
    try {
      if (!_rendererReady) {
        await localRenderer.initialize();
        _rendererReady = true;
      }
      const audioConstraints = {'echoCancellation': true, 'noiseSuppression': true, 'autoGainControl': true};
      try {
        _local = await navigator.mediaDevices.getUserMedia({
          'audio': audioConstraints,
          'video': video ? {'facingMode': 'user', 'width': {'ideal': 640}, 'height': {'ideal': 480}, 'frameRate': {'ideal': 24}} : false,
        });
      } catch (_) {
        if (!video || _closed) rethrow;
        // A busy or unavailable camera must not prevent microphone capture.
        _local = await navigator.mediaDevices.getUserMedia({'audio': audioConstraints, 'video': false});
        video = false;
        camOn = false;
        message = 'Camera is unavailable. You can continue with audio.';
      }
      if (_closed) {
        for (final track in _local!.getTracks()) { await track.stop(); }
        await _local!.dispose();
        _local = null;
        return false;
      }
      try { await audio.apply(); } catch (_) {}
      if (_closed) return false;
      localRenderer.srcObject = _local;
      if (!video) camOn = false;
      notifyListeners();
      return true;
    } catch (_) {
      message = 'Could not open the camera or microphone.';
      notifyListeners();
      return false;
    }
  }

  // ───────────── join ─────────────
  Future<void> join({required bool audio, required bool video, int? answerCallId}) async {
    if (phase == 'joining' || phase == 'in' || phase == 'waiting') return;
    phase = 'joining';
    message = null;
    notifyListeners();
    _realtime ??= Realtime.I.events.listen(_live);
    if (answerCallId != null) {
      _accepted = true;
      _tones.accepted();
      try {
        await api.post('calls/$answerCallId/answer');
        if (_closed) { await api.post('calls/$answerCallId/end'); return; }
      } catch (e) {
        if (_closed) return;
        phase = 'error';
        message = e is ApiException ? e.message : 'Could not answer the call.';
        notifyListeners();
        return;
      }
    }
    if (!await startPreview(wantVideo: video)) {
      if (_local == null) {
        phase = 'error';
        notifyListeners();
        return;
      }
    }
    if (_closed) return;
    micOn = audio && _local != null;
    camOn = video && hasVideoTrack;
    _applyTracks();

    try {
      final raw = await api.post('meetings/$code/join', {'audio': micOn, 'video': camOn});
      final d = Api.obj(raw);
      if (_closed) {
        await api.post('meetings/$code/leave', {'pid': d['pid']});
        return;
      }
      pid = (d['pid'] as num).toInt();
      role = '${d['role'] ?? 'participant'}';
      meeting = Map<String, dynamic>.from(d['meeting'] as Map? ?? {});
      _ice = (d['ice_servers'] as List?) ?? [];
      phase = d['status'] == 'waiting' ? 'waiting' : 'in';
      if (phase == 'in' && !isCall) _tones.connected();
      notifyListeners();
      try {
        await this.audio.apply();
      } catch (_) {}
      if (_closed) return;
      _realtime ??= Realtime.I.events.listen(_live);
      if (isCall && role == 'host' && !_accepted && meeting['call_status'] != 'active') _tones.dialing();
      _callState(meeting['call_status'] as String?);
      if (_closed) return;
      if (isCall && role == 'host' && !_accepted) {
        _statusLoop = Timer.periodic(const Duration(milliseconds: 500), (_) => _checkCallState());
      }
      _loop = Timer.periodic(const Duration(seconds: 1), (_) => _poll());
      _poll();
    } on ApiException catch (e) {
      phase = 'error';
      message = e.message;
      await _release();
      notifyListeners();
    } catch (_) {
      phase = 'error';
      message = 'Could not join. Check your connection.';
      await _release();
      notifyListeners();
    }
  }

  void _applyTracks() {
    for (final t in _local?.getAudioTracks() ?? const <MediaStreamTrack>[]) {
      t.enabled = micOn;
    }
    for (final t in _local?.getVideoTracks() ?? const <MediaStreamTrack>[]) {
      t.enabled = camOn;
    }
  }

  // ───────────── polling ─────────────
  Future<void> _poll() async {
    if (_polling || _closed || pid == 0) return;
    _polling = true;
    try {
      final j = Api.obj(await api.getNoCache('meetings/$code/poll', query: {'pid': '$pid', 'sig': '$_sigLast', 'chat': '$_chatLast'}));
      if (_closed) return;
      _misses = 0;
      if (j['meeting'] is Map) meeting = Map<String, dynamic>.from(j['meeting'] as Map);
      if (isCall) _callState(meeting['call_status'] as String?);
      if (_closed) return;
      final me = j['me'] is Map ? Map<String, dynamic>.from(j['me'] as Map) : <String, dynamic>{};
      final st = '${me['status']}';
      if (st == 'ended') return await _end(meeting['kind'] == 'call' ? 'The call has ended.' : 'The meeting has ended.');
      if (st == 'removed') return await _end('The host removed you from the meeting.');
      if (st == 'denied') return await _end('The host did not let you in.');
      if (st == 'left') return await _end(meeting['kind'] == 'call' ? 'You left the call.' : 'You left the meeting.');
      role = '${me['role'] ?? role}';
      if (st == 'waiting') {
        if (phase != 'waiting') {
          phase = 'waiting';
          notifyListeners();
        }
        return;
      }
      if (st == 'joined' && phase != 'in') {
        phase = 'in';
        if (!isCall) _tones.connected();
      }
      if (me['force_mute'] == true && micOn) {
        micOn = false;
        _applyTracks();
        _state({'audio': false, 'ack_mute': true});
      }
      _applyRecording(j['recording']);
      participants = (j['participants'] as List? ?? const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      waiting = (j['waiting'] as List? ?? const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      hand = participants.any((p) => p['is_me'] == true && p['hand'] == true);
      await _syncPeers();
      for (final s in (j['signals'] as List? ?? const [])) {
        final m = Map<String, dynamic>.from(s as Map);
        final id = (m['id'] as num).toInt();
        if (id > _sigLast) _sigLast = id;
        await _signal(m);
      }
      for (final c in (j['chat'] as List? ?? const [])) {
        final m = Map<String, dynamic>.from(c as Map);
        final id = (m['id'] as num).toInt();
        if (id > _chatLast) {
          _chatLast = id;
          chat.add(m);
          if (!chatOpen && m['pid'] != pid) unreadChat++;
        }
      }
      notifyListeners();
    } on ApiException catch (e) {
      if (e.status == 403 || e.status == 404) {
        if (++_misses >= 3) await _end('You are no longer in this meeting.');
      }
    } catch (_) {
      // a short network drop: keep trying; give up after about a minute
      if (++_misses >= 60) await _end('Connection lost.');
    } finally {
      _polling = false;
      if (_pollAgain && !_closed) { _pollAgain = false; scheduleMicrotask(_poll); }
    }
  }

  // ───────────── peers (WebRTC) ─────────────
  Future<void> _syncPeers() async {
    final ids = participants.map((p) => (p['pid'] as num).toInt()).where((i) => i != pid).toSet();
    for (final id in peers.keys.toList()) {
      if (!ids.contains(id)) await _dropPeer(id);
    }
    await Future.wait(ids.where((id) => !peers.containsKey(id)).map((id) async {
      if (_closed) return;
      final offering = pid > id; // the higher participant id creates the offer
      await _makePeer(id, offerer: offering);
      if (offering && !_closed) await _offer(id);
    }));
  }

  Future<MeetingPeer?> _makePeer(int id, {required bool offerer}) async {
    if (_closed) return null;
    final p = MeetingPeer(id)..offerer = offerer;
    peers[id] = p;
    try {
      await p.renderer.initialize();
      p.rendererReady = true;
      if (_closed || peers[id] != p) { await p.renderer.dispose(); p.rendererReady = false; return null; }
      final pc = await createPeerConnection({'iceServers': _ice, 'sdpSemantics': 'unified-plan'});
      if (_closed || peers[id] != p) { await pc.close(); return null; }
      p.pc = pc;
      // Only the side that SENDS the offer adds its tracks up front. A transceiver made with addTransceiver() is not
      // reused for an incoming offer (only addTrack() ones are), so on the answering side a pre-added microphone is
      // left out of the answer and the other person hears nothing. The answering side attaches its tracks right
      // after setRemoteDescription (see _attachLocal).
      if (offerer) {
        final localAudio = _local?.getAudioTracks() ?? const <MediaStreamTrack>[];
        final localVideo = _local?.getVideoTracks() ?? const <MediaStreamTrack>[];
        for (final t in localAudio) {
          final transceiver = await pc.addTransceiver(
            track: t,
            init: RTCRtpTransceiverInit(
              direction: TransceiverDirection.SendRecv,
              streams: [_local!],
            ),
          );
          debugPrint('[RTC-DIAG][meeting peer $id] local audio direction=${await transceiver.getDirection()}');
        }
        for (final t in localVideo) {
          final transceiver = await pc.addTransceiver(
            track: t,
            init: RTCRtpTransceiverInit(
              direction: TransceiverDirection.SendRecv,
              streams: [_local!],
            ),
          );
          debugPrint('[RTC-DIAG][meeting peer $id] local video direction=${await transceiver.getDirection()}');
        }
        // Explicitly create a receiver for media this participant does not send.
        // Local sender transceivers above are explicitly SendRecv so this side
        // also advertises that it can receive the peer's media.
        if (localAudio.isEmpty) {
          await pc.addTransceiver(
            kind: RTCRtpMediaType.RTCRtpMediaTypeAudio,
            init: RTCRtpTransceiverInit(direction: TransceiverDirection.RecvOnly),
          );
          debugPrint('[RTC-DIAG][meeting peer $id] added receive-only audio transceiver');
        }
        if (localVideo.isEmpty) {
          await pc.addTransceiver(
            kind: RTCRtpMediaType.RTCRtpMediaTypeVideo,
            init: RTCRtpTransceiverInit(direction: TransceiverDirection.RecvOnly),
          );
          debugPrint('[RTC-DIAG][meeting peer $id] added receive-only video transceiver');
        }
      }
      debugPrint('[RTC-DIAG][meeting peer $id] local audio=${_local?.getAudioTracks().length ?? 0} video=${_local?.getVideoTracks().length ?? 0} audioEnabled=${_local?.getAudioTracks().map((t) => t.enabled).toList()}');
      pc.onIceCandidate = (RTCIceCandidate c) {
        if (c.candidate != null) _send(id, 'ice', {'candidate': c.candidate, 'sdpMid': c.sdpMid, 'sdpMLineIndex': c.sdpMLineIndex});
      };
      pc.onIceConnectionState = (RTCIceConnectionState state) {
        debugPrint('[RTC-DIAG][meeting peer $id] ICE=$state');
      };
      pc.onTrack = (RTCTrackEvent e) {
        debugPrint('[RTC-DIAG][meeting peer $id] remote track kind=${e.track.kind} enabled=${e.track.enabled} streams=${e.streams.length}');
        // Keep the received tracks in a local aggregate stream. In the locked
        // flutter_webrtc mobile plugin, Unified Plan onTrack streams are
        // reported to Dart but are not registered in the native renderer's
        // remote-stream lookup; assigning e.streams.first can therefore leave
        // the native video source null. The aggregate stream is app-owned and
        // registered by createLocalMediaStream, so RTCVideoRenderer can resolve
        // it on both Android and iOS.
        final mediaWork = p.remoteMedia.add(e.track, (stream) {
          if (_closed || peers[id] != p || !p.rendererReady) return;
          p.renderer.srcObject = stream;
          p.hasStream = true;
          notifyListeners();
        });
        // `RemoteMedia.add` records the track synchronously, while its stream
        // wrapper may need an async native allocation. Refresh now so the tile
        // does not remain on the avatar while that allocation is pending.
        if (e.track.kind == 'video') notifyListeners();
        unawaited(mediaWork.catchError((_) {}));
      };
      pc.onConnectionState = (RTCPeerConnectionState s) {
        debugPrint('[RTC-DIAG][meeting peer $id] peer=$s');
        if (s == RTCPeerConnectionState.RTCPeerConnectionStateConnected && peers[id] == p && !_closed) {
          _mediaConnected = true;
          _tones.connected();
          notifyListeners();
          unawaited(audio.apply().catchError((_) {}));
          _startPeerStats(p, pc);
        }
        if (s == RTCPeerConnectionState.RTCPeerConnectionStateFailed && peers[id] == p && !_closed) {
          _dropPeer(id).then((_) => notifyListeners()); // the next poll builds a fresh connection
        }
      };
      return p;
    } catch (_) {
      if (peers[id] == p) await _dropPeer(id);
      return null;
    }
  }

  void _startPeerStats(MeetingPeer peer, RTCPeerConnection pc) {
    if (peer.statsTimer != null) return;
    unawaited(_logPeerAudioStats(peer, pc));
    peer.statsTimer = Timer.periodic(
      const Duration(seconds: 5),
          (_) => unawaited(_logPeerAudioStats(peer, pc)),
    );
  }

  Future<void> _logPeerAudioStats(MeetingPeer peer, RTCPeerConnection pc) async {
    if (_closed || peers[peer.pid] != peer || peer.statsBusy) return;
    peer.statsBusy = true;
    try {
      await logRtcAudioStats(pc, 'meeting peer ${peer.pid}');
    } finally {
      peer.statsBusy = false;
    }
  }

  Future<void> _dropPeer(int id) async {
    final p = peers.remove(id);
    if (p == null) return;
    p.statsTimer?.cancel();
    final ready = p.rendererReady;
    p.rendererReady = false;
    p.hasStream = false;
    notifyListeners();
    try {
      p.renderer.srcObject = null;
      await p.remoteMedia.dispose();
      await p.pc?.close();
      if (ready) await p.renderer.dispose();
    } catch (_) {}
  }

  Future<void> _send(int to, String type, Map<String, dynamic> payload) async {
    try {
      await api.post('meetings/$code/signal', {'pid': pid, 'to': to, 'type': type, 'payload': jsonEncode(payload)});
    } catch (_) {}
  }

  Future<void> _offer(int id) async {
    final p = peers[id];
    final pc = p?.pc;
    if (pc == null) return;
    try {
      final o = await pc.createOffer({'offerToReceiveAudio': 1, 'offerToReceiveVideo': 1});
      debugPrint('[RTC-DIAG][meeting peer $id] offer hasAudio=${o.sdp?.contains('m=audio') ?? false} hasVideo=${o.sdp?.contains('m=video') ?? false}');
      await pc.setLocalDescription(o);
      await _send(id, 'offer', {'type': o.type, 'sdp': o.sdp});
    } catch (_) {}
  }

  Future<void> _handleSignal(int from, String type, String payload) async {
    Map<String, dynamic> d;
    try {
      d = jsonDecode(payload) as Map<String, dynamic>;
    } catch (_) {
      return;
    }
    try {
      if (type == 'offer') {
        // a fresh offer replaces an old connection (the other person reconnected)
        if (peers.containsKey(from) && (peers[from]!.remoteSet || peers[from]!.offerer)) await _dropPeer(from);
        final p = peers[from] ?? await _makePeer(from, offerer: false);
        final pc = p?.pc;
        if (p == null || pc == null) return;
        await pc.setRemoteDescription(RTCSessionDescription(d['sdp'] as String?, d['type'] as String?));
        p.remoteSet = true;
        await _attachLocal(p); // answering side: our microphone / camera go on the transceivers the offer created
        await _flush(p);
        final a = await pc.createAnswer({'offerToReceiveAudio': 1, 'offerToReceiveVideo': 1});
        debugPrint('[RTC-DIAG][meeting peer $from] answer hasAudio=${a.sdp?.contains('m=audio') ?? false} hasVideo=${a.sdp?.contains('m=video') ?? false}');
        await pc.setLocalDescription(a);
        await _send(from, 'answer', {'type': a.type, 'sdp': a.sdp});
      } else if (type == 'answer') {
        final p = peers[from];
        if (p?.pc == null) return;
        await p!.pc!.setRemoteDescription(RTCSessionDescription(d['sdp'] as String?, d['type'] as String?));
        p.remoteSet = true;
        await _flush(p);
      } else if (type == 'ice') {
        final p = peers[from] ?? await _makePeer(from, offerer: false); // candidates can arrive before the offer: keep them
        if (p == null) return;
        final c = RTCIceCandidate(d['candidate'] as String?, d['sdpMid'] as String?, (d['sdpMLineIndex'] as num?)?.toInt());
        if (p.remoteSet && p.pc != null) {
          await p.pc!.addCandidate(c);
        } else {
          p.queue.add(c);
        }
      }
    } catch (_) {}
  }

  /// Answering side: after the remote offer is applied, send our microphone / camera on the transceivers it created.
  /// Set its sender and direction explicitly so the answer carries our media.
  Future<void> _attachLocal(MeetingPeer p) async {
    final pc = p.pc;
    final local = _local;
    if (pc == null || local == null || p.offerer) return;
    await attachLocalTracks(pc, local);
    debugPrint('[RTC-DIAG][meeting peer ${p.pid}] answer: attached local tracks as sendrecv');
  }

  Future<void> _flush(MeetingPeer p) async {
    for (final c in List.of(p.queue)) {
      try {
        await p.pc?.addCandidate(c);
      } catch (_) {}
    }
    p.queue.clear();
  }

  // ───────────── my controls ─────────────
  Future<void> _state(Map<String, dynamic> s) async {
    try {
      await api.post('meetings/$code/state', {'pid': pid, ...s});
    } catch (_) {}
  }

  void toggleMic() {
    micOn = !micOn;
    _applyTracks();
    notifyListeners();
    _state({'audio': micOn});
  }

  /// Audio call -> video: open the camera and rebuild the connections so everybody receives the video.
  Future<void> enableCamera() async {
    if (hasVideoTrack || _local == null) return;
    if (!(await AppPermissions.camera())) {
      message = 'Camera permission was not given.';
      notifyListeners();
      return;
    }
    try {
      final cam = await navigator.mediaDevices.getUserMedia({'audio': false, 'video': {'facingMode': 'user', 'width': {'ideal': 640}, 'height': {'ideal': 480}, 'frameRate': {'ideal': 24}}});
      for (final t in cam.getVideoTracks()) {
        await _local!.addTrack(t);
      }
      try { await audio.apply(); } catch (_) {}
      localRenderer.srcObject = _local;
      camOn = true;
      _applyTracks();
      notifyListeners();
      for (final id in peers.keys.toList()) {
        await _dropPeer(id);
        await _makePeer(id, offerer: true);
        await _offer(id); // the other side accepts an offer from an existing peer as a fresh start
      }
      _state({'video': true});
    } catch (_) {
      message = 'Could not open the camera.';
      notifyListeners();
    }
  }

  void toggleCam() {
    if (!hasVideoTrack) {
      enableCamera();
      return;
    }
    camOn = !camOn;
    _applyTracks();
    notifyListeners();
    _state({'video': camOn});
  }

  Future<void> switchCamera() async {
    final v = _local?.getVideoTracks();
    if (v == null || v.isEmpty) return;
    try {
      await Helper.switchCamera(v.first);
      frontCamera = !frontCamera;
      notifyListeners();
    } catch (_) {}
  }

  Future<bool> selectAudioOutput(String output) async {
    if (_closed) return false;
    final switched = await audio.select(output);
    if (!_closed) notifyListeners();
    return switched;
  }

  Future<void> toggleSpeaker() async {
    await selectAudioOutput(speaker ? 'auto' : 'speaker');
  }

  void toggleHand() {
    hand = !hand;
    notifyListeners();
    _state({'hand': hand});
  }

  Future<String?> sendChat(String text) async {
    final t = text.trim();
    if (t.isEmpty) return null;
    try {
      await api.post('meetings/$code/chat', {'pid': pid, 'body': t});
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not send';
    }
  }

  /// Host / co-host actions: admit, admit_all, deny, remove, mute, mute_all, lower_hand, cohost, uncohost, lock, unlock, end.
  Future<String?> hostAction(String action, [int? target]) async {
    try {
      final raw = await api.post('meetings/$code/host', {'pid': pid, 'action': action, if (target != null) 'target': target});
      _poll();
      return raw is Map ? raw['message'] as String? : null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not do that';
    }
  }

  void openChat(bool open) {
    chatOpen = open;
    if (open) unreadChat = 0;
    notifyListeners();
  }

  // ───────────── leave / end ─────────────
  Future<bool> _deliverTermination(String path, Map<String, dynamic> body) async {
    for (var attempt = 0; attempt < 3; attempt++) {
      try {
        await api.post(path, body).timeout(const Duration(seconds: 5));
        return true;
      } on ApiException catch (e) {
        if (e.status < 500 && e.status != 429) return false;
      } catch (_) {}
      if (attempt < 2) await Future<void>.delayed(Duration(milliseconds: 300 * (1 << attempt)));
    }
    return false;
  }

  Future<void> leave() async {
    if (_closed) return;
    unawaited(_deliverTermination('meetings/$code/leave', {'pid': pid}));
    await _end(meeting['kind'] == 'call' ? 'You left the call.' : 'You left the meeting.');
  }

  Future<void> endForAll() async {
    if (_closed) return;
    if (!isHost) return;
    terminationPending = true;
    final delivery = _deliverTermination('meetings/$code/host', {'pid': pid, 'action': 'end'});
    await _end('Closing the meeting for everyone…');
    final confirmed = await delivery;
    if (_disposed) return;
    terminationPending = false;
    phase = confirmed ? 'ended' : 'error';
    message = confirmed ? 'You ended the meeting for everyone.' : 'Disconnected locally. Could not confirm closing the meeting for everyone. Rejoin to check its status.';
    notifyListeners();
  }

  Future<void> _end(String why) async {
    if (_closed) return;
    message = why;
    phase = 'ended';
    notifyListeners();
    await _release();
  }

  Future<void>? _releasing;
  Future<void> _release() => _releasing ??= _releaseMedia();

  Future<void> _releaseMedia() async {
    _closed = true;
    await _tones.stop();
    _loop?.cancel();
    _statusLoop?.cancel();
    _realtime?.cancel();
    unawaited(_recorder.finish().catchError((_) { recNotice = 'Recording could not be uploaded. The audio file remains saved on this phone.'; }));
    if (_preview != null) await _preview;
    for (final id in peers.keys.toList()) {
      await _dropPeer(id);
    }
    try {
      for (final t in _local?.getTracks() ?? const <MediaStreamTrack>[]) {
        await t.stop();
      }
      await _local?.dispose();
    } catch (_) {}
    _local = null;
    try {
      localRenderer.srcObject = null;
      if (_rendererReady) await localRenderer.dispose();
    } catch (_) {}
    _rendererReady = false;
  }

  /// Leaving the screen without pressing a button (back gesture): behave like "Leave".
  Future<void> shutdown() async {
    if (!_closed && pid != 0 && phase != 'ended') {
      try {
        unawaited(_deliverTermination('meetings/$code/leave', {'pid': pid}));
      } catch (_) {}
    }
    await _release();
  }
}