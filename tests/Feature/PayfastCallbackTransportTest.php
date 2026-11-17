<?php

namespace Tests\Feature;

use App\Http\Middleware\PayfastCallbackTelemetry;
use App\Support\PayfastCallbackUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayfastCallbackTransportTest extends TestCase
{
    public function test_callback_urls_use_canonical_configuration_and_scoped_overrides(): void
    {
        config(['app.url' => 'https://canonical.example', 'services.payfast.notify_url' => 'https://callback.example/notify',
            'services.payfast.notify_team_url' => '/notify_team']);
        $this->app['request'] = Request::create('http://untrusted.example/checkout');
        $this->assertSame('https://callback.example/notify', PayfastCallbackUrl::for('notify'));
        foreach (['notify_team', 'notifyClothing', 'notify_order', 'notify_hoodie'] as $endpoint) {
            $this->assertSame('https://canonical.example/'.$endpoint, PayfastCallbackUrl::for($endpoint));
        }
        $payfast = new \App\Services\Payfast();
        $this->assertSame('https://callback.example/notify', $payfast->notify_url);
        $this->assertSame('https://canonical.example/notify_team', $payfast->notify_url_team);
    }

    public function test_unsafe_or_wrong_endpoint_configuration_fails_before_handoff(): void
    {
        config(['app.url' => 'https://canonical.example']);
        foreach (['https://callback.example/notify_team', 'https://user:secret@callback.example/notify',
            'https://callback.example/notify?token=secret', '//callback.example/notify'] as $url) {
            config(['services.payfast.notify_url' => $url]);
            try {
                PayfastCallbackUrl::for('notify');
                $this->fail('Unsafe callback URL accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->app->instance('env', 'production');
        config(['app.url' => 'http://canonical.example', 'services.payfast.notify_url' => '/notify']);
        $this->expectException(\InvalidArgumentException::class);
        PayfastCallbackUrl::for('notify');
    }

    public function test_transport_observes_only_exact_post_callbacks_without_sensitive_payload(): void
    {
        $middleware = $this->observer();
        $request = Request::create('/notify', 'POST', ['pf_payment_id' => '123', 'custom_int5' => ['bad'],
            'signature' => 'secret', 'email_address' => 'private@example.com', 'merchant_key' => 'key']);
        $response = $middleware->handle($request, fn () => response('rejected', 400));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertCount(2, $middleware->records);
        $context = $middleware->records[0][1];
        $this->assertSame('123', $context['unverified_provider_reference']);
        $this->assertNull($context['unverified_order_id']);
        $this->assertSame($context['correlation_id'], $middleware->records[1][1]['correlation_id']);
        $this->assertSame(400, $middleware->records[1][1]['http_status']);
        $this->assertSame(['correlation_id', 'endpoint', 'unverified_provider_reference', 'unverified_order_id'], array_keys($context));
        foreach ([['GET', '/notify'], ['POST', '/notify/extra'], ['POST', '/ordinary']] as [$method, $path]) {
            $middleware->handle(Request::create($path, $method), fn () => response('ok'));
        }
        $this->assertCount(2, $middleware->records);
        $this->assertSame(PayfastCallbackTelemetry::class, app(\App\Http\Kernel::class)->getGlobalMiddleware()[0]);
    }

    public function test_maintenance_or_session_audit_failure_is_observed_and_original_exception_rethrown(): void
    {
        foreach ([new HttpException(503), new \RuntimeException('private session or audit failure')] as $failure) {
            $middleware = $this->observer();
            try {
                $middleware->handle(Request::create('/notify_team', 'POST'), fn () => throw $failure);
                $this->fail('Exception swallowed.');
            } catch (\Throwable $caught) {
                $this->assertSame($failure, $caught);
            }
            $this->assertCount(2, $middleware->records);
            $this->assertSame($failure instanceof HttpException ? 503 : 500, $middleware->records[1][1]['inferred_http_status']);
            $this->assertArrayNotHasKey('http_status', $middleware->records[1][1]);
            $this->assertSame($failure::class, $middleware->records[1][1]['exception_class']);
            $this->assertStringNotContainsString('private', json_encode($middleware->records));
        }
    }

    public function test_file_logging_failure_preserves_both_response_and_original_exception(): void
    {
        Log::shouldReceive('build')->with(\Mockery::on(fn ($configuration) =>
            $configuration === ['driver' => 'daily', 'days' => 14, 'path' => storage_path('logs/payfast-callbacks.log')]
        ))->andThrow(new \RuntimeException('file unavailable'));
        $middleware = new PayfastCallbackTelemetry();
        $this->assertSame(503, $middleware->handle(Request::create('/notify', 'POST'), fn () => response('', 503))->getStatusCode());
        $failure = new \RuntimeException('financial failure');
        try {
            $middleware->handle(Request::create('/notify', 'POST'), fn () => throw $failure);
            $this->fail('Exception swallowed.');
        } catch (\Throwable $caught) {
            $this->assertSame($failure, $caught);
        }
    }

    public function test_telemetry_does_not_decode_json_before_request_size_validation(): void
    {
        $request = new class extends Request {
            public function json($key = null, $default = null) { throw new \RuntimeException('JSON decoded before size validation'); }
        };
        $request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/notify',
            'CONTENT_TYPE' => 'application/json'], '{"pf_payment_id":"123","custom_int5":"456"}');
        $middleware = $this->observer();
        $middleware->handle($request, fn () => response('', 413));
        $this->assertCount(2, $middleware->records);
        $this->assertNull($middleware->records[0][1]['unverified_provider_reference']);
        $this->assertNull($middleware->records[0][1]['unverified_order_id']);
        $this->assertSame(413, $middleware->records[1][1]['http_status']);
    }

    private function observer(): PayfastCallbackTelemetry
    {
        return new class extends PayfastCallbackTelemetry {
            public array $records = [];
            protected function write(string $message, array $context): void { $this->records[] = [$message, $context]; }
        };
    }
}
