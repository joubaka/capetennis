<?php

namespace App\Services\Assistant;

use App\Models\{Event, Player};
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/** Speech-to-text only: never submits a message or performs a tournament action. */
final class AssistantTranscriptionProvider
{
    public function isLocal(): bool
    {
        return config('event-assistant.transcription.provider', 'gemini') === 'whisper';
    }

    public function available(): bool
    {
        return $this->isLocal()
            ? app()->environment('local', 'testing') && $this->localEndpoint() !== null
            : config('event-assistant.transcription.provider', 'gemini') === 'gemini' && app(AssistantProvider::class)->available();
    }

    public function transcribe(UploadedFile $audio, Event $event): string
    {
        if (! $this->available()) {
            throw new AssistantProviderException('Voice transcription is unavailable in the current configuration. You can type your request instead.');
        }
        if (! $this->isLocal()) return app(AssistantProvider::class)->transcribe($audio);

        // No external fallback: event names and audio stay on this machine for this trial.
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(110)
                ->withOptions(['allow_redirects' => false])
                ->attach('file', file_get_contents($audio->getRealPath()), 'recording', ['Content-Type' => $audio->getMimeType()])
                ->post($this->localEndpoint(), ['prompt' => $this->nameHints($event)]);
        } catch (ConnectionException) {
            throw new AssistantProviderException('Local Whisper could not connect or finish in time. Start the local voice service or try a shorter recording.');
        }
        if (! $response->successful()) {
            throw new AssistantProviderException('Local Whisper could not transcribe that recording. Check the local voice service or try a shorter recording.');
        }
        $text = $response->json('text');
        if (! is_string($text) || trim($text) === '') {
            throw new AssistantProviderException('No speech could be transcribed. Please record again or type your request.');
        }

        return mb_substr(trim($text), 0, 2000);
    }

    private function localEndpoint(): ?string
    {
        $url = trim((string) config('event-assistant.transcription.endpoint'));
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'http'
            || ! in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return null;

        return $url;
    }

    private function nameHints(Event $event): string
    {
        // Query names only, scoped through the event's draws/registrations, never contact details.
        $names = Player::query()->whereHas('registrations', function ($registrations) use ($event) {
            $registrations->where(function ($query) use ($event) {
                $query->whereHas('draws', fn ($draws) => $draws->where('event_id', $event->id))
                    ->orWhereHas('categoryEvents', fn ($categories) => $categories->where('event_id', $event->id));
            });
        })->orderBy('surname')->orderBy('name')->limit(60)->get(['players.id', 'players.name', 'players.surname'])
            ->map(fn ($player) => trim(preg_replace('/\s+/u', ' ', $player->name.' '.$player->surname)))
            ->filter()->unique()->all();

        return mb_substr('Tennis tournament. Round robin, draw, court, venue, under fourteen, singles, doubles. Player names: '.implode(', ', $names), 0, 1500);
    }
}
