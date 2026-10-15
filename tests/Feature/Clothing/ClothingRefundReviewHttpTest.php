<?php

namespace Tests\Feature\Clothing;

use App\Models\{CategoryEvent, ClothingItemType, ClothingOrder, ClothingSize, Event, Player, Team, TeamRegion, User};
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\{Crypt, DB, Http};
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ClothingRefundReviewHttpTest extends TestCase
{
    public function test_real_http_review_survives_a_stale_file_session_write_without_moving_money(): void
    {
        $root = storage_path('framework/testing/clothing-review-http-'.str()->uuid());
        mkdir($root, 0777, true);
        mkdir($root.'/sessions');
        mkdir($root.'/views');
        $database = $root.'/review.sqlite';
        touch($database);
        $before = config('database.connections.sqlite');
        $connection = config('database.default');
        $server = null;
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            $admin = User::factory()->create()->assignRole(Role::findOrCreate('super-user', 'web'));
            $payer = User::factory()->create();
            $event = Event::factory()->create();
            $region = TeamRegion::create(['region_name' => 'QA refund session region']);
            DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
            $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
            $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
            $catalogue = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'QA clothing', 'price' => 395]);
            $size = ClothingSize::create(['item_type' => $catalogue->id, 'size' => 'QA size']);
            $order = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => Player::factory()->create()->id,
                'user_id' => $payer->id, 'pay_status' => 1, 'status' => 'completed', 'subtotal' => 1010, 'total' => 1010, 'amount_paid' => 1010,
                'payfast_paid' => true, 'payfast_pf_payment_id' => 'QA-SESSION-PAYMENT', 'payfast_amount_due' => 1010, 'paid_at' => now(), 'request_token' => (string) str()->uuid()]);
            foreach (['QA Shirt' => 395, 'QA Skirt' => 370, 'QA Peak' => 245] as $name => $paid) {
                $order->items()->create(['clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id, 'qty' => 1,
                    'price' => $paid, 'line_total' => $paid, 'item_name' => $name, 'size_name' => 'QA size']);
            }
            $shirt = $order->items()->firstOrFail();
            $id = str()->random(40);
            $sessionName = 'ct_refund_http_test';
            $handler = new FileSessionHandler(new Filesystem, $root.'/sessions', 120);
            $session = new Store($sessionName, $handler, $id);
            $session->start();
            $session->put(auth()->guard('web')->getName(), $admin->id);
            $csrf = str()->random(40);
            $session->put('_token', $csrf);
            $session->save();
            // An audit request reads the session before the review is submitted.
            $stale = new Store($sessionName, $handler, $id);
            $stale->start();
            $cookie = Crypt::encrypt(CookieValuePrefix::create($sessionName, app('encrypter')->getKey()).$id, false);
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $url = 'http://'.$address;
            $server = new Process([PHP_BINARY, '-S', $address, base_path('tests/Fixtures/clothing-review-http-router.php')], base_path(), [
                'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => $url, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => $sessionName, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '',
                'CT_REVIEW_SESSION_DIR' => $root.'/sessions', 'VIEW_COMPILED_PATH' => $root.'/views', 'CACHE_STORE' => 'array',
                'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null',
            ]);
            $server->start();
            $ready = false;
            for ($attempt = 0; $attempt < 40; $attempt++) {
                try { $ready = Http::timeout(2)->get($url.'/login')->status() === 200; }
                catch (\Illuminate\Http\Client\ConnectionException) { usleep(100000); }
                if ($ready) break;
            }
            $this->assertTrue($ready, 'Isolated HTTP test server did not start. '.substr($server->getErrorOutput(), -2000));
            $client = fn () => Http::withoutRedirecting()->withCookies([$sessionName => $cookie], '127.0.0.1')->asForm();
            $path = '/backend/event/'.$event->id.'/clothing/orders/'.$order->id.'/refunds';
            $input = ['_token' => $csrf, 'request_token' => (string) str()->uuid(), 'method' => 'wallet', 'mode' => 'quantity', 'items' => [$shirt->id => 1], 'reason' => 'QA shirt return'];
            $review = $client()->post($url.$path.'/review', $input);
            $this->assertSame(200, $review->status());
            $this->assertStringContainsString('Confirm refund of R395.00', $review->body());
            $this->assertStringContainsString('QA Skirt', $review->body());
            $this->assertStringContainsString('Not selected', $review->body());
            preg_match('/name="review_token" value="([^"]+)"/', $review->body(), $matches);
            $this->assertNotEmpty($matches[1] ?? null);
            $token = html_entity_decode($matches[1], ENT_QUOTES);
            $current = new Store($sessionName, $handler, $id);
            $current->start();
            $this->assertFalse($current->has('clothing_refund_review'));
            // Demonstrate the old dependency fails when the late audit saves its old payload.
            $current->put('clothing_refund_review.'.$event->id.'.'.$order->id, $token);
            $current->save();
            $stale->put('audit_previous_request_id', 'QA stale audit');
            $stale->save();
            $overwritten = new Store($sessionName, $handler, $id);
            $overwritten->start();
            $this->assertFalse($overwritten->has('clothing_refund_review'));
            $repeat = $client()->post($url.$path.'/review', $input);
            $this->assertSame(200, $repeat->status());
            $this->assertStringContainsString('Confirm refund of R395.00', $repeat->body());
            $this->assertDatabaseCount('clothing_refunds', 0);
            $this->assertDatabaseCount('wallet_transactions', 0);
            $this->assertDatabaseCount('clothing_provider_refund_attempts', 0);
            $resolved = app(\App\Services\Clothing\ClothingRefundReviewService::class)->resolve($token, $event, $order, $admin);
            $this->assertSame([$shirt->id => '1'], $resolved['items']);
            $legacy = $client()->get($url.$path.'/review');
            $this->assertSame(302, $legacy->status());
            $this->assertSame($url.$path, $legacy->header('Location'));
            if (getenv('CT_REFUND_FLOW_QA') === '1') {
                file_put_contents(storage_path('app/qa/clothing-refund-flow/session-review.html'), preg_replace('/name="review_token" value="[^"]+"/', 'name="review_token" value=""', $review->body()));
            }
        } finally {
            $server?->stop();
            DB::disconnect('sqlite');
            config(['database.default' => $connection, 'database.connections.sqlite' => $before]);
            DB::purge('sqlite');
            // Test-owned unique paths only; no application sessions or databases.
            (new Filesystem)->deleteDirectory($root);
        }
    }
}
