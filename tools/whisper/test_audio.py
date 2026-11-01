"""Small deterministic checks for bounded decoding; no model download required."""
import io
import unittest
import wave

from fastapi import HTTPException
from server import bounded_audio


def wav(seconds, channels=1, rate=8000):
    output = io.BytesIO()
    with wave.open(output, "wb") as recording:
        recording.setnchannels(channels)
        recording.setsampwidth(2)
        recording.setframerate(rate)
        recording.writeframes(b"\0\0" * rate * seconds * channels)
    return output.getvalue()


class BoundedAudioTest(unittest.TestCase):
    def test_resamples_and_downmixes_without_changing_duration(self):
        audio = bounded_audio(wav(2, channels=2))
        self.assertEqual(len(audio), 32000)
        self.assertEqual(audio.ndim, 1)
        self.assertEqual(audio.dtype.name, "float32")

    def test_rejects_audio_above_duration_limit(self):
        with self.assertRaises(HTTPException) as error:
            bounded_audio(wav(91))
        self.assertEqual(error.exception.status_code, 422)

    def test_accepts_exact_duration_limit(self):
        self.assertEqual(len(bounded_audio(wav(90))), 90 * 16000)

    def test_rejects_undecodable_input(self):
        with self.assertRaises(Exception):
            bounded_audio(b"not an audio file")


if __name__ == "__main__":
    unittest.main()
