<?php

namespace App\Services\Performance;

use App\Models\{Player, User};
use App\Services\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Illuminate\Validation\ValidationException;

/** Identity binding only: it never creates account ownership or a player profile. */
class TeamSheetProfileLinkService
{
    public const REQUEST_CONTEXT = 'cape-tennis-profile-link-request:v1:';

    public function ready(): bool
    {
        return Schema::hasTable('teamsheet_profile_link_requests')
            && Schema::hasColumn('teamsheet_tennis_player_links', 'link_method')
            && app(TeamSheetTennisImportService::class)->origin() !== null;
    }

    public function assertOwns(User $actor, Player $player, bool $lock = false): void
    {
        // Staff / Super User preview access is deliberately not ownership.
        if (!$lock) { abort_unless(in_array((int) $player->id, $actor->ownedPlayerIds(), true), 403); return; }
        $current = DB::table('players')->where('id', $player->id)->lockForUpdate()->first();
        $pivot = DB::table('user_players')->where('user_id', $actor->id)->where('player_id', $player->id)->lockForUpdate()->first();
        abort_unless($current && ($pivot || (int) $current->userId === (int) $actor->id), 403);
    }

    public function status(User $actor, Player $player): array
    {
        $this->assertOwns($actor, $player);
        if (!$this->ready()) return ['available' => false, 'linked' => false, 'can_unlink' => false];
        $link = DB::table('teamsheet_tennis_player_links')->where('player_id', $player->id)->first();
        return ['available' => true, 'linked' => $link !== null,
            'can_unlink' => $link && $link->link_method === 'self_service' && (int) $link->linked_by === (int) $actor->id];
    }

    public function start(Request $request, Player $player): string
    {
        $actor = $request->user(); $this->assertOwns($actor, $player);
        if (!$this->ready()) $this->fail('TeamSheet profile linking is not available yet. Please try again later.');
        if (DB::table('teamsheet_tennis_player_links')->where('player_id', $player->id)->exists()) $this->fail('This player already has a TeamSheet connection.');
        $origin = app(TeamSheetTennisImportService::class)->origin();
        $state = bin2hex(random_bytes(32)); $issued = now()->timestamp; $expires = $issued + 600;
        DB::transaction(function () use ($actor, $player, $request, $origin, $state, $expires) {
            // One pending attempt per actor/player. Old callbacks cannot replace a new attempt.
            DB::table('users')->where('id', $actor->id)->lockForUpdate()->first();
            $this->assertOwns($actor, $player, true);
            DB::table('teamsheet_profile_link_requests')->where('expires_at', '<', now()->subDay())->limit(500)->delete();
            DB::table('teamsheet_profile_link_requests')->where('user_id', $actor->id)->where('player_id', $player->id)
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
            DB::table('teamsheet_profile_link_requests')->insert(['state_hash' => hash('sha256', $state),
                'user_id' => $actor->id, 'player_id' => $player->id, 'session_hash' => $this->sessionHash($request),
                'origin' => $origin, 'expires_at' => date('Y-m-d H:i:s', $expires), 'created_at' => now(), 'updated_at' => now()]);
        });
        $payload = $this->encode(['state' => $state, 'issued_at' => $issued, 'expires_at' => $expires, 'cape_player_id' => (int) $player->id,
            'redirect_uri' => route('my.tennis.teamsheet.callback')]);
        $signature = hash_hmac('sha256', self::REQUEST_CONTEXT.$payload, config('teamsheet.token'));
        return $origin.'/integrations/cape-tennis/authorize?'.http_build_query(['request' => $payload, 'signature' => $signature], '', '&', PHP_QUERY_RFC3986);
    }

