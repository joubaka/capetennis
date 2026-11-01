"""Manually transcribe a local recording without submitting a tournament action."""
import argparse
import json
import mimetypes
import time
import urllib.request
import uuid
from pathlib import Path


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("audio", type=Path)
    parser.add_argument("--prompt", default="Tennis tournament. Singles, doubles, round robin, court.")
    args = parser.parse_args()
    data = args.audio.read_bytes()
    if not data or len(data) > 5 * 1024 * 1024:
        parser.error("Recording must be non-empty and at most 5 MB")
    boundary = uuid.uuid4().hex
    mime = mimetypes.guess_type(args.audio.name)[0] or "application/octet-stream"
    body = (
        f'--{boundary}\r\nContent-Disposition: form-data; name="prompt"\r\n\r\n{args.prompt[:2000]}\r\n'
        f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="recording"\r\n'
        f'Content-Type: {mime}\r\n\r\n'
    ).encode() + data + f"\r\n--{boundary}--\r\n".encode()
    request = urllib.request.Request(
        "http://127.0.0.1:8178/v1/audio/transcriptions", data=body,
        headers={"Content-Type": f"multipart/form-data; boundary={boundary}"},
    )
    started = time.perf_counter()
    with urllib.request.urlopen(request, timeout=115) as response:
        result = json.load(response)
    print(json.dumps({**result, "elapsed_seconds": round(time.perf_counter() - started, 2)}, ensure_ascii=False))
