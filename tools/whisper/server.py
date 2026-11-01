"""Local-only Whisper trial. No intentional audio/transcript retention or logging."""
import io
import os
import threading
from contextlib import asynccontextmanager
from pathlib import Path

import uvicorn
import av
import numpy as np
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from faster_whisper import WhisperModel

ROOT = Path(__file__).resolve().parents[2]
CACHE = ROOT / "storage" / "whisper-trial" / "models"
MODEL_NAME = os.environ.get("WHISPER_MODEL", "large-v3-turbo")
DEVICE = os.environ.get("WHISPER_DEVICE", "cpu")
COMPUTE = os.environ.get("WHISPER_COMPUTE_TYPE", "int8")
MAX_BYTES = 5 * 1024 * 1024
MAX_SECONDS = 90
busy = threading.Lock()
model = None


@asynccontextmanager
async def lifespan(app):
    global model
    model = WhisperModel(
        MODEL_NAME, device=DEVICE, compute_type=COMPUTE,
        download_root=str(CACHE), cpu_threads=4, num_workers=1,
    )
    yield
    model = None


app = FastAPI(lifespan=lifespan, docs_url=None, redoc_url=None, openapi_url=None)


def bounded_audio(data):
    """Stop decoding at the duration limit, even for highly compressed uploads."""
    samples = []
    count = 0
    resampler = av.AudioResampler(format="s16", layout="mono", rate=16000)
    with av.open(io.BytesIO(data)) as container:
        for frame in container.decode(audio=0):
            frame.pts = None
            for output in resampler.resample(frame):
                values = output.to_ndarray().flatten()
                count += len(values)
                if count > MAX_SECONDS * 16000:
                    raise HTTPException(422, "Record at most 90 seconds for this trial.")
                samples.append(values)
        for output in resampler.resample(None):
            values = output.to_ndarray().flatten()
            count += len(values)
            if count > MAX_SECONDS * 16000:
                raise HTTPException(422, "Record at most 90 seconds for this trial.")
            samples.append(values)
    if not samples:
        raise HTTPException(422, "No audio could be decoded.")
    return np.concatenate(samples).astype(np.float32) / 32768.0


@app.middleware("http")
async def local_only(request, call_next):
    from starlette.responses import JSONResponse
    if request.client.host not in ("127.0.0.1", "::1", "testclient"):
        return JSONResponse({"detail": "Local access only."}, status_code=403)
    # Browser requests must go through Laravel's authorized, CSRF-protected route.
    if request.headers.get("origin"):
        return JSONResponse({"detail": "Use the Laravel transcription route."}, status_code=403)
    try:
        size = int(request.headers.get("content-length", "0"))
    except ValueError:
        return JSONResponse({"detail": "Invalid upload size."}, status_code=400)
    if request.method == "POST" and (size <= 0 or size > MAX_BYTES + 65536):
        return JSONResponse({"detail": "Upload must be at most 5 MB."}, status_code=413)
    return await call_next(request)


@app.get("/health")
def health():
    return {"ready": model is not None, "model": MODEL_NAME, "device": DEVICE, "compute_type": COMPUTE}


@app.post("/v1/audio/transcriptions")
def transcribe(file: UploadFile = File(...), prompt: str = Form("")):
    if not busy.acquire(blocking=False):
        raise HTTPException(429, "Whisper is processing another recording. Try again shortly.")
    try:
        data = file.file.read(MAX_BYTES + 1)
        if not data or len(data) > MAX_BYTES:
            raise HTTPException(413, "Upload must be non-empty and at most 5 MB.")
        try:
            audio = bounded_audio(data)
        except HTTPException:
            raise
        except Exception:
            raise HTTPException(422, "That recording could not be decoded.") from None
        segments, info = model.transcribe(
            audio, beam_size=5, initial_prompt=prompt[:2000] or None,
            vad_filter=True, condition_on_previous_text=False,
        )
        text = " ".join(segment.text.strip() for segment in segments).strip()
        return {"text": text[:2000], "language": info.language, "duration": round(len(audio) / 16000, 2)}
    except HTTPException:
        raise
    except Exception:
        raise HTTPException(503, "Local transcription failed. Try again.") from None
    finally:
        busy.release()
        file.file.close()


if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=8178, access_log=False)