    public function complete(Request $request, string $state, string $code): int
    {
        if (!$this->ready() || !preg_match('/^[a-f0-9]{64}$/', $state) || !preg_match('/^[a-f0-9]{64}$/', $code)) $this->fail('This connection attempt is invalid or expired. Start again from My Tennis.');
        $attempt = $this->attempt($request, $state);
        $response = Http::withToken(config('teamsheet.token'))->acceptJson()->withoutRedirecting()->connectTimeout(5)->timeout(15)
            ->post($attempt->origin.'/api/v1/integrations/cape-tennis/profile-link/exchange', ['code' => $code, 'state' => $state]);
        if (!$response->successful() || strlen($response->body()) > 4096) $this->fail('TeamSheet could not verify this connection. Start again from My Tennis.');
        $claim = $response->json('data');
        if (!is_array($claim) || ($claim['source'] ?? null) !== 'teamsheet' || ($claim['state'] ?? null) !== $state
            || !is_int($claim['teamsheet_player_id'] ?? null) || $claim['teamsheet_player_id'] < 1) $this->fail('TeamSheet returned an invalid connection confirmation.');
        return DB::transaction(function () use ($request, $state, $claim) {
            abort_unless(DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first(), 403);
            $attempt = $this->attempt($request, $state, true);
            $feed = DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            $player = Player::findOrFail($attempt->player_id); $this->assertOwns($request->user(), $player, true);
            if (!$feed || ($feed->origin !== null && $feed->origin !== $attempt->origin)) $this->fail('The TeamSheet connection changed. Please contact support.');
            if (DB::table('teamsheet_tennis_player_links')->where('player_id', $player->id)
                ->orWhere('teamsheet_player_id', $claim['teamsheet_player_id'])->exists()) $this->fail('One of these player profiles is already connected. Existing connections were left unchanged.');
            if (DB::table('teamsheet_tennis_player_links')->count() >= TeamSheetTennisImportService::CAP) $this->fail('The connection limit has been reached. Please contact support.');
            DB::table('teamsheet_tennis_player_links')->insert(['teamsheet_player_id' => $claim['teamsheet_player_id'],
                'player_id' => $player->id, 'reviewed_by' => $request->user()->id, 'reason' => 'Verified sign-in and player relationship on both sites.',
                'link_method' => 'self_service', 'linked_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->update(['origin' => $attempt->origin]);
            DB::table('teamsheet_profile_link_requests')->where('id', $attempt->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            PlatformAuditLogger::log('teamsheet.profile.self-linked', after: ['player_id' => $player->id,
                'teamsheet_player_id' => $claim['teamsheet_player_id']], userId: $request->user()->id);
            return (int) $player->id;
        });
    }

    public function unlink(User $actor, Player $player): void
    {
        $this->assertOwns($actor, $player);
        if (!$this->ready()) $this->fail('TeamSheet profile linking is not available yet.');
        DB::transaction(function () use ($actor, $player) {
            abort_unless(DB::table('users')->where('id', $actor->id)->lockForUpdate()->first(), 403);
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            $this->assertOwns($actor, $player, true);
            $link = DB::table('teamsheet_tennis_player_links')->where('player_id', $player->id)->lockForUpdate()->first();
            if (!$link || $link->link_method !== 'self_service' || (int) $link->linked_by !== (int) $actor->id) $this->fail('This connection must be reviewed by Cape Tennis support.');
            DB::table('teamsheet_tennis_player_links')->where('id', $link->id)->delete();
            PlatformAuditLogger::log('teamsheet.profile.self-unlinked', before: ['player_id' => $player->id,
                'teamsheet_player_id' => $link->teamsheet_player_id], userId: $actor->id);
        });
    }

    private function attempt(Request $request, string $state, bool $lock = false): object
    {
        $query = DB::table('teamsheet_profile_link_requests')->where('state_hash', hash('sha256', $state));
        if ($lock) $query->lockForUpdate();
        $attempt = $query->first();
        if (!$attempt || $attempt->consumed_at || strtotime($attempt->expires_at) <= now()->timestamp
            || (int) $attempt->user_id !== (int) $request->user()->id || !hash_equals($attempt->session_hash, $this->sessionHash($request))
            || $attempt->origin !== app(TeamSheetTennisImportService::class)->origin()) $this->fail('This connection attempt is invalid or expired. Start again from My Tennis.');
        $this->assertOwns($request->user(), Player::findOrFail($attempt->player_id));
        return $attempt;
    }

    private function sessionHash(Request $request): string { return hash('sha256', $request->session()->getId()); }
    private function encode(array $payload): string { return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '='); }
    private function fail(string $message): never { throw ValidationException::withMessages(['teamsheet' => $message]); }
}
