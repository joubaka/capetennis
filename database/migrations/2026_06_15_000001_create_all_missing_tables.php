<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcement_events')) {
            Schema::create('announcement_events', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('announcement_id');
                $table->integer('event_id');
            });
        }

        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('message');
                $table->integer('event_id')->nullable();
                $table->string('title');
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('brackets')) {
            Schema::create('brackets', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191);
            });
        }

        if (!Schema::hasTable('category_results')) {
            Schema::create('category_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->unsignedBigInteger('registration_id');
                $table->unsignedInteger('position');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cavalier_clothings')) {
            Schema::create('cavalier_clothings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191);
                $table->string('shirt_id', 191)->nullable();
                $table->string('hot_pants_id', 191)->nullable();
                $table->string('long_sleeve_id', 11)->nullable();
                $table->string('skirt_id', 11)->nullable();
                $table->string('short_id', 11)->nullable();
                $table->string('peak_id', 11)->nullable();
                $table->string('cap_id', 11)->nullable();
                $table->integer('pay_status')->nullable();
                $table->string('team', 100)->nullable();
                $table->integer('pf_id')->nullable();
            });
        }

        if (!Schema::hasTable('clothing_item_types')) {
            Schema::create('clothing_item_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('item_type_name', 191);
                $table->integer('price')->nullable();
                $table->integer('region_id')->nullable();
                $table->integer('ordering')->nullable();
            });
        }

        if (!Schema::hasTable('clothing_order_items')) {
            Schema::create('clothing_order_items', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('clothing_order_id');
                $table->integer('clothing_order_item_id');
                $table->integer('clothing_item_size');
                $table->integer('qty')->default(1);
                $table->decimal('price', 10, 2)->default(0.00);
                $table->decimal('line_total', 10, 2)->default(0.00);
            });
        }

        if (!Schema::hasTable('clothing_sizes')) {
            Schema::create('clothing_sizes', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('size', 191);
                $table->integer('item_type')->nullable();
                $table->integer('ordering')->nullable();
            });
        }

        if (!Schema::hasTable('covids')) {
            Schema::create('covids', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('player_name', 191);
            });
        }

        if (!Schema::hasTable('draw_events')) {
            Schema::create('draw_events', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('draw_id');
            });
        }

        if (!Schema::hasTable('draw_formats')) {
            Schema::create('draw_formats', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191);
            });
        }

        if (!Schema::hasTable('draw_group_rankings')) {
            Schema::create('draw_group_rankings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('draw_group_id');
                $table->integer('registration_id');
                $table->integer('matches_won')->nullable();
                $table->integer('matches_lost')->nullable();
                $table->integer('games_won')->nullable();
                $table->integer('games_lost')->nullable();
                $table->integer('sets_won')->nullable();
                $table->integer('sets_lost')->nullable();
                $table->integer('rank')->nullable();
            });
        }

        if (!Schema::hasTable('draw_teams')) {
            Schema::create('draw_teams', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('draw_id');
                $table->integer('team_id');
                $table->integer('team_2')->nullable();
            });
        }

        if (!Schema::hasTable('draw_types')) {
            Schema::create('draw_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('drawTypeName');
                $table->string('btn_color', 100);
                $table->string('type', 30)->nullable();
            });
        }

        if (!Schema::hasTable('event_draw_types')) {
            Schema::create('event_draw_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('draw_type_id');
            });
        }

        if (!Schema::hasTable('event_nominations')) {
            Schema::create('event_nominations', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('player_id');
                $table->integer('category_event_id');
                $table->integer('rank')->nullable();
            });
        }

        if (!Schema::hasTable('event_pdf_draws')) {
            Schema::create('event_pdf_draws', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('pdf_draw_id');
            });
        }

        if (!Schema::hasTable('event_regions')) {
            Schema::create('event_regions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('region_id');
                $table->integer('ordering')->nullable();
            });
        }

        if (!Schema::hasTable('event_teams')) {
            Schema::create('event_teams', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('team_id');
                $table->integer('published')->nullable();
            });
        }

        if (!Schema::hasTable('event_venues')) {
            Schema::create('event_venues', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->integer('venue_id');
                $table->integer('num_courts');
            });
        }

        if (!Schema::hasTable('eventtypes')) {
            Schema::create('eventtypes', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191)->nullable();
                $table->integer('type');
            });
        }

        if (!Schema::hasTable('exercise_names')) {
            Schema::create('exercise_names', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
                $table->integer('exersize_type_id');
                $table->integer('max_score');
                $table->integer('duration')->nullable();
            });
        }

        if (!Schema::hasTable('exercise_types')) {
            Schema::create('exercise_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
            });
        }

        if (!Schema::hasTable('exercises')) {
            Schema::create('exercises', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('score');
                $table->integer('exersize_name_id');
                $table->integer('player_id');
                $table->integer('user_id');
                $table->date('date_of_lesson')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 191)->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_id');
                $table->string('name');
                $table->integer('size')->nullable();
                $table->string('path', 200)->nullable();
            });
        }

        if (!Schema::hasTable('fixture_players')) {
            Schema::create('fixture_players', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('fixture_id');
                $table->integer('team1_id')->nullable();
                $table->integer('team2_id')->nullable();
            });
        }

        if (!Schema::hasTable('fixture_types')) {
            Schema::create('fixture_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('type', 191);
            });
        }

        if (!Schema::hasTable('genders')) {
            Schema::create('genders', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('genderType');
            });
        }

        if (!Schema::hasTable('goal_goal_names')) {
            Schema::create('goal_goal_names', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('goal_id');
                $table->integer('goal_name_id');
            });
        }

        if (!Schema::hasTable('goal_names')) {
            Schema::create('goal_names', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
                $table->integer('goal_type_id');
            });
        }

        if (!Schema::hasTable('goal_themes')) {
            Schema::create('goal_themes', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('theme');
            });
        }

        if (!Schema::hasTable('goal_types')) {
            Schema::create('goal_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('name');
                $table->integer('goal_theme_id')->nullable();
            });
        }

        if (!Schema::hasTable('goals')) {
            Schema::create('goals', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('player_id');
                $table->date('startDate')->nullable();
                $table->date('endDate')->nullable();
            });
        }

        if (!Schema::hasTable('invatations')) {
            Schema::create('invatations', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('eventId', 191);
                $table->integer('event_category_id');
                $table->integer('player_id');
                $table->integer('registration_status');
                $table->integer('user_id');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue', 191)->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('leaderboards')) {
            Schema::create('leaderboards', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('player_id');
                $table->integer('series_id');
                $table->integer('total_points');
                $table->integer('best_2_points')->nullable();
                $table->integer('num_events')->nullable();
                $table->string('category_id', 200)->nullable();
                $table->integer('school_grade')->nullable();
            });
        }

        if (!Schema::hasTable('no_profile_players')) {
            Schema::create('no_profile_players', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('full_name');
                $table->integer('fixture_practice_id');
            });
        }

        if (!Schema::hasTable('no_profile_team_players')) {
            Schema::create('no_profile_team_players', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('team_id');
                $table->integer('rank');
                $table->integer('pay_status');
                $table->string('name', 100)->nullable();
                $table->string('surname', 100)->nullable();
                $table->integer('player_profile')->nullable();
            });
        }

        if (!Schema::hasTable('overberg_clothings')) {
            Schema::create('overberg_clothings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191);
                $table->string('shirt_id', 191)->nullable();
                $table->string('hoodie_id', 191)->nullable();
                $table->integer('pay_status')->nullable();
                $table->string('team', 100)->nullable();
                $table->integer('pf_id')->nullable();
                $table->integer('cap_id')->nullable();
                $table->integer('skirt_id')->nullable();
                $table->integer('pants_id')->nullable();
                $table->integer('hot_pants_id')->nullable();
                $table->integer('peak_id')->nullable();
                $table->integer('jacket_id')->nullable();
            });
        }

        if (!Schema::hasTable('paarl_wilson_tournament')) {
            Schema::create('paarl_wilson_tournament', function (Blueprint $table) {
                $table->increments('id');
                $table->dateTime('date_time')->nullable();
                $table->string('payment')->nullable();
                $table->string('cellular_nr')->nullable();
                $table->string('name')->nullable();
                $table->string('surname')->nullable()->index();
                $table->text('gender')->nullable();
                $table->text('category')->nullable();
                $table->integer('userID')->nullable();
                $table->integer('userEmail')->nullable();
                $table->string('email')->nullable();
            });
        }

        if (!Schema::hasTable('pdf_draws')) {
            Schema::create('pdf_draws', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 191);
                $table->integer('size');
            });
        }

        if (!Schema::hasTable('player_positions')) {
            Schema::create('player_positions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('player_subscriptions')) {
            Schema::create('player_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('player_id');
                $table->integer('subscription_id');
            });
        }

        if (!Schema::hasTable('points')) {
            Schema::create('points', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('score');
                $table->integer('series_id')->nullable();
                $table->integer('position')->nullable();
                $table->integer('points_template_created')->nullable();
            });
        }

        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('player_id');
                $table->integer('category_event_id');
                $table->integer('position');
                $table->integer('round_robin_score')->nullable();
            });
        }

        if (!Schema::hasTable('practice_durations')) {
            Schema::create('practice_durations', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('duration');
            });
        }

        if (!Schema::hasTable('practice_fixtures')) {
            Schema::create('practice_fixtures', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('registration1_id');
                $table->text('registration2_id');
                $table->integer('practice_id')->nullable();
            });
        }

        if (!Schema::hasTable('practice_results')) {
            Schema::create('practice_results', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('practice_fixture_id');
                $table->integer('winner_registration');
                $table->integer('loser_registration');
                $table->integer('registration1_score');
                $table->integer('registration2_score');
            });
        }

        if (!Schema::hasTable('practice_types')) {
            Schema::create('practice_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('practice_type');
            });
        }

        if (!Schema::hasTable('practices')) {
            Schema::create('practices', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('duration_id');
                $table->integer('practice_type_id');
                $table->integer('player_id');
                $table->date('date_of_lesson')->nullable();
            });
        }

        if (!Schema::hasTable('rank_types')) {
            Schema::create('rank_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('type');
                $table->string('name');
                $table->text('description')->nullable();
            });
        }

        if (!Schema::hasTable('rank_venue_mappings')) {
            Schema::create('rank_venue_mappings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ranking_list_categories')) {
            Schema::create('ranking_list_categories', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('event_category_id');
                $table->integer('ranking_list_id');
            });
        }

        if (!Schema::hasTable('ranking_list_category_events')) {
            Schema::create('ranking_list_category_events', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('category_event_id');
                $table->integer('ranking_list_id');
                $table->unique(['ranking_list_id', 'category_event_id'], 'rl_ce_unique');
            });
        }

        if (!Schema::hasTable('ranking_lists')) {
            Schema::create('ranking_lists', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('category_id');
                $table->integer('series_id');
                $table->unsignedTinyInteger('best_num_of_scores')->nullable();
            });
        }

        if (!Schema::hasTable('ranking_score_legs')) {
            Schema::create('ranking_score_legs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ranking_score_id')->index();
                $table->unsignedBigInteger('player_id')->index();
                $table->unsignedBigInteger('category_event_id');
                $table->string('event_name');
                $table->integer('position');
                $table->integer('points');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ranking_scores')) {
            Schema::create('ranking_scores', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('ranking_list_id');
                $table->integer('player_id');
                $table->integer('total_points');
                $table->integer('num_events')->nullable();
                $table->integer('highSchool')->nullable();
                $table->integer('primarySchool')->nullable();
            });
        }

        if (!Schema::hasTable('rankings')) {
            Schema::create('rankings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('player_id');
                $table->string('category_event', 1000)->nullable();
                $table->integer('score_id_1')->default(0);
                $table->integer('score_id_2')->nullable()->default(0);
                $table->integer('score_id_3')->nullable()->default(0);
                $table->integer('total');
                $table->integer('region');
                $table->integer('school_grade')->nullable();
            });
        }

        if (!Schema::hasTable('registration_sub_draws')) {
            Schema::create('registration_sub_draws', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('sub_draw_id');
                $table->integer('registration_id');
            });
        }

        if (!Schema::hasTable('results')) {
            Schema::create('results', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('winner_registration')->nullable();
                $table->integer('loser_registration')->nullable();
                $table->integer('registration1_score');
                $table->integer('registration2_score');
                $table->integer('set_nr');
            });
        }

        if (!Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('fixture_id');
                $table->integer('venue_id');
                $table->integer('draw_id');
                $table->dateTime('time');
            });
        }

        if (!Schema::hasTable('scoreboards')) {
            Schema::create('scoreboards', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('singles_win_3_0')->default(0);
                $table->integer('show_scoreboard')->nullable()->default(0);
                $table->integer('singles_win_2_1')->default(0);
                $table->integer('singles_loose_3_0')->default(0);
                $table->integer('singles_loose_2_1')->default(0);
                $table->integer('doubles_win')->default(0);
                $table->integer('doubles_loose')->default(0);
                $table->integer('doubles_loose_tiebreak')->default(0);
                $table->integer('mixed_win')->default(0);
                $table->integer('mixed_loose')->default(0);
                $table->integer('mixed_loose_tiebreak')->default(0);
            });
        }

        if (!Schema::hasTable('scoring_formats')) {
            Schema::create('scoring_formats', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('format', 191);
                $table->integer('points');
            });
        }

        if (!Schema::hasTable('scorings')) {
            Schema::create('scorings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('scoringName');
                $table->text('sets');
            });
        }

        if (!Schema::hasTable('sell_products')) {
            Schema::create('sell_products', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('product_name');
                $table->integer('sell_type_id');
                $table->integer('price')->nullable();
                $table->integer('event_id')->nullable();
            });
        }

        if (!Schema::hasTable('sell_types')) {
            Schema::create('sell_types', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('type');
            });
        }

        if (!Schema::hasTable('series')) {
            Schema::create('series', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->text('name');
                $table->integer('leaderboard_published')->default(0);
                $table->integer('best_num_of_scores')->nullable();
                $table->integer('points_template_created')->nullable();
                $table->string('rank_type', 100)->nullable()->default('position_based');
                $table->integer('year');
            });
        }

        if (!Schema::hasTable('series_points')) {
            Schema::create('series_points', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('series_id');
                $table->integer('position');
                $table->integer('points_id');
            });
        }

        if (!Schema::hasTable('series_rankings')) {
            Schema::create('series_rankings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('series_id')->index();
                $table->unsignedBigInteger('ranking_list_id')->nullable()->index();
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('player_id');
                $table->unsignedInteger('rank_position');
                $table->unsignedInteger('total_points')->default(0);
                $table->longText('meta_json')->nullable();
                $table->string('status', 20)->default('calculated')->index();
                $table->string('run_id', 64)->nullable()->index();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ranking_audit_logs')) {
            Schema::create('ranking_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('series_id')->index();
                $table->string('run_id', 64)->nullable()->index();
                $table->string('action', 80);
                $table->longText('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('draws');
                $table->integer('practiceMatches');
                $table->integer('event_id')->nullable();
            });
        }

        if (!Schema::hasTable('sub_draws')) {
            Schema::create('sub_draws', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('draw_id');
                $table->text('subDrawName');
                $table->integer('drawFormat')->nullable();
            });
        }

        if (!Schema::hasTable('subscription_infos')) {
            Schema::create('subscription_infos', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->date('start_date');
                $table->date('finish_date');
                $table->integer('user_id');
                $table->integer('payment_type');
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('type');
                $table->integer('price');
                $table->string('information')->nullable();
                $table->integer('subscription_info_id')->nullable();
            });
        }

        if (!Schema::hasTable('team_categories')) {
            Schema::create('team_categories', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('ageGroup', 191);
                $table->integer('published');
                $table->integer('eventId');
            });
        }

        if (!Schema::hasTable('team_fixture_results')) {
            Schema::create('team_fixture_results', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('team_fixture_id');
                $table->integer('team1_score');
                $table->integer('team2_score');
                $table->integer('match_winner_id')->nullable();
                $table->integer('set_nr')->nullable();
                $table->integer('match_loser_id')->nullable();
            });
        }

        if (!Schema::hasTable('team_fixtures')) {
            Schema::create('team_fixtures', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('draw_id');
                $table->integer('match_nr');
                $table->integer('numSets')->nullable();
                $table->integer('fixture_type')->nullable();
                $table->integer('round_nr')->nullable();
                $table->integer('tie_nr')->nullable();
                $table->integer('region1')->nullable();
                $table->integer('region2')->nullable();
                $table->string('age', 100)->nullable();
                $table->integer('rank_nr')->nullable();
                $table->integer('scheduled')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->unsignedBigInteger('venue_id')->nullable()->index();
                $table->string('court_label', 50)->nullable();
                $table->unsignedInteger('duration_min')->nullable();
                $table->integer('home_rank_nr')->nullable();
                $table->integer('away_rank_nr')->nullable();
                $table->integer('clash_flag')->nullable();
            });
        }

        if (!Schema::hasTable('team_order_of_plays')) {
            Schema::create('team_order_of_plays', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->integer('team_fixture_id')->nullable();
                $table->integer('venue_id')->nullable();
                $table->dateTime('time')->nullable();
            });
        }

        if (!Schema::hasTable('team_regions')) {
            Schema::create('team_regions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('region_name', 191);
                $table->integer('clothing_order')->nullable();
                $table->integer('clothing_admin')->nullable();
                $table->text('short_name')->nullable();
                $table->integer('no_profile')->nullable();
                $table->integer('region_fee')->nullable();
            });
        }

        if (!Schema::hasTable('team_scoreboards')) {
            Schema::create('team_scoreboards', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
                $table->integer('event_id');
            });
        }

        if (!Schema::hasTable('tests')) {
            Schema::create('tests', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
            });
        }

        if (!Schema::hasTable('wp_cav_doubles_15_17')) {
            Schema::create('wp_cav_doubles_15_17', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name', 200);
                $table->integer('rank');
                $table->integer('team_id');
                $table->integer('pay_status')->nullable();
                $table->integer('user_id')->nullable();
                $table->integer('num_team_members')->nullable();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'wp_cav_doubles_15_17', 'tests', 'team_scoreboards', 'team_regions',
            'team_order_of_plays', 'team_fixtures', 'team_fixture_results', 'team_categories',
            'subscriptions', 'subscription_infos', 'sub_draws', 'settings', 'series_rankings',
            'series_points', 'series', 'sell_types', 'sell_products', 'scorings',
            'scoring_formats', 'scoreboards', 'schedules', 'results', 'registration_sub_draws',
            'rankings', 'ranking_scores', 'ranking_score_legs', 'ranking_lists',
            'ranking_list_category_events', 'ranking_list_categories', 'rank_venue_mappings',
            'rank_types', 'practices', 'practice_types', 'practice_results', 'practice_fixtures',
            'practice_durations', 'positions', 'points', 'player_subscriptions', 'player_positions',
            'pdf_draws', 'paarl_wilson_tournament', 'overberg_clothings', 'no_profile_team_players',
            'no_profile_players', 'leaderboards', 'jobs', 'invatations', 'goals', 'goal_types',
            'goal_themes', 'goal_names', 'goal_goal_names', 'genders', 'fixture_types',
            'fixture_players', 'files', 'failed_jobs', 'exercises', 'exercise_types',
            'exercise_names', 'eventtypes', 'event_venues', 'event_teams', 'event_regions',
            'event_pdf_draws', 'event_nominations', 'event_draw_types', 'draw_types', 'draw_teams',
            'draw_group_rankings', 'draw_formats', 'draw_events', 'covids', 'clothing_sizes',
            'clothing_order_items', 'clothing_item_types', 'cavalier_clothings', 'category_results',
            'brackets', 'announcements', 'announcement_events',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
