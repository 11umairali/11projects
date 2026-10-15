package com.dahify.dahimail

import android.content.Context
import android.os.SystemClock
import com.cloudwebrtc.webrtc.FlutterWebRTCPlugin
import com.cloudwebrtc.webrtc.audio.AudioProcessingAdapter
import org.webrtc.AudioTrack
import org.webrtc.AudioTrackSink
import java.io.File
import java.io.RandomAccessFile
import java.nio.ByteBuffer
import java.nio.ByteOrder
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

/** Records the WebRTC tracks themselves, so headphones and muted microphones work. */
internal class CallAudioRecorder(private val context: Context) {
    private val tracks = mutableMapOf<String, Pair<AudioTrack, AudioTrackSink>>()
    @Volatile private var output: PcmCallFile? = null
    private var timer: java.util.concurrent.ScheduledExecutorService? = null
    private var started = 0L
    private var file: File? = null
    @Volatile private var localTrack: AudioTrack? = null
    private var captureAdapter: AudioProcessingAdapter? = null
    private val capture = object : AudioProcessingAdapter.ExternalAudioFrameProcessing {
        private var cursor = -1L
        override fun initialize(sampleRateHz: Int, numChannels: Int) { cursor = -1 }
        override fun reset(newRate: Int) { cursor = -1 }
        override fun process(numBands: Int, numFrames: Int, buffer: ByteBuffer) {
            val writer = output ?: return
            try {
                if (localTrack?.enabled() != true || numFrames <= 0) return
                // The locked WebRTC SDK supplies channel 0 as FloatS16, 10ms per callback.
                val input = buffer.duplicate().order(ByteOrder.nativeOrder())
                if (input.remaining() < numFrames * 4) return
                val pcm = ByteBuffer.allocate(numFrames * 2).order(ByteOrder.LITTLE_ENDIAN)
                repeat(numFrames) { pcm.putShort(input.float.toInt().coerceIn(-32768, 32767).toShort()) }
                pcm.flip()
                val elapsed = (SystemClock.elapsedRealtime() - started).coerceAtLeast(0)
                val at = maxOf(cursor, elapsed * 16 - 160).coerceAtLeast(0)
                cursor = at + 160
                writer.add(pcm, numFrames * 100, 1, numFrames, at)
            } catch (_: RuntimeException) { /* A track can close while a frame is in flight. */ }
        }
    }

    fun start(values: List<Map<String, String>>, maxMb: Int): String {
        check(output == null) { "Already recording" }
        val directory = File(context.filesDir, "call-recordings").apply { mkdirs() }
        val destination = File.createTempFile("recording-", ".wav", directory)
        file = destination
        output = PcmCallFile(destination, maxMb.coerceIn(1, 500).toLong() * 1024 * 1024)
        started = SystemClock.elapsedRealtime()
        try {
            update(values)
            capture.initialize(0, 1)
            captureAdapter = FlutterWebRTCPlugin.sharedSingleton.audioProcessingController.capturePostProcessing
            captureAdapter?.addProcessor(capture)
            check(tracks.isNotEmpty() || localTrack != null) { "Call audio is not ready" }
            timer = Executors.newSingleThreadScheduledExecutor().also { executor ->
                executor.scheduleAtFixedRate({
                    try { output?.flush((SystemClock.elapsedRealtime() - started - 200).coerceAtLeast(0)) }
                    catch (_: Exception) { output?.failed = true }
                }, 200, 50, TimeUnit.MILLISECONDS)
            }
            return destination.absolutePath
        } catch (error: Exception) { stop(); destination.delete(); throw error }
    }

