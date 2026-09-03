<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base schema migration for SQLite in-memory test databases.
 *
 * The production schema lives in MySQL and was never fully managed through
 * migrations. This migration recreates the minimum table structure required
 * for the test suite to run. Tables are kept intentionally minimal — only
 * the columns referenced by application code under test are included.
 *
 * Tables that exist here but are later altered by other migration files will
 * receive those columns through the normal migration pipeline (e.g. the
 * add_preset_key_to_draw_settings migration runs after this one).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Auth / users
        // ------------------------------------------------------------------

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->string('profile_photo_path')->nullable();
                $table->unsignedBigInteger('current_team_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Note: personal_access_tokens is auto-created by laravel/sanctum.

        // ------------------------------------------------------------------
        // Spatie permission tables
        // ------------------------------------------------------------------

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id', 'role_id']);
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }

        // ------------------------------------------------------------------
        // Spatie activity log
        // ------------------------------------------------------------------

        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->nullableMorphs('subject');
                $table->nullableMorphs('causer');
                $table->json('properties')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->string('event')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Stub tables for legacy ALTER TABLE migrations
        // (only columns that the ALTER migrations reference are added here;
        // the rest are added by the individual migrations)
        // ------------------------------------------------------------------

        if (! Schema::hasTable('draw_settings')) {
            Schema::create('draw_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fixtures')) {
            Schema::create('fixtures', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->boolean('scheduled')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_fixture_players')) {
            Schema::create('team_fixture_players', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('fixture_id')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Teams (Jetstream personal team — referenced by UserFactory)
        // ------------------------------------------------------------------

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name');
                $table->boolean('personal_team');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_user')) {
            Schema::create('team_user', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('team_id')->index();
                $table->string('role')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_invitations')) {
            Schema::create('team_invitations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id')->index();
                $table->string('email');
                $table->string('role')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Core application data tables
        // ------------------------------------------------------------------

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->text('information')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('email')->nullable();
                $table->string('organizer')->nullable();
                $table->decimal('entryFee', 10, 2)->default(0);
                $table->integer('deadline')->default(0);
                $table->timestamp('withdrawal_deadline')->nullable();
                $table->string('eventType')->nullable();
                $table->string('status')->default('active');
                $table->text('venue_notes')->nullable();
                $table->string('logo')->nullable();
                $table->boolean('published')->default(false);
                $table->boolean('signUp')->default(false);
                $table->unsignedBigInteger('series_id')->nullable();
                $table->decimal('cape_tennis_fee', 10, 2)->default(0);
                // budget_cap, target_entries, target_income added by
                // 2026_04_24_000004_add_budget_fields_to_events
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->decimal('Fee', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('category_events')) {
            Schema::create('category_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->decimal('entry_fee', 10, 2)->default(0);
                $table->integer('ordering')->default(0);
                $table->boolean('nominations_published')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('players')) {
            Schema::create('players', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('surname');
                $table->string('email')->nullable();
                $table->date('dateOfBirth')->nullable();
                $table->tinyInteger('gender')->nullable();
                $table->string('cellNr')->nullable();
                $table->unsignedBigInteger('userId')->nullable();
                $table->string('coach')->nullable();
                $table->timestamp('profile_updated_at')->nullable();
                $table->boolean('profile_complete')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('registrations')) {
            Schema::create('registrations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('player_registrations')) {
            Schema::create('player_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('registration_id');
                $table->unsignedBigInteger('player_id');
                $table->timestamps();
                $table->primary(['registration_id', 'player_id']);
            });
        }

        if (! Schema::hasTable('category_event_registrations')) {
            Schema::create('category_event_registrations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('category_event_id');
                $table->unsignedBigInteger('registration_id');
                $table->unsignedBigInteger('user_id')->nullable();

                // Payment
                $table->string('pf_transaction_id')->nullable();
                $table->unsignedTinyInteger('payment_status_id')->nullable();

                // Withdrawal / status
                $table->string('status')->default('active');
                $table->timestamp('withdrawn_at')->nullable();

                // Refund core
                $table->string('refund_method')->nullable();
                $table->string('refund_status')->default('not_refunded');
                $table->decimal('refund_gross', 10, 2)->default(0);
                $table->decimal('refund_fee', 10, 2)->default(0);
                $table->decimal('refund_net', 10, 2)->default(0);
                $table->timestamp('refunded_at')->nullable();

                // Bank refund details
                $table->string('refund_account_name')->nullable();
                $table->string('refund_bank_name')->nullable();
                $table->string('refund_account_number')->nullable();
                $table->string('refund_branch_code')->nullable();
                $table->string('refund_account_type')->nullable();

                // deleted_at (softDeletes) is added by
                // 2026_04_30_210002_add_soft_deletes_to_category_event_registrations
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Wallet / ledger
        // ------------------------------------------------------------------

        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('payable_type')->nullable();
                $table->unsignedBigInteger('payable_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('wallet_id')->index();
                $table->string('type');           // credit | debit
                $table->decimal('amount', 12, 2);
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Payment orders
        // ------------------------------------------------------------------

        if (! Schema::hasTable('registration_orders')) {
            Schema::create('registration_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('wallet_reserved', 12, 2)->default(0);
                $table->boolean('wallet_debited')->default(false);
                $table->boolean('payfast_paid')->default(false);
                $table->string('payfast_pf_payment_id')->nullable();
                $table->decimal('payfast_amount_due', 12, 2)->default(0);
                $table->boolean('pay_status')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('registration_order_items')) {
            Schema::create('registration_order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->decimal('item_price', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_payment_orders')) {
            Schema::create('team_payment_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->unsignedBigInteger('event_id')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('wallet_reserved', 12, 2)->default(0);
                $table->decimal('payfast_amount_due', 12, 2)->default(0);
                $table->boolean('wallet_debited')->default(false);
                $table->boolean('payfast_paid')->default(false);
                $table->boolean('pay_status')->default(false);
                $table->string('payfast_pf_payment_id')->nullable();
                $table->json('payfast_raw_data')->nullable();
                // Refund fields
                $table->string('refund_method')->nullable();
                $table->string('refund_status')->default('not_refunded');
                $table->decimal('refund_gross', 10, 2)->default(0);
                $table->decimal('refund_fee', 10, 2)->default(0);
                $table->decimal('refund_net', 10, 2)->default(0);
                $table->timestamp('refunded_at')->nullable();
                $table->string('refund_account_name')->nullable();
                $table->string('refund_bank_name')->nullable();
                $table->string('refund_account_number')->nullable();
                $table->string('refund_branch_code')->nullable();
                $table->string('refund_account_type')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // PayFast transaction log
        // ------------------------------------------------------------------

        if (! Schema::hasTable('transactions_pf')) {
            Schema::create('transactions_pf', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pf_payment_id')->nullable();
                $table->string('transaction_type')->nullable();
                $table->decimal('amount_gross', 10, 2)->nullable();
                $table->decimal('amount_fee', 10, 2)->nullable();
                $table->decimal('amount_net', 10, 2)->nullable();
                $table->unsignedBigInteger('event_id')->nullable();
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->string('item_name')->nullable();
                $table->string('email_address')->nullable();
                $table->integer('custom_int1')->nullable();
                $table->integer('custom_int2')->nullable();
                $table->integer('custom_int3')->nullable();
                $table->integer('custom_int4')->nullable();
                $table->integer('custom_int5')->nullable();
                $table->string('custom_str1')->nullable();
                $table->string('custom_str2')->nullable();
                $table->string('custom_str3')->nullable();
                $table->string('custom_str4')->nullable();
                $table->string('custom_str5')->nullable();
                $table->decimal('cape_tennis_fee', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // Misc tables referenced by models / middleware
        // ------------------------------------------------------------------

        if (! Schema::hasTable('authentication_log')) {
            Schema::create('authentication_log', function (Blueprint $table) {
                $table->id();
                $table->string('authenticatable_type');
                $table->unsignedBigInteger('authenticatable_id');
                $table->index(['authenticatable_type', 'authenticatable_id']);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('login_at')->nullable();
                $table->boolean('login_successful')->default(false);
                $table->timestamp('logout_at')->nullable();
                $table->boolean('cleared_by_user')->default(false);
                $table->json('location')->nullable();
            });
        }

        if (! Schema::hasTable('event_admins')) {
            Schema::create('event_admins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_players')) {
            Schema::create('user_players', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('player_id')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clothing_orders')) {
            Schema::create('clothing_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->integer('pay_status')->default(0);
                $table->string('pf_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->decimal('amount_paid', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_players')) {
            Schema::create('team_players', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->integer('pay_status')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_type_models')) {
            Schema::create('event_type_models', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->integer('pay_status')->default(0);
                $table->string('pf_payment_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->timestamps();
            });
        }

        // Note: site_settings has its own migration at 2026_03_08_000000.
    }

    public function down(): void
    {
        // Tear down in reverse dependency order (foreign keys disabled in SQLite tests).
        foreach ([
            'team_payment_orders',
            'registration_order_items',
            'registration_orders',
            'wallet_transactions',
            'wallets',
            'category_event_registrations',
            'player_registrations',
            'category_events',
            'registrations',
            'players',
            'events',
            'categories',
            'transactions_pf',
            'orders',
            'order_items',
            'role_has_permissions',
            'model_has_permissions',
            'model_has_roles',
            'permissions',
            'roles',
            'activity_log',
            'team_invitations',
            'team_user',
            'teams',
            'personal_access_tokens',
            'password_resets',
            'users',
            'site_settings',
            'draw_settings',
            'fixtures',
            'team_fixture_players',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
