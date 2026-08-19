<?php

namespace App\Providers;

use Bavix\Wallet\WalletConfigure;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Order;
use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Observers\FinancialMutationObserver;
use App\Observers\RegistrationObserver;
use App\Observers\TransactionObserver;
use App\Observers\WalletObserver;
use App\Observers\WalletTransactionObserver;
use App\Support\Audit\AuditContext;
use App\Support\Audit\AuditModelSubscriber;
use App\Support\Audit\AuditRedactor;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\DB;
use App\Support\Audit\AuditQueryListener;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobExceptionOccurred;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register()
  {
    // Cape Tennis owns its wallet schema and ledger migrations. Registering
    // Bavix's bundled migrations would recreate/alter incompatible tables.
    WalletConfigure::ignoreMigrations();

    // ✅ Override Fortify login redirect behaviour
    $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);

    // ---------------------------------------------------------------
    // Draw domain — canonical services
    // ---------------------------------------------------------------
    $this->app->singleton(\App\Domain\Draws\Services\StandingsService::class);
    $this->app->singleton(\App\Domain\Draws\Services\RoundRobinGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\PlayoffGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\ByeAdvancementService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawLockService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawPublicationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\BracketRenderService::class);
    $this->app->singleton(\App\Domain\Draws\Services\FeedInGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawGenerationService::class);
    $this->app->singleton(\App\Domain\Fixtures\Services\FixtureProgressionService::class);

    // EngineRouter — singleton so mismatch/fallback counters accumulate per request
    $this->app->singleton(\App\Domain\Engine\EngineRouter::class);
    $this->app->singleton(AuditContext::class);
    $this->app->singleton(AuditRedactor::class);
    $this->app->singleton(AuditWriter::class);
    $this->app->singleton(AuditModelSubscriber::class);
    $this->app->singleton(AuditQueryListener::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot()
  {
    DB::listen(fn ($query) => app(AuditQueryListener::class)->handle($query));

    EventFacade::listen(CommandStarting::class, function (CommandStarting $event): void {
      app(AuditWriter::class)->record([
        'category' => 'system',
        'action' => 'command.started',
        'outcome' => 'attempted',
        'source' => 'console',
        'subject_type' => 'command',
        'subject_id' => $event->command,
      ]);
    });
    EventFacade::listen(CommandFinished::class, function (CommandFinished $event): void {
      app(AuditWriter::class)->record([
        'category' => 'system',
        'action' => 'command.finished',
        'outcome' => $event->exitCode === 0 ? 'succeeded' : 'failed',
        'source' => 'console',
        'subject_type' => 'command',
        'subject_id' => $event->command,
        'metadata' => ['exit_code' => $event->exitCode],
      ]);
    });
    EventFacade::listen(JobProcessing::class, function (JobProcessing $event): void {
      app(AuditWriter::class)->record([
        'category' => 'system',
        'action' => 'queue-job.started',
        'outcome' => 'attempted',
        'source' => 'queue',
        'subject_type' => 'queue-job',
        'subject_id' => $event->job->getJobId(),
        'subject_label' => $event->job->resolveName(),
        'metadata' => ['connection' => $event->connectionName, 'queue' => $event->job->getQueue()],
      ], true);
    });
    EventFacade::listen(JobProcessed::class, function (JobProcessed $event): void {
      app(AuditWriter::class)->record([
        'category' => 'system',
        'action' => 'queue-job.finished',
        'source' => 'queue',
        'subject_type' => 'queue-job',
        'subject_id' => $event->job->getJobId(),
        'subject_label' => $event->job->resolveName(),
      ]);
    });
    EventFacade::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
      app(AuditWriter::class)->record([
        'category' => 'system',
        'action' => 'queue-job.failed',
        'outcome' => 'failed',
        'source' => 'queue',
        'subject_type' => 'queue-job',
        'subject_id' => $event->job->getJobId(),
        'subject_label' => $event->job->resolveName(),
        'metadata' => ['exception' => $event->exception::class],
      ]);
    });

    RateLimiter::for('outbound-mail', function () {
      return Limit::perSecond((int) config('mail.bulk_mail.rate_per_second', 14));
    });

    // Share asset version globally for cache busting
    View::share('assetVersion', config('app.asset_version', '1.0.0'));

    CategoryEventRegistration::observe(RegistrationObserver::class);
    Wallet::observe(WalletObserver::class);
    WalletTransaction::observe(WalletTransactionObserver::class);
    Transaction::observe(TransactionObserver::class);
    RegistrationOrder::observe(FinancialMutationObserver::class);
    TeamPaymentOrder::observe(FinancialMutationObserver::class);
    CategoryEventRegistration::observe(FinancialMutationObserver::class);
    ClothingOrder::observe(FinancialMutationObserver::class);
    Order::observe(FinancialMutationObserver::class);
    TeamPlayer::observe(FinancialMutationObserver::class);

    foreach (['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'restoring', 'restored'] as $modelEvent) {
      EventFacade::listen("eloquent.{$modelEvent}: *", function (string $eventName, array $models) use ($modelEvent): void {
        $model = $models[0] ?? null;
        if ($model instanceof Model) {
          app(AuditModelSubscriber::class)->{$modelEvent}($model);
        }
      });
    }
    // ✅ Global admin badge: pending bank refunds (registrations + team refunds)
    View::composer('*', function ($view) {

      if (auth()->check() && auth()->user()->hasAnyRole(['super-user', 'admin'])) {

        $registrationPending = CategoryEventRegistration::where('status', 'withdrawn')
          ->where('refund_method', 'bank')
          ->where('refund_status', 'pending')
          ->count();

        $teamPending = TeamPaymentOrder::where('refund_method', 'bank')
          ->where('refund_status', 'pending')
          ->count();

        $pendingBankRefundCount = $registrationPending + $teamPending;

        $view->with('pendingBankRefundCount', $pendingBankRefundCount);
      }

    });
  }
}
