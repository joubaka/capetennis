<?php

namespace App\Services\Performance;

use App\Models\{Category, CategoryEvent, Draw};
use Illuminate\Support\Facades\{DB, Schema};

/** Resolve a display context without guessing from age or a player's rating. */
class BallRatingContext
{
    public function stage(Draw|CategoryEvent|Category|null $context): ?string
    {
        if ($context === null) return 'standard';
        if ($context instanceof Category) {
            $label = BallRatingModel::labelled($context->name, true);
            return $label === null ? BallRatingModel::bucket(null, $context->name) : $label['stage'];
        }
        $field = $context instanceof Draw ? $context->categoryEvent : $context;
        if ($context instanceof Draw && $field && (int) $field->event_id !== (int) $context->event_id) return null;
        $event = $context instanceof Draw ? $context->event : $field?->event;
        $drawIds = $context instanceof Draw ? [$context->id] : Draw::query()->where('category_event_id', $context->id)->where('event_id', $context->event_id)->limit(257)->pluck('id')->all();
        if (count($drawIds) > 256) return null;
        if ($drawIds && Schema::hasTable('draw_venue_court_allocations') && Schema::hasTable('event_venue_courts')) {
            $types = DB::table('draw_venue_court_allocations as a')->join('draws as d', 'd.id', '=', 'a.draw_id')->join('event_venue_courts as c', function ($join) {
                $join->on('c.event_id', '=', 'd.event_id')->on('c.venue_id', '=', 'a.venue_id')->on('c.label', '=', 'a.court_label');
            })->whereIn('a.draw_id', $drawIds)->whereNotNull('c.ball_type')->distinct()->limit(10)->pluck('c.ball_type')->all();
            $stages = array_values(array_unique(array_map(fn ($type) => BallRatingModel::bucket($type), $types)));
            if (count($stages) > 1) return null;
            if (count($stages) === 1) return $stages[0];
        }
        $label = $field?->category?->name ?? ($context instanceof Draw ? $context->drawName : '');
        $specific = BallRatingModel::labelled($label, true);
        if ($specific !== null) return $specific['stage'];
        if (BallRatingModel::isWilsonOrange($label, $event?->name)) return 'orange';
        return BallRatingModel::bucket(null, $event?->name);
    }
}
