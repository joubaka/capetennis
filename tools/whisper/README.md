# Local Whisper trial

This is a local development trial, not a production deployment. Laravel forwards an authorized user's recording to `127.0.0.1:8178`; there is no cloud transcription fallback. The service does not retain audio or transcripts or log their contents. Upload parsing may briefly buffer larger files on local temporary disk; those upload buffers are closed after processing. Audio decoding stops at the 90-second limit. Model downloads require internet once; subsequent inference uses local files.

## Setup and start

Use Python 3.12 and an isolated environment (do not install into the system Python):

```powershell
python -m venv storage/whisper-trial/venv
storage/whisper-trial/venv/Scripts/python.exe -m pip install -r tools/whisper/requirements-lock.txt
powershell -NoProfile -File tools/whisper/start.ps1
```

The default is multilingual `large-v3-turbo` on CPU with INT8 and four threads. The first start downloads the model into ignored `storage/whisper-trial/models` and may take several minutes. `GET http://127.0.0.1:8178/health` reports ready after the model loads. Keep the launcher running while testing. Stop it with Ctrl+C. Do not bind this trial to a network interface or expose it through a proxy.

GPU inference is optional and requires compatible CUDA/cuDNN libraries; detecting an NVIDIA card alone does not establish that these are installed. Set `WHISPER_DEVICE=cuda` and `WHISPER_COMPUTE_TYPE=int8_float16` only after verifying those dependencies. CPU is the conservative trial default.

Enable the local Laravel transcription provider as documented in the assistant integration. No Gemini key is needed for local transcription. Uploads are limited to 5 MB and 90 seconds. Record short commands (10–20 seconds) first. The service rejects concurrent transcription rather than loading multiple copies of the model.

If the model host stalls on large downloads, `storage/whisper-trial/venv/Scripts/python.exe tools/whisper/download_weights.py` downloads the pinned trial weights in bounded HTTPS ranges and verifies their SHA-256 before activation. Let the regular first start obtain the small configuration/tokenizer files first. Do not run both downloaders together.

For a recording saved on this computer, use `storage/whisper-trial/venv/Scripts/python.exe tools/whisper/transcribe_file.py C:/path/to/recording.wav`. This prints the transcript and processing time without saving the result or sending a command to Laravel.

## Accent and names acceptance

Synthetic speech only checks the pipeline; it cannot establish recognition of the user's accent. In the assistant, record these with actual event player names, then review the text without sending the command:

1. “Schedule the under-fourteen boys on Saturday from eight thirty.”
2. “Move [first player name] against [second player name] to court three at quarter past nine.”
3. “Add the same categories as Leg One to this event.”
4. Repeat a command with Afrikaans names, natural pauses, and normal background noise.

Some commands deliberately test transcription beyond supported assistant actions. Recognizing words does not mean the assistant can execute those actions. For 20–30 recordings, compare exact player names, dates, times and court numbers; note transcription latency too. Do not automatically submit transcripts or turn uncertain names into player selections. Repeat several recordings with and without relevant name hints to detect hints introducing words that were not spoken.
