# Local Whisper voice trial

Whisper is an optional experiment. The active local assistant was switched back to TeamSheet's Gemini transcription approach at the user's request: mono PCM WAV recording, short-silence stopping, and a 30-second recording cap. Cape Tennis still requires transcript review before sending a command. The local Whisper service is stopped; its isolated environment and model are retained for an explicit future trial.

This trial separates speech recognition from Gemini request interpretation. It is enabled only in Laravel's `local` or `testing` environment and only accepts a literal loopback HTTP endpoint. It does not fall back to a cloud provider.

After starting the local Whisper service, set these values in the local environment:

```dotenv
EVENT_ASSISTANT_TRANSCRIPTION_PROVIDER=whisper
WHISPER_TRANSCRIPTION_URL=http://127.0.0.1:8178/v1/audio/transcriptions
```

The service accepts multipart `POST /v1/audio/transcriptions`, with a `file` audio upload and a `prompt` text field, and returns `{"text":"recognised words"}`. The prompt contains tennis terms and up to 60 names from registrations in the current event's draws or categories, bounded to 1,500 characters. Contact details are excluded. Name hints aid transcription; they do not select a player or authorize any action.

Open the event's Tournament assistant, click **Speak**, then click **Stop recording** when finished. Start with 10–20 second utterances for this CPU trial; recording stops automatically after 30 seconds. Allow almost two minutes for CPU transcription. The local provider request times out after 110 seconds, before the local Apache PHP handler's configured 120-second execution limit, and the browser waits up to 125 seconds. Read and correct the resulting text before clicking **Send**. The AI interpretation checkbox is independent and can remain off. Transcription alone creates no conversation, proposal, registration or draw.

For an accent check, use several short recordings with known expected wording: player surnames, English and Afrikaans names, court numbers, dates, times, singles/doubles and round-robin terminology. Compare raw transcription before editing. Record which names and numbers were missed, alongside processing time; a successful synthetic test is not evidence of accuracy for a real speaker.

To return to the existing Gemini transcription path, remove the local override or set `EVENT_ASSISTANT_TRANSCRIPTION_PROVIDER=gemini`. That path continues to require explicit provider consent and the configured Gemini credentials. No production deployment is included in this trial.