    fun update(values: List<Map<String, String>>) {
        localTrack = null
        val active = values.map { "${it["peer"]}:${it["id"]}" }.toSet()
        for (key in tracks.keys.toList()) if (key !in active) {
            val (track, sink) = tracks.remove(key)!!
            try { track.removeSink(sink) } catch (_: Exception) {}
        }
        for (value in values) {
            val key = "${value["peer"]}:${value["id"]}"
            if (tracks.containsKey(key)) continue
            val track = FlutterWebRTCPlugin.sharedSingleton?.getTrackForId(value["id"], null) as? AudioTrack ?: continue
            if (value["peer"] == "local") { localTrack = track; continue }
            var cursor = -1L
            val sink = AudioTrackSink { buffer, bits, rate, channels, frames, _ ->
                val writer = output
                if (writer != null && bits == 16 && rate > 0 && channels > 0) {
                    val elapsed = (SystemClock.elapsedRealtime() - started).coerceAtLeast(0)
                    val size = frames * 16000 / rate
                    val at = maxOf(cursor, elapsed * 16 - size).coerceAtLeast(0)
                    cursor = at + size
                    // Disabled local tracks must never leak a muted microphone into a recording.
                    try { if (track.enabled()) writer.add(buffer, rate, channels, frames, at) } catch (_: RuntimeException) {}
                }
            }
            track.addSink(sink)
            tracks[key] = track to sink
        }
    }

    fun status() = mapOf("limit" to (output?.full == true), "failed" to (output?.failed == true))

    fun stop(): Map<String, Any> {
        timer?.shutdownNow(); timer = null
        val writer = output
        output = null
        captureAdapter?.removeProcessor(capture); captureAdapter = null; localTrack = null
        for ((track, sink) in tracks.values) try { track.removeSink(sink) } catch (_: Exception) {}
        tracks.clear()
        val elapsed = (SystemClock.elapsedRealtime() - started).coerceAtLeast(0)
        writer?.finish(elapsed)
        return mapOf("path" to (file?.absolutePath ?: ""), "duration" to ((writer?.samplesWritten ?: 0) / 16000).coerceAtLeast(1), "failed" to (writer?.failed == true))
    }
}

/** Bounded mono mixer; writes a standard PCM WAV and never changes live audio. */
internal class PcmCallFile(file: File, private val limit: Long) {
    private val out = RandomAccessFile(file, "rw")
    private val pending = sortedMapOf<Long, IntArray>()
    var samplesWritten = 0L; private set
    @Volatile var full = false; private set
    @Volatile var failed = false
    private var closed = false
    init { out.write(ByteArray(44)) }

    @Synchronized fun add(input: ByteBuffer, rate: Int, channels: Int, frames: Int, at: Long) {
        if (closed || full || failed) return
        val pcm = input.duplicate().order(ByteOrder.LITTLE_ENDIAN)
        val count = frames * 16000 / rate
        for (i in 0 until count) {
            val sample = at + i
            if (sample < samplesWritten || sample > samplesWritten + 32000) continue
            val source = i.toLong() * rate / 16000
            val offset = (source * channels * 2).toInt()
            if (offset + channels * 2 > pcm.limit()) break
            var value = 0
            for (channel in 0 until channels) value += pcm.getShort(offset + channel * 2).toInt()
            pending.getOrPut(sample / 160) { IntArray(160) }[(sample % 160).toInt()] += value / channels
        }
    }

    @Synchronized fun flush(elapsedMs: Long) {
        if (closed || failed) return
        val end = minOf(elapsedMs * 16, (limit - 44) / 2)
        while (samplesWritten + 160 <= end) {
            val values = pending.remove(samplesWritten / 160) ?: IntArray(160)
            val bytes = ByteBuffer.allocate(320).order(ByteOrder.LITTLE_ENDIAN)
            for (value in values) bytes.putShort(value.coerceIn(-32768, 32767).toShort())
            out.write(bytes.array()); samplesWritten += 160
        }
        if (samplesWritten + 160 > (limit - 44) / 2) { full = true; pending.clear() }
    }

    @Synchronized fun finish(elapsedMs: Long) {
        if (closed) return
        try {
            flush(elapsedMs)
            val length = samplesWritten * 2
            val header = ByteBuffer.allocate(44).order(ByteOrder.LITTLE_ENDIAN)
            header.put("RIFF".toByteArray()).putInt((36 + length).toInt()).put("WAVEfmt ".toByteArray())
                .putInt(16).putShort(1).putShort(1).putInt(16000).putInt(32000).putShort(2).putShort(16)
                .put("data".toByteArray()).putInt(length.toInt())
            out.seek(0); out.write(header.array())
        } finally { closed = true; pending.clear(); out.close() }
    }
}
