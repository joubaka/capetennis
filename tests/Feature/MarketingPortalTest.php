<?php

namespace Tests\Feature;

use App\Models\{MarketingCampaign, MarketingContact, User};
use App\Services\Marketing\PanarottisCalculator;
use App\Services\Marketing\HostCampaignTemplate;
use Illuminate\Support\Facades\{Bus, DB, File, Mail, Notification, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // This class never refreshes shared ct_testing or local application data.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        foreach (['2013_01_01_000000_create_base_schema_for_testing.php', '2026_11_19_150000_create_marketing_portal_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('category_event_registrations', fn ($table) => $table->softDeletes());
        Schema::create('event_mail_issues', function ($table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->timestamp('read_at')->nullable();
        });
        Schema::create('site_settings', function ($table) {
            $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps();
        });
        Mail::fake(); Bus::fake(); Notification::fake();
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function contact(): array
    {
        return ['academy' => 'Fictional Academy', 'name' => 'Test Coach', 'email' => 'COACH@example.test',
            'phone' => '+27 82 000 0000', 'town' => 'Test Town', 'courts' => 8, 'status' => 'interested', 'preference' => 'email', 'notes' => '<script>alert(1)</script>'];
    }

    public function test_every_portal_route_requires_super_user(): void
    {
        $this->get('/superadmin/marketing')->assertRedirect();
        $contact = MarketingContact::create($this->contact());
        $campaign = MarketingCampaign::create(['title' => 'Private draft', 'email_body' => 'Private', 'whatsapp_body' => 'Private']);
        Role::findOrCreate('admin', 'web');
        foreach ([User::factory()->create(), User::factory()->create()->assignRole('admin')] as $user) {
            $this->actingAs($user);
            foreach (['', '/contacts', '/contacts/create', "/contacts/{$contact->id}/edit", '/campaigns', '/campaigns/create', "/campaigns/{$campaign->id}/edit", '/calculator'] as $path) {
                $this->get('/superadmin/marketing'.$path)->assertForbidden();
            }
            foreach (['contacts', 'campaigns', 'calculator'] as $path) $this->post('/superadmin/marketing/'.$path, [])->assertForbidden();
            foreach (['contacts' => $contact, 'campaigns' => $campaign] as $path => $record) {
                $this->put('/superadmin/marketing/'.$path.'/'.$record->id, [])->assertForbidden();
                $this->delete('/superadmin/marketing/'.$path.'/'.$record->id, [])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('marketing_contacts', 1);
        $this->assertDatabaseCount('marketing_campaigns', 1);
    }

    public function test_contact_crud_validation_search_pagination_and_stale_edit_protection(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('superadmin.marketing.contacts.store'), $this->contact() + ['id' => 999, 'version' => 99])->assertRedirect();
        $contact = MarketingContact::sole();
        $this->assertSame('coach@example.test', $contact->email);
        $this->assertSame(1, $contact->version);
        $this->assertNotSame(999, $contact->id);
        $this->post(route('superadmin.marketing.contacts.store'), array_replace($this->contact(), ['courts' => -1, 'email' => 'bad']))->assertSessionHasErrors(['courts', 'email']);
        $this->put(route('superadmin.marketing.contacts.update', $contact), array_replace($this->contact(), ['version' => 0]))->assertStatus(409);
        $this->put(route('superadmin.marketing.contacts.update', $contact), array_replace($this->contact(), ['version' => 1, 'status' => 'approved']))->assertRedirect();
        $this->get(route('superadmin.marketing.contacts.edit', $contact))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        for ($i = 0; $i < 21; $i++) MarketingContact::create(array_replace($this->contact(), ['academy' => 'Other '.$i]));
        $this->get(route('superadmin.marketing.contacts.index'))->assertOk()->assertViewHas('contacts', fn ($rows) => $rows->count() === 20 && $rows->total() === 22);
        $this->get(route('superadmin.marketing.contacts.index', ['search' => 'Fictional']))->assertOk()->assertViewHas('contacts', fn ($rows) => $rows->total() === 1);
        $this->delete(route('superadmin.marketing.contacts.destroy', $contact), ['version' => 1])->assertStatus(409);
        $this->delete(route('superadmin.marketing.contacts.destroy', $contact), ['version' => 2])->assertRedirect();
        $this->assertDatabaseMissing('marketing_contacts', ['id' => $contact->id]);
    }

    public function test_drafts_are_persistent_inert_escaped_and_never_sent(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('superadmin.marketing.campaigns.create'))->assertOk()->assertSee('R8,690')->assertSee('Dates and qualification rules are not final');
        $this->assertDatabaseCount('marketing_campaigns', 0);
        $data = ['title' => 'Draft one', 'subject' => 'Invitation', 'email_body' => '<script>alert(1)</script>', 'whatsapp_body' => str_repeat('🎾', 30000), 'status' => 'sent'];
        $this->post(route('superadmin.marketing.campaigns.store'), $data)->assertRedirect();
        $campaign = MarketingCampaign::sole();
        $this->assertSame($data['whatsapp_body'], $campaign->whatsapp_body);
        $response = $this->get(route('superadmin.marketing.campaigns.edit', $campaign))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->put(route('superadmin.marketing.campaigns.update', $campaign), $data + ['version' => 0])->assertStatus(409);
        $this->put(route('superadmin.marketing.campaigns.update', $campaign), array_replace($data, ['title' => 'Updated', 'version' => 1]))->assertRedirect();
        $this->get(route('superadmin.marketing.campaigns.index'))->assertOk()->assertSee('Updated')->assertSee('Draft');
        $this->delete(route('superadmin.marketing.campaigns.destroy', $campaign), ['version' => 2])->assertRedirect();
        $this->assertDatabaseCount('marketing_campaigns', 0);
        Mail::assertNothingSent(); Mail::assertNothingQueued(); Bus::assertNothingDispatched(); Notification::assertNothingSent();
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'superadmin.marketing.')) $this->assertStringNotContainsString('send', $route->uri());
        }
    }

    public function test_preview_workspace_uses_separate_channel_templates_without_rewriting_saved_copy(): void
    {
        $this->actingAs($this->admin());
        $response = $this->get(route('superadmin.marketing.campaigns.create'))->assertOk()
            ->assertSee('Live preview')->assertSee('marketing-campaign-preview.js')
            ->assertSee('marketing-campaign-preview.css')->assertSee('not an image attachment');
        $campaign = $response->viewData('campaign');
        $this->assertSame(HostCampaignTemplate::body(), $campaign->email_body);
        $this->assertSame(HostCampaignTemplate::whatsappBody(), $campaign->whatsapp_body);
        $this->assertLessThan(mb_strlen($campaign->email_body), mb_strlen($campaign->whatsapp_body));
        $this->assertLessThan(1600, mb_strlen($campaign->whatsapp_body));
        $this->assertStringContainsString('| Category | Entries | Entry fee | Income |', $campaign->email_body);
        $this->assertStringContainsString('R8,690', $campaign->whatsapp_body);
        $saved = MarketingCampaign::create(['title' => 'Original', 'email_body' => '<img src=x onerror=alert(1)>', 'whatsapp_body' => 'My own short caption']);
        $this->get(route('superadmin.marketing.campaigns.edit', $saved))->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertSame('My own short caption', $saved->fresh()->whatsapp_body);
        $this->assertSame(1, MarketingCampaign::count());
        Mail::assertNothingSent(); Bus::assertNothingDispatched();
    }

    public function test_calculator_defaults_partial_attendance_rounding_and_validation(): void
    {
        $calculator = app(PanarottisCalculator::class);
        $result = $calculator->calculate($calculator->defaults());
        $this->assertSame(['entries' => 52, 'gross' => 1260000, 'processing' => 63000, 'platform' => 78000, 'retained' => 1119000,
            'convenor' => 50000, 'rental' => 200000, 'other_costs' => 0, 'remaining' => 869000], $result['totals']);
        $input = array_replace($calculator->defaults(), ['entries' => [1, 0, 0, 0], 'fees' => ['200.10', '250', '250', '300']]);
        $result = $calculator->calculate($input);
        $this->assertSame(1001, $result['totals']['processing']);
        $this->assertLessThan(0, $result['totals']['remaining']);
        $this->actingAs($this->admin());
        $this->get(route('superadmin.marketing.calculator'))->assertOk()->assertSee('R8,690.00');
        $this->post(route('superadmin.marketing.calculate'), $input + ['remaining' => 999999])->assertOk()->assertViewHas('result', fn ($r) => $r['totals']['entries'] === 1);
        foreach ([['entries' => [17, 16, 12, 8]], ['fees' => ['1.001', 250, 250, 300]], ['courts' => 101], ['hours' => 25], ['processing_percent' => 101], ['limits' => [16]], ['other_costs' => -1]] as $invalid) {
            $this->post(route('superadmin.marketing.calculate'), array_replace($calculator->defaults(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('marketing_contacts', 0);
        $this->assertDatabaseCount('marketing_campaigns', 0);
    }

    public function test_render_fictional_browser_fixtures(): void
    {
        $this->actingAs($this->admin());
        MarketingContact::create($this->contact());
        foreach (['contacts' => 'contacts.index', 'contact-form' => 'contacts.create', 'campaign-form' => 'campaigns.create', 'campaigns' => 'campaigns.index', 'calculator' => 'calculator'] as $name => $route) {
            $response = $this->get(route('superadmin.marketing.'.$route))->assertOk();
            if (getenv('CT_MARKETING_QA_HTML') === '1') {
                File::ensureDirectoryExists(storage_path('framework/testing'));
                file_put_contents(storage_path('framework/testing/marketing-'.$name.'.html'), $response->getContent());
            }
        }
    }
}
