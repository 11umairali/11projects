import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

/// Log only audio RTP counters, never SDP, addresses, credentials, or media.
Future<void> logRtcAudioStats(RTCPeerConnection pc, String label) async {
  try {
    final reports = await pc.getStats();
    final rows = reports
        .where((r) => r.type == 'inbound-rtp' || r.type == 'outbound-rtp' || r.type == 'media-source' || r.type == 'track')
        .where((r) {
      final kind = r.values['kind'] ?? r.values['mediaType'];
      return kind == null
          ? (r.values.containsKey('audioLevel') || r.values.containsKey('totalAudioEnergy') || r.type == 'inbound-rtp' || r.type == 'outbound-rtp')
          : '$kind'.toLowerCase() == 'audio';
    })
        .map((r) {
      final values = r.values;
      final kind = values['kind'] ?? values['mediaType'] ?? '?';
      if (r.type == 'inbound-rtp' || r.type == 'outbound-rtp') {
        final bytes = r.type == 'inbound-rtp' ? values['bytesReceived'] : values['bytesSent'];
        final packets = r.type == 'inbound-rtp' ? values['packetsReceived'] : values['packetsSent'];
        return '${r.type}[kind=$kind bytes=${bytes ?? '?'} packets=${packets ?? '?'}]';
      }
      return '${r.type}[kind=$kind audioLevel=${values['audioLevel'] ?? '?'} totalAudioEnergy=${values['totalAudioEnergy'] ?? '?'}]';
    })
        .toList();
    debugPrint(
      '[RTC-DIAG][$label] audioRtp=${rows.isEmpty ? 'no audio RTP reports' : rows.join(' ')}',
    );
  } catch (error) {
    debugPrint('[RTC-DIAG][$label] audioRtp error=${error.runtimeType}');
  }
}