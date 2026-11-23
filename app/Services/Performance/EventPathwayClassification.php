<?php

namespace App\Services\Performance;

use App\Models\CategoryEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Hosted-event guidance labels; never substitutes for played-match ball evidence. */
class EventPathwayClassification
{
    public const HOSTED_LEVELS = [
        'red' => 'Red ball', 'orange' => 'Orange ball',
        'wilson-b' => 'Wilson B', 'wilson-a' => 'Wilson A',
    ];

    public function explicit(CategoryEvent $field): ?string
    {
        return $field->getAttribute('pathway_level') ?? $field->event?->getAttribute('pathway_level');
    }

    public function hasExplicit(CategoryEvent $field): bool
    {
        return $this->explicit($field) !== null;
    }

    public static function presentationStage(?string $level): ?string
    {
        return match ($level) {
            'red', 'orange' => $level,
            'wilson-b' => 'Wilson B',
            'wilson-a' => 'Wilson A',
            default => null,
        };
    }

    public function forCategory(CategoryEvent $field): ?string
    {
        $level = $this->explicit($field);
        $stage = self::presentationStage($level);
        if ($stage === null) return null;
        $event = $field->event;
        if (!$event || (int) $field->event_id !== (int) $event->id) return null;
        $label = (string) $field->category?->name;
        $hasBallEvidence = preg_match('/\b(red|orange|green|yellow)\b/iu', $label) === 1
            || BallRatingModel::isOrangeDivision($label);
        if (Schema::hasTable('draws') && Schema::hasTable('draw_venue_court_allocations') && Schema::hasTable('event_venue_courts')) {
            $hasBallEvidence = $hasBallEvidence || DB::table('draw_venue_court_allocations as a')
                ->join('draws as d', 'd.id', '=', 'a.draw_id')
                ->join('event_venue_courts as c', function ($join) {
                    $join->on('c.event_id', '=', 'd.event_id')->on('c.venue_id', '=', 'a.venue_id')->on('c.label', '=', 'a.court_label');
                })->where('d.event_id', $field->event_id)->where('d.category_event_id', $field->id)
                ->whereNotNull('c.ball_type')->exists();
        }
        if ($hasBallEvidence) {
            $expected = in_array($level, ['red', 'orange'], true) ? $level : 'standard';
            $actual = Schema::hasTable('draws') ? app(BallRatingContext::class)->stage($field)
                : BallRatingModel::forMatch(['category' => $label]);
            if ($actual !== $expected) return null;
        }
        return $stage;
    }

    public function label(CategoryEvent $field): string
    {
        $level = $this->explicit($field);
        return self::HOSTED_LEVELS[$level] ?? 'Not classified';
    }
}
