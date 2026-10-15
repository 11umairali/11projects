package com.dahify.dahimail

import java.io.File
import java.nio.ByteBuffer
import java.nio.ByteOrder
import org.junit.Assert.*
import org.junit.Test

class PcmCallFileTest {
    @Test fun mixesParticipantsWithoutChangingLiveBuffersAndWritesValidWav() {
        val file = File.createTempFile("pcm-test", ".wav")
        try {
            val writer = PcmCallFile(file, 1024 * 1024)
            val local = ByteBuffer.allocate(960).order(ByteOrder.LITTLE_ENDIAN)
            repeat(480) { local.putShort(1000) }; local.rewind()
            val remote = ByteBuffer.allocate(320).order(ByteOrder.LITTLE_ENDIAN)
            repeat(160) { remote.putShort(2000) }; remote.rewind()
            writer.add(local, 48000, 1, 480, 0)
            writer.add(remote, 16000, 1, 160, 0)
            writer.finish(10)
            val bytes = file.readBytes()
            assertEquals(364, bytes.size)
            assertEquals("RIFF", String(bytes, 0, 4))
            assertEquals("WAVE", String(bytes, 8, 4))
            val pcm = ByteBuffer.wrap(bytes).order(ByteOrder.LITTLE_ENDIAN)
            assertEquals(16000, pcm.getInt(24))
            assertEquals(320, pcm.getInt(40))
            repeat(160) { assertEquals(3000, pcm.getShort(44 + it * 2).toInt()) }
            assertEquals(0, local.position()); assertEquals(1000, local.getShort(0).toInt())
        } finally { file.delete() }
    }
    @Test fun clipsMixedAudioBoundsFileSizeAndIgnoresLateCallbacks() {
        val file = File.createTempFile("pcm-test", ".wav")
        try {
            val writer = PcmCallFile(file, 364)
            val input = ByteBuffer.allocate(320).order(ByteOrder.LITTLE_ENDIAN)
            repeat(160) { input.putShort(30000) }; input.rewind()
            repeat(2) { writer.add(input, 16000, 1, 160, 0) }
            writer.flush(1000)
            assertTrue(writer.full)
            writer.finish(1000)
            writer.add(input, 16000, 1, 160, 160)
            assertEquals(364, file.length())
            assertEquals(32767, ByteBuffer.wrap(file.readBytes()).order(ByteOrder.LITTLE_ENDIAN).getShort(44).toInt())
        } finally { file.delete() }
    }
}
