<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItem;
use App\Models\ClothingSize;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\File;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamPaymentOrder;
use App\Models\TeamRegion;
use App\Models\User;
use App\Support\EventTypePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTypeRenderedJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('ct_testing', config('database.connections.mysql.database'));
        $this->assertSame('ct_testing', DB::connection()->getDatabaseName());
        $this->assertSame('ct_testing', DB::selectOne('SELECT DATABASE() AS database_name')->database_name);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(10, 0));
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        foreach (array_keys(config('mail.mailers', [])) as $name) {
            config(["mail.mailers.{$name}" => ['transport' => 'array']]);
        }
    }

    #[DataProvider('storedTypes')]
    public function test_stored_identities_render_their_existing_public_journey(
        int $id, string $name, ?string $code, ?int $family, string $template, string $marker
    ): void {
        $event = $this->event($id, $name, $code, $family);
        File::forceCreate(['event_id' => $event->id, 'name' => 'Synthetic event programme.pdf', 'path' => 'public/files/synthetic-journey.pdf']);
        $before = $this->rowCounts();

        $response = $this->get(route('events.show', $event))->assertOk()
            ->assertViewIs('frontend.event.show')->assertSee('Synthetic event information')
            ->assertSee($marker, false)->assertSee('R450.00')
            ->assertDontSee('aria-label="Upload document"', false)
            ->assertDontSee('data-bs-target="#addFileModal"', false);
        $this->assertSame($template, EventTypePresentation::template($response->viewData('event')));
        if ($template !== 'interprovincial-trials') {
            $response->assertSee('Synthetic event programme.pdf');
        } else {
            $response->assertDontSee('Synthetic event programme.pdf')->assertDontSee('Click on a Region below to register');
        }
        $this->assertSame($before, $this->rowCounts(), 'A public event GET must not create or remove workflow records.');
        $this->export($response, 'type-'.$id.'-'.$template.'-guest');
    }

    public static function storedTypes(): array
    {
        return [
            'individual' => [1, 'Individual', 'individual', 1, 'individual', 'class="row individual-event-view"'],
            'legacy camp family zero' => [2, 'camp', null, 0, 'default', 'Event documents'],
            'team' => [3, 'team event', 'team', 2, 'team', 'class="team-public-event col-xl-12"'],
            'high school' => [4, 'High School Team', 'team', 2, 'team', 'class="team-public-event col-xl-12"'],
            'cavaliers' => [5, 'Cavaliers Trials', 'individual', 1, 'cavaliers_trials', 'Nominations'],
            'individual pdf' => [6, 'Individual Pdf', 'individual', 1, 'individual', 'class="row individual-event-view"'],
            'primary schools' => [7, 'Platteland Primary Schools', 'team', 2, 'team', 'class="team-public-event col-xl-12"'],
            'high schools' => [8, 'Platteland High Schools', 'team', 2, 'team', 'class="team-public-event col-xl-12"'],
            'parent child' => [9, 'ParentChildDoubles', 'individual', 1, 'parentChildDoubles', 'Meals'],
            'interoro is generic' => [12, 'Interoro Trials', 'individual', 1, 'default', 'Event documents'],
            'actual local interpro trials' => [13, 'Interpro Trials', 'interprovincial-trials', 1, 'interprovincial-trials', 'Trial nominations'],
            'masters' => [14, 'Masters', 'masters', 1, 'masters', 'Masters invitations'],
            'missing zero type' => [0, '', null, null, 'default', 'Event documents'],
            'legacy interpro separate' => [13, 'Interpro', 'individual', 1, 'interpro', 'Click on a Region below to register'],
            'semantic trials team family' => [203, 'Regional trial programme', 'interprovincial-trials', 2, 'interprovincial-trials', 'Trial nominations'],
        ];
    }

    #[DataProvider('trialFamilies')]
    public function test_trials_nominations_keep_publication_sponsorship_and_linked_decline_boundaries(int $family): void
    {
        $event = $this->event(203, 'Regional trial programme', EventType::INTERPROVINCIAL_TRIALS_CODE, $family);
        $published = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        $private = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => false]);
        $foreign = CategoryEvent::factory()->create(['event_id' => $this->event(204, 'Foreign trials', EventType::INTERPROVINCIAL_TRIALS_CODE, $family)->id, 'nominations_published' => true]);
        $nomination = $this->nomination($event, $published, 'Published', 'Nominee');
        $this->nomination($event, $private, 'Private', 'Nominee');
        $this->nomination($foreign->event, $foreign, 'Foreign', 'Nominee');
        $ordinary = User::factory()->create(['name' => 'Synthetic sponsor']);
        $linked = User::factory()->create(['name' => 'Synthetic linked account']);
        $linked->players()->attach($nomination->player_id);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'queued', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $ordinary->id]);
        $invitation = InterprovincialTrialInvitation::create([
            'event_id' => $event->id, 'category_event_id' => $published->id, 'nomination_id' => $nomination->id,
            'player_id' => $nomination->player_id, 'batch_id' => $batch->id, 'status' => 'sent',
            'recipient_email' => 'private-invitation-recipient@example.test',
        ]);
        $register = route('interprovincial-trials.nominations.register', [$event, $published, $nomination]);
        $decline = route('interprovincial-trials.invitations.decline', $invitation);
        $before = $this->rowCounts();

        $guest = $this->get(route('events.show', $event))->assertOk()->assertSee('Sign in to register')
            ->assertDontSee('action="'.$register.'"', false)->assertDontSee($decline, false);
        $this->assertNominationPrivacy($guest);
        $this->export($guest, 'trials-family-'.$family.'-guest');

        $sponsor = $this->actingAs($ordinary)->get(route('events.show', $event))->assertOk()
            ->assertSee('action="'.$register.'"', false)->assertDontSee($decline, false);
        $this->assertNominationPrivacy($sponsor);
        $this->export($sponsor, 'trials-family-'.$family.'-sponsor');

        $owner = $this->actingAs($linked)->get(route('events.show', $event))->assertOk()
            ->assertSee('action="'.$register.'"', false)->assertSee($decline, false);
        $this->assertNominationPrivacy($owner);
        $this->assertSame($before, $this->rowCounts());
        $this->assertDatabaseMissing('user_players', ['user_id' => $ordinary->id, 'player_id' => $nomination->player_id]);
        $this->export($owner, 'trials-family-'.$family.'-linked');
    }

    public static function trialFamilies(): array
    {
        return ['individual base' => [EventType::INDIVIDUAL], 'team base' => [EventType::TEAM]];
    }

    public function test_legacy_interpro_draw_scoring_documents_and_draft_event_access_remain_event_scoped(): void
    {
        $event = $this->event(13, 'Interpro', 'individual', EventType::INDIVIDUAL);
        $published = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Public synthetic draw', 'published' => true]);
        $draft = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Private synthetic draft', 'published' => false]);
        $foreign = $this->event(1, 'Individual', 'individual', EventType::INDIVIDUAL);
        Draw::factory()->create(['event_id' => $foreign->id, 'drawName' => 'Foreign synthetic draw', 'published' => true]);
        File::forceCreate(['event_id' => $event->id, 'name' => 'Public synthetic document.pdf', 'path' => 'public/files/synthetic-journey.pdf']);
        File::forceCreate(['event_id' => $foreign->id, 'name' => 'Foreign synthetic document.pdf', 'path' => 'public/files/synthetic-foreign.pdf']);
        $ordinary = User::factory()->create();
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $foreignAdmin = User::factory()->create()->assignRole('admin');
        EventAdmin::create(['event_id' => $foreign->id, 'user_id' => $foreignAdmin->id]);
        $scorer = User::factory()->create();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $super = User::factory()->create()->assignRole(Role::findOrCreate('super-user', 'web'));
        $before = $this->rowCounts();
        $scoreUrl = route('frontend.scoring.workspace', ['event' => $event, 'draw' => $published->id]);

        foreach ([null, $ordinary, $foreignAdmin, $admin, $scorer, $super] as $user) {
            auth()->forgetGuards();
            if ($user) $this->actingAs($user);
            $response = $this->get(route('events.show', $event))->assertOk()
                ->assertSee('Public synthetic draw')->assertSee('Public synthetic document.pdf')
                ->assertDontSee('Foreign synthetic draw')->assertDontSee('Foreign synthetic document.pdf');
            $canPreview = in_array($user?->id, [$admin->id, $super->id], true);
            $canScore = $canPreview || $user?->id === $scorer->id;
            $canPreview ? $response->assertSee($draft->drawName) : $response->assertDontSee($draft->drawName);
            $canScore ? $response->assertSee($scoreUrl, false) : $response->assertDontSee($scoreUrl, false);
            $canPreview ? $response->assertSee('name="myFile"', false) : $response->assertDontSee('name="myFile"', false);
            $this->export($response, 'legacy-interpro-'.($user ? 'actor-'.$user->id : 'guest'));
        }
        $this->assertSame($before, $this->rowCounts());

        $event->update(['published' => false]);
        $before = $this->rowCounts();
        foreach ([$ordinary, $foreignAdmin, $scorer] as $user) {
            $this->actingAs($user)->get(route('events.show', $event))->assertNotFound()->assertDontSee('Synthetic event information');
        }
        foreach ([$admin, $super] as $user) {
            $this->actingAs($user)->get(route('events.show', $event))->assertOk()->assertSee('Synthetic event information');
        }
        $this->assertSame($before, $this->rowCounts());
    }

    #[DataProvider('directoryTypes')]
    public function test_region_directory_preserves_roster_links_payer_controls_and_private_foreign_boundaries(int $id, int $family): void
    {
        $event = $this->event($id, 'Synthetic regional event', $family === EventType::TEAM ? 'team' : 'individual', $family);
        $region = TeamRegion::create(['region_name' => 'Synthetic Northern/Southern Regional Tennis Association With A Long Name', 'clothing_order' => false]);
        $uniqueRegion = TeamRegion::create(['region_name' => 'Synthetic Eastern/Western Regional Tennis Association With Another Long Name', 'clothing_order' => false]);
        $event->regions()->attach($region);
        $event->regions()->attach($uniqueRegion);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $publicTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'name' => 'Synthetic public team', 'published' => true]);
        $privateTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'name' => 'Synthetic private team', 'published' => false]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $this->event(1, 'Individual', 'individual', 1)->id]);
        $foreignCategory->event->regions()->attach($region);
        $foreignTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $foreignCategory->id, 'name' => 'Foreign isolated team', 'published' => true]);
        $ambiguousTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => null, 'name' => 'Ambiguous shared-region team', 'published' => true]);
        foreach ([[$publicTeam, 'Public roster player'], [$privateTeam, 'Private roster player'], [$foreignTeam, 'Foreign roster player'], [$ambiguousTeam, 'Ambiguous roster player']] as [$team, $name]) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Synthetic', 'email' => 'private-roster-contact@example.test']);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        }
        $importedTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'name' => 'Synthetic imported team', 'published' => true, 'noProfile' => true]);
        $imported = NoProfileTeamPlayer::create(['team_id' => $importedTeam->id, 'name' => 'Imported', 'surname' => 'Synthetic roster', 'rank' => 1, 'pay_status' => 0]);
        $legacyTeam = Team::factory()->create(['region_id' => $uniqueRegion->id, 'category_event_id' => null, 'name' => 'Unique region legacy team', 'published' => true]);
        $legacyPlayer = Player::factory()->create(['name' => 'Unique legacy', 'surname' => 'Synthetic player']);
        TeamPlayer::create(['team_id' => $legacyTeam->id, 'player_id' => $legacyPlayer->id, 'rank' => 1, 'pay_status' => 0]);
        $paidPlayer = Player::factory()->create(['name' => 'Paid public', 'surname' => 'Synthetic player']);
        TeamPlayer::create(['team_id' => $publicTeam->id, 'player_id' => $paidPlayer->id, 'rank' => 2, 'pay_status' => 1]);
        $sponsor = User::factory()->create();
        $payer = User::factory()->create();
        TeamPaymentOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $publicTeam->id, 'player_id' => $paidPlayer->id,
            'pay_status' => true, 'total_amount' => 450, 'payfast_amount_due' => 450]);
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $scorer = User::factory()->create();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $before = $this->rowCounts();
        $withdraw = route('team.player.withdraw', [$publicTeam->id, $paidPlayer->id, $event->id]);
        foreach (['guest' => null, 'sponsor' => $sponsor, 'payer' => $payer, 'admin' => $admin, 'scorer' => $scorer] as $actor => $user) {
            auth()->forgetGuards();
            if ($user) $this->actingAs($user);
            $response = $this->get(route('events.show', $event))->assertOk()
                ->assertSee('Public roster player')->assertSee('Unique legacy Synthetic player')
                ->assertDontSee('Private roster player')->assertDontSee('Foreign roster player')
                ->assertDontSee('Ambiguous roster player')->assertDontSee('Ambiguous shared-region team')
                ->assertSee('Imported Synthetic roster')
                ->assertSee(route('player.create', ['type' => 'noProfile', 'noProfile' => $imported->id, 'team' => $importedTeam->id, 'event' => $event->id]))
                ->assertDontSee('Foreign isolated team')->assertDontSee('private-roster-contact@example.test')
                ->assertSee(route('team.payment.payfast', [$publicTeam->id, $publicTeam->team_players->first()->player_id, $event->id]), false);
            $actor === 'payer' ? $response->assertSee($withdraw, false) : $response->assertDontSee($withdraw, false);
            if ($family === EventType::TEAM) $response->assertSee('team-region-directory', false)->assertSee('team-region-kicker', false);
            $this->assertSame(1, substr_count($response->getContent(), 'id="event-region-picker-'.$event->id.'"'));
            $this->assertSame(2, substr_count($response->getContent(), 'data-bs-target="#team-'.$event->id.'-'));
            $this->export($response, 'directory-type-'.$id.'-'.$actor);
        }
        $this->assertSame($before, $this->rowCounts());
    }

    public static function directoryTypes(): array
    {
        return ['ordinary team' => [3, EventType::TEAM], 'legacy interpro' => [13, EventType::INDIVIDUAL]];
    }

    public function test_legacy_interpro_directory_uses_prepared_regions_and_retains_the_empty_state(): void
    {
        $event = $this->event(13, 'Interpro', 'individual', EventType::INDIVIDUAL);
        $before = $this->rowCounts();
        $empty = $this->get(route('events.show', $event))->assertOk()->assertSee('Regions are not configured for this event.');
        $this->assertSame($before, $this->rowCounts());
        $this->export($empty, 'legacy-interpro-empty-directory');
        $region = TeamRegion::create(['region_name' => 'Synthetic prepared Interpro region', 'clothing_order' => false]);
        $event->regions()->attach($region);
        $before = $this->rowCounts();
        $response = $this->get(route('events.show', $event))->assertOk()
            ->assertDontSee('Regions are not configured for this event.')
            ->assertSee('id="event-region-picker-'.$event->id.'"', false)
            ->assertSee('Synthetic prepared Interpro region')->assertSee('No teams listed for this region yet.');
        $this->assertSame([$region->id], $response->viewData('regions')->pluck('id')->all());
        $this->assertSame($before, $this->rowCounts());
        $this->export($response, 'legacy-interpro-configured-region');
    }

    #[DataProvider('directoryTypes')]
    public function test_directory_clothing_controls_match_existing_endpoint_capability_and_preserve_payer_history(int $id, int $family): void
    {
        $event = $this->event($id, 'Synthetic regional event', $family === EventType::TEAM ? 'team' : 'individual', $family);
        $region = TeamRegion::create(['region_name' => 'Synthetic clothing region', 'clothing_order' => true, 'clothing_admin' => true]);
        $event->regions()->attach($region);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'published' => true]);
        $player = Player::factory()->create(['name' => 'Synthetic clothing', 'surname' => 'Player']);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $importedTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'published' => true, 'noProfile' => true]);
        NoProfileTeamPlayer::create(['team_id' => $importedTeam->id, 'name' => 'Imported', 'surname' => 'Clothing player', 'rank' => 1]);
        $item = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'Synthetic shirt', 'price' => 125]);
        $size = ClothingSize::create(['item_type' => $item->id, 'size' => 'Synthetic size', 'ordering' => 1]);
        $payer = User::factory()->create();
        $order = ClothingOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id,
            'pay_status' => 1, 'total' => 125, 'subtotal' => 125]);
        ClothingOrderItem::create(['clothing_order_id' => $order->id, 'clothing_order_item_id' => $item->id, 'clothing_item_size' => $size->id,
            'qty' => 1, 'price' => 125, 'line_total' => 125, 'item_name' => 'Historical synthetic shirt', 'size_name' => 'Synthetic size']);
        $before = $this->rowCounts();
        $response = $this->actingAs($payer)->get(route('events.show', $event))->assertOk()->assertSee('Historical synthetic shirt');
        if ($family === EventType::TEAM) {
            $response->assertSee('Order clothing for Synthetic clothing Player')
                ->assertSee('Link a player profile before ordering clothing.')
                ->assertSee('id="clothing-order-modal"', false);
        } else {
            $response->assertDontSee('Order clothing for Synthetic clothing Player')
                ->assertDontSee('Link a player profile before ordering clothing.')
                ->assertDontSee('Clothing orders open for');
        }
        $this->assertSame($before, $this->rowCounts());
        $this->export($response, 'directory-type-'.$id.'-clothing-history');
        if ($family !== EventType::TEAM) {
            $this->postJson(route('clothingOrder.store'), ['event_id' => $event->id, 'region_id' => $region->id,
                'team_id' => $team->id, 'player_id' => $player->id, 'request_token' => (string) Str::uuid(),
                'items' => [$item->id => ['size' => $size->id, 'qty' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('region_id');
            $this->assertSame($before, $this->rowCounts());
        }
    }

    #[DataProvider('directoryTypes')]
    public function test_supported_directory_registration_remains_available_to_an_unlinked_sponsor(int $id, int $family): void
    {
        $event = $this->event($id, 'Synthetic regional event', $family === EventType::TEAM ? 'team' : 'individual', $family);
        $region = TeamRegion::create(['region_name' => 'Synthetic registration region', 'clothing_order' => false]);
        $event->regions()->attach($region);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $sponsor = User::factory()->create();
        $this->actingAs($sponsor);
        $response = $this->get(route('team.payment.payfast', [$team, $player, $event]));
        $this->assertSame(200, $response->status(), 'An eligible unlinked sponsor can open the existing team checkout.');
        $order = TeamPaymentOrder::sole();
        $this->assertSame($sponsor->id, (int) $order->user_id);
        $this->assertSame($event->id, (int) $order->event_id);
        $this->assertSame($team->id, (int) $order->team_id);
        $this->assertSame($player->id, (int) $order->player_id);
        $this->assertSame('450.00', number_format((float) $order->total_amount, 2, '.', ''));
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertSame(0, (int) $team->team_players()->sole()->pay_status);
    }

    public function test_directory_scope_does_not_break_existing_legacy_order_payer_access(): void
    {
        $event = $this->event(13, 'Interpro', 'individual', EventType::INDIVIDUAL);
        $otherEvent = $this->event(1, 'Individual', 'individual', EventType::INDIVIDUAL);
        $region = TeamRegion::create(['region_name' => 'Synthetic shared historical region', 'clothing_order' => false]);
        $event->regions()->attach($region);
        $otherEvent->regions()->attach($region);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => null, 'published' => true, 'name' => 'Ambiguous historical roster']);
        $pendingPlayer = Player::factory()->create(['name' => 'Private pending', 'surname' => 'Historical player']);
        $paidPlayer = Player::factory()->create(['name' => 'Private paid', 'surname' => 'Historical player']);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $pendingPlayer->id, 'rank' => 1, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $paidPlayer->id, 'rank' => 2, 'pay_status' => 1]);
        $payer = User::factory()->create();
        $otherUser = User::factory()->create();
        $pending = TeamPaymentOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $pendingPlayer->id,
            'pay_status' => false, 'total_amount' => 450, 'payfast_amount_due' => 450]);
        $paid = TeamPaymentOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $paidPlayer->id,
            'pay_status' => true, 'total_amount' => 450, 'payfast_amount_due' => 450, 'withdrawn_at' => now()->subMinute()]);
        $before = $this->rowCounts();
        $this->actingAs($payer)->get(route('events.show', $event))->assertOk()
            ->assertDontSee('Ambiguous historical roster')->assertDontSee('Private pending Historical player')->assertDontSee('Private paid Historical player');
        $checkout = $this->get(route('team.checkout', $pending));
        $this->assertSame(200, $checkout->status());
        $this->assertSame($pending->id, $checkout->viewData('order')->id);
        $refund = $this->get(route('team.player.refund.choose', [$team, $paidPlayer, $event]));
        $this->assertSame(200, $refund->status());
        $this->assertSame($paid->id, $refund->viewData('order')->id);
        $this->actingAs($otherUser)->get(route('team.checkout', $pending))->assertForbidden();
        $this->get(route('team.player.refund.choose', [$team, $paidPlayer, $event]))->assertRedirect()->assertSessionHasErrors();
        $this->assertSame($before, $this->rowCounts());
        $this->assertFalse((bool) $pending->fresh()->pay_status);
        $this->assertTrue((bool) $paid->fresh()->pay_status);
    }

    public function test_cavaliers_populated_nominations_keep_publication_and_event_isolation(): void
    {
        $event = $this->event(5, 'Cavaliers Trials', 'individual', EventType::INDIVIDUAL);
        $public = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        $private = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => false]);
        $foreignEvent = $this->event(1, 'Individual', 'individual', EventType::INDIVIDUAL);
        $foreign = CategoryEvent::factory()->create(['event_id' => $foreignEvent->id, 'nominations_published' => true]);
        $this->nomination($event, $public, 'Published Cavaliers', 'Nominee');
        $this->nomination($event, $private, 'Private Cavaliers', 'Nominee');
        $this->nomination($foreignEvent, $foreign, 'Foreign Cavaliers', 'Nominee');
        $before = $this->rowCounts();
        $response = $this->get(route('events.show', $event))->assertOk()->assertSee('Published Cavaliers Nominee')
            ->assertSee('Not Published')->assertDontSee('Private Cavaliers Nominee')->assertDontSee('Foreign Cavaliers Nominee')
            ->assertDontSee('private-nominee-contact@example.test');
        $this->assertSame($before, $this->rowCounts());
        $this->export($response, 'cavaliers-published-nominations');
    }

    public function test_masters_populated_public_invitations_hide_reserves_private_batches_and_foreign_lists(): void
    {
        $event = $this->event(14, 'Masters', EventType::MASTERS_CODE, EventType::INDIVIDUAL);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $public = MastersInvitationBatch::create(['event_id' => $event->id, 'series_id' => 1, 'ranking_run_id' => 'synthetic-public-run',
            'status' => 'sent', 'public_list_published' => true, 'registration_open' => true]);
        foreach ([['Available Masters', MastersInvitation::INVITED], ['Pending Masters', MastersInvitation::ACCEPTED_PENDING_PAYMENT],
            ['Confirmed Masters', MastersInvitation::PAID_CONFIRMED], ['Private reserve Masters', MastersInvitation::RESERVE]] as $index => [$name, $status]) {
            $this->mastersInvitation($public, $category, $name, $status, $index + 1);
        }
        $draft = MastersInvitationBatch::create(['event_id' => $event->id, 'series_id' => 1, 'ranking_run_id' => 'synthetic-private-run',
            'status' => 'draft', 'public_list_published' => false]);
        $this->mastersInvitation($draft, $category, 'Private draft Masters', MastersInvitation::INVITED, 1);
        $foreignEvent = $this->event(14, 'Masters', EventType::MASTERS_CODE, EventType::INDIVIDUAL);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $foreignEvent->id]);
        $foreign = MastersInvitationBatch::create(['event_id' => $foreignEvent->id, 'series_id' => 1, 'ranking_run_id' => 'synthetic-foreign-run',
            'status' => 'sent', 'public_list_published' => true]);
        $this->mastersInvitation($foreign, $foreignCategory, 'Foreign Masters', MastersInvitation::INVITED, 1);
        $before = $this->rowCounts();
        $response = $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Available Masters Player')->assertSee('Pending Masters Player')->assertSee('Confirmed Masters Player')
            ->assertSee('Complete payment')->assertSee('Registered')->assertSee('Decline invitation')
            ->assertDontSee('Private reserve Masters')->assertDontSee('Private draft Masters')->assertDontSee('Foreign Masters Player');
        $this->assertSame($public->id, $response->viewData('mastersBatch')->id);
        $this->assertSame($before, $this->rowCounts());
        $this->export($response, 'masters-populated-public-invitations');
    }

    public function test_parent_child_paid_pair_keeps_existing_entry_count_public_display_and_payer_scope(): void
    {
        $event = $this->event(9, 'ParentChildDoubles', 'individual', EventType::INDIVIDUAL);
        $event->update(['results_published' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $child = Player::factory()->create(['name' => 'Synthetic child', 'surname' => 'Pair']);
        $parent = Player::factory()->create(['name' => 'Synthetic parent', 'surname' => 'Pair']);
        $registration = Registration::factory()->create();
        $registration->players()->attach([$child->id, $parent->id]);
        $payer = User::factory()->create();
        $other = User::factory()->create();
        CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'user_id' => $payer->id]);
        $unpaid = Registration::factory()->create();
        $unpaidPlayer = Player::factory()->create(['name' => 'Private unpaid', 'surname' => 'Pair']);
        $unpaid->players()->attach($unpaidPlayer);
        CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $unpaid->id, 'user_id' => $other->id]);
        File::forceCreate(['event_id' => $event->id, 'name' => 'Synthetic pair programme.pdf', 'path' => 'public/files/synthetic-pair.pdf']);
        $before = $this->rowCounts();
        foreach (['guest' => null, 'payer' => $payer, 'other' => $other] as $actor => $user) {
            auth()->forgetGuards();
            if ($user) $this->actingAs($user);
            $response = $this->get(route('events.show', $event))->assertOk()->assertSee('Synthetic child Pair')->assertSee('Synthetic parent Pair')
                ->assertDontSee('Private unpaid Pair')->assertSee('Synthetic pair programme.pdf')
                ->assertSee(route('result.show', $event), false);
            $this->assertSame(1, $response->viewData('entryCount'));
            $this->assertSame($actor === 'payer' ? 1 : 0, $response->viewData('userRegistrations')->count());
            $this->export($response, 'parent-child-paid-pair-'.$actor);
        }
        $this->assertSame(2, $registration->fresh()->players()->count());
        $this->assertSame($before, $this->rowCounts());
    }

    private function event(int $id, string $name, ?string $code, ?int $family): Event
    {
        if ($family !== null) {
            DB::table('eventtypes')->updateOrInsert(['id' => $id], ['name' => $name, 'code' => $code, 'type' => $family]);
        }

        return Event::factory()->create([
            'name' => 'Synthetic journey event '.$id, 'eventType' => $id, 'information' => 'Synthetic event information',
            'start_date' => '2026-11-15', 'end_date' => '2026-11-18', 'deadline' => 0,
            'withdrawal_deadline' => '2026-11-10 23:59:59', 'entryFee' => 450,
            'published' => true, 'signUp' => true,
        ]);
    }

    private function nomination(Event $event, CategoryEvent $category, string $name, string $surname): EventNomination
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => $surname, 'email' => 'private-nominee-contact@example.test']);

        return EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
    }

    private function assertNominationPrivacy(TestResponse $response): void
    {
        $response->assertSee('Published Nominee')->assertDontSee('Private Nominee')->assertDontSee('Foreign Nominee')
            ->assertDontSee('private-nominee-contact@example.test')->assertDontSee('private-invitation-recipient@example.test');
    }

    private function mastersInvitation(MastersInvitationBatch $batch, CategoryEvent $category, string $name, string $status, int $position): void
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Player']);
        MastersInvitation::create(['batch_id' => $batch->id, 'event_id' => $batch->event_id, 'category_event_id' => $category->id,
            'player_id' => $player->id, 'ranking_position' => $position, 'queue_position' => $position, 'status' => $status]);
    }

    private function rowCounts(): array
    {
        return collect(['events', 'eventtypes', 'category_events', 'event_nominations', 'registrations',
            'category_event_registrations', 'registration_orders', 'team_payment_orders', 'wallet_transactions',
            'user_players', 'players', 'event_regions', 'files', 'draws', 'fixtures', 'team_fixtures', 'teams', 'team_players', 'no_profile_team_players', 'clothing_orders', 'clothing_order_items',
            'player_registrations', 'registration_order_items', 'masters_invitations', 'masters_invitation_batches',
            'interprovincial_trial_invitations', 'interprovincial_trial_invitation_batches'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }

    private function export(TestResponse $response, string $name): void
    {
        $directory = storage_path('framework/testing/capester-event-journeys-20261025/snapshots');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/'.$name.'.html', $response->getContent());
    }
}
