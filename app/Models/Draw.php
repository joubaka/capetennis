<?php

namespace App\Models;

use App\Services\Fixtures;
use App\Services\FeatureFlags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draw extends Model
{
    use HasFactory;
    protected $with = ['venues'];
    protected $table = 'draws';

    protected $fillable = [
        'drawName',
        'drawType_id',
        'category_event_id',
        'event_id',
        'published',
        'oop_published',
        'locked',
        'num_courts',
        'start_time',
        'time_per_match',
        'team_category_id',
        'gender',
        'oop_created',
        'engine_mode',
        'team_event_format_id',
    ];
    public function drawFormat()
    {
        return $this->belongsTo(\App\Models\DrawFormats::class, 'drawType_id');
    }
    public function drawFixtures()
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id');

    }
    public function fixtures_per_round()
    {
        return $this->hasMany(Fixture::class,'draw_id','id')
        ->select('round')->groupBy('round');
    }
    public function draw_groups($draw_id)
    {
        return $this->hasMany(DrawGroup::class, 'draw_id', 'id')->where('draw_id', $draw_id)->orderBy('id')->get();
    }
    public function groups()
    {
        return $this->hasMany(DrawGroup::class, 'draw_id', 'id');
    }
   public function settings()
{
    return $this->hasOne(DrawSetting::class);
}
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
    public function draw_types()
    {

        return $this->belongsTo(DrawType::class, 'drawType_id', 'id');
    }

    public function team_category()
    {
        return $this->belongsTo(TeamCategory::class,'team_category_id','id');
    }

public function registrations()
{
    return $this->belongsToMany(Registration::class, 'draw_registrations', 'draw_id', 'registration_id')->withPivot('seed', 'box_number')
      ->orderBy('pivot_seed');   // *** IMPORTANT ***;
        
}

public function drawRegistrations()
{
    return $this->hasMany(drawRegistrations::class, 'draw_id', 'id');
}

public function getAllPlayersAttribute()
{
    return $this->registrations->flatMap(fn($r) => $r->players)->unique('id');
}

    public function fixtures()
    {
        return $this->hasMany(TeamFixture::class)->orderBy('id');
    }
    public function fixtures_per_venue()
    {
        return $this->hasMany(TeamFixture::class)->orderBy('venue_id');
    }

    public function last_fixtures()
    {

        return $this->hasMany(Fixture::class, 'draw_id', 'id')->select('registration1_id')->groupBy('registration1_id');
    }
    public function ind_fixtures()
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')->orderBy('id');
    }

    public function ind_fixtures_round_1($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 1)
        ->where('bracket_id',$bracket_id)
        ->orderBy('match_nr')
        ->get();
    }
    public function ind_fixtures_round_2($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 2)
        ->where('bracket_id',$bracket_id)
        ->orderBy('match_nr')
        ->get();
    }
    public function ind_fixtures_5_8($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 2)
        ->where('bracket_id',$bracket_id)
        ->where([['match_nr','>',6],['match_nr','<',9]])
       ->orderBy('match_nr','asc')

        ->get();
    }
    public function ind_fixtures_round_3($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 3)
        ->where('bracket_id',$bracket_id)
        ->orderBy('match_nr')
        ->get();
    }

    public function ind_fixtures_7_8($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 3)
        ->where('bracket_id',$bracket_id)
        ->orderBy('match_nr','desc')
        ->get();
    }
    public function ind_fixtures_5_6($bracket_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 3)
        ->where('bracket_id',$bracket_id)
        ->where('match_nr', 11)
        ->orderBy('match_nr','desc')
        ->get();
    }
    public function pos_playoffs($bracket_id,$match_id)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
        ->where('round', 3)
        ->where('bracket_id',$bracket_id)
        ->where('match_nr', $match_id)
        ->orderBy('match_nr','desc')
        ->get();
    }
    public function ind_fixtures_byes_reg1()
    {

        return $this->hasMany(Fixture::class, 'draw_id', 'id')->Where('registration1_id', 0);
    }
    public function ind_fixtures_byes_reg2()
    {

        return $this->hasMany(Fixture::class, 'draw_id', 'id')->Where('registration2_id', 0);
    }

    public function fixtures_in_draw($draw)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')->where('draw_id', $draw)->orderBy('id')->get();
    }
    //bracket playoffs eg: overberg
    public function fixtures_in_playoff($draw)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')
            ->where('draw_id', $draw)
            ->where('draw_group_id', null)
            ->orderBy('id')->get();
    }


    public function fixtures_in_draw_day($draw)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')->where('draw_id', $draw)->orderBy('id')->get();
    }
    public function bracket_fixtures($bracket_id, $match_start, $match_end)
    {
        return $this->hasMany(Fixture::class, 'draw_id', 'id')->where('bracket_id', $bracket_id)->orderBy('id')->whereBetween('match_nr', [$match_start, $match_end])->get();
    }

    public function order_of_play()
    {
        return $this->hasMany(OrderOfPlay::class)->orderBy('id');
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'draw_venues', 'draw_id', 'venue_id') ->orderBy('id')->withPivot('num_courts');
    }

    public function draw_venue()
    {
        return $this->hasMany(DrawVenue::class);
    }
    public function rounds()
    {
        return $this->hasMany(TeamFixture::class)->select('round_nr')->groupBy('round_nr')->get();
    }

    public function teams_in_draw()
    {
        return $this->belongsToMany(Team::class, 'draw_teams');
    }

    public function teamEventFormat()
    {
        return $this->belongsTo(\App\Models\TeamEventFormat::class, 'team_event_format_id');
    }

    public function teamTies()
    {
        return $this->hasMany(\App\Models\TeamTie::class, 'draw_id');
    }
    public function ties($round)
    {
        return $this->hasMany(TeamFixture::class)->where('round_nr', $round)->get();
    }
    public function brackets()
    {
        return $this->belongsTo(Bracket::class,'bracket_id','id');
    }
    public function categoryEvent()
    {
        return $this->belongsTo(CategoryEvent::class,'category_event_id','id');
    }

