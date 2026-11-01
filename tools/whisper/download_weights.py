"""Fallback for networks that stall on a single large Hugging Face response."""
import concurrent.futures
import hashlib
import os
import urllib.request
from pathlib import Path

REVISION = "0a363e9161cbc7ed1431c9597a8ceaf0c4f78fcf"
EXPECTED_HASH = "e76620f83d5f5b69efd3d87e3dc180c1bd21df9fbebacfd4335e5e1efcc018da"
SIZE = 1617884929
CHUNK = 4 * 1024 * 1024
ROOT = Path(__file__).resolve().parents[2]
TARGET = ROOT / "storage/whisper-trial/models/models--mobiuslabsgmbh--faster-whisper-large-v3-turbo/snapshots" / REVISION
URL = f"https://huggingface.co/mobiuslabsgmbh/faster-whisper-large-v3-turbo/resolve/{REVISION}/model.bin?download=true"


def fetch(index):
    start = index * CHUNK
    end = min(SIZE, start + CHUNK) - 1
    request = urllib.request.Request(URL, headers={"Range": f"bytes={start}-{end}"})
    for attempt in range(3):
        try:
            with urllib.request.urlopen(request, timeout=45) as response:
                if response.status != 206 or response.headers.get("Content-Range") != f"bytes {start}-{end}/{SIZE}":
                    raise RuntimeError("Model server did not honor the requested byte range")
                data = response.read(CHUNK + 1)
            if len(data) != end - start + 1:
                raise RuntimeError("Incomplete model chunk")
            return start, data
        except Exception:
            if attempt == 2:
                raise


if __name__ == "__main__":
    TARGET.mkdir(parents=True, exist_ok=True)
    temporary = TARGET / "model.download"
    total = (SIZE + CHUNK - 1) // CHUNK
    pending = list(range(total))
    if temporary.exists():
        # Sparse unwritten ranges are all zero. The final full-file hash is authoritative.
        with temporary.open("rb") as existing:
            pending = []
            for index in range(total):
                existing.seek(index * CHUNK)
                part = existing.read(min(CHUNK, SIZE - index * CHUNK))
                if len(part) != min(CHUNK, SIZE - index * CHUNK) or not any(part):
                    pending.append(index)
    completed = total - len(pending)
    print(f"Resuming model download: {completed}/{total} chunks present", flush=True)
    with temporary.open("r+b" if temporary.exists() else "wb") as output, concurrent.futures.ThreadPoolExecutor(max_workers=16) as pool:
        futures = {pool.submit(fetch, index) for index in pending}
        for future in concurrent.futures.as_completed(futures):
            completed += 1
            start, data = future.result()
            output.seek(start)
            output.write(data)
            futures.discard(future)
            if completed % 25 == 0 or completed == total:
                print(f"Model download: {completed}/{total} chunks", flush=True)
    with temporary.open("rb") as source:
        actual = hashlib.file_digest(source, "sha256").hexdigest()
    if actual != EXPECTED_HASH:
        raise RuntimeError("Model checksum failed; refusing to activate downloaded weights")
    os.replace(temporary, TARGET / "model.bin")
    print("Whisper weights downloaded and SHA-256 verified", flush=True)