public function getStructureType(): string
{
    $settings = $this->settings;

    if (!$settings) return 'unknown';

    if ($settings->supports_boxes) {
        return 'round_robin';
    }

    if ($settings->supports_playoff && $settings->default_playoff_size > 0) {
        return 'feed_in';
    }

    return 'knockout';
}

    /**
     * Resolve the effective engine mode for this draw.
     *
     * Priority: draw override → event override → global config.
     */
    public function effectiveEngineMode(): string
    {
        if ($this->engine_mode) {
            return $this->engine_mode;
        }

        // Lazy-load event if needed
        $event = $this->relationLoaded('event') ? $this->event : $this->event()->first();
        if ($event && $event->engine_mode) {
            return $event->engine_mode;
        }

        return config('capetennis_engine.mode', 'hybrid');
    }

    /**
     * Effective engine mode with feature-flag override applied.
     *
     * Priority: draw override → event override → feature flags → global config.
     */
    public function effectiveEngineModeWithFlags(): string
    {
        // Per-draw override always wins
        if ($this->engine_mode) {
            return $this->engine_mode;
        }

        // Per-event override
        $event = $this->relationLoaded('event') ? $this->event : $this->event()->first();
        if ($event && $event->engine_mode) {
            return $event->engine_mode;
        }

        // Feature-flag gates
        $flags = app(FeatureFlags::class);

        if ($flags->enabled('canonical_engine', $event?->id)) {
            return 'canonical';
        }

        if ($flags->enabled('hybrid_engine', $event?->id)) {
            return 'hybrid';
        }

        return config('capetennis_engine.mode', 'hybrid');
    }

    /**
     * Whether canonical mode is allowed for this draw.
     * Canonical is blocked when unresolved HIGH or MEDIUM mismatches exist.
     */
    public function canonicalAllowed(): bool
    {
        $blocked = \App\Models\EngineMismatch::forDraw($this->id)
            ->unresolved()
            ->whereIn('severity', ['high', 'medium'])
            ->exists();

        return ! $blocked;
    }

}
