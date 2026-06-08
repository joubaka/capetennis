<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Models\CategoryEvent;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CategoryEventDoublesFlagTest
 *
 * Verifies:
 *   - is_doubles column exists with default false
 *   - CategoryEvent::isDoubles() returns correct values
 *   - Existing category events are unaffected (default false)
 *   - FeatureFlags::DOUBLES_FOUNDATION constant exists and defaults to false
 *   - Flag can be enabled/disabled without side effects
 */
class CategoryEventDoublesFlagTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    public function test_is_doubles_column_exists(): void
    {
        $this->assertTrue(\Schema::hasColumn('category_events', 'is_doubles'));
    }

    public function test_is_doubles_defaults_to_false(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();

        $this->assertFalse((bool) $categoryEvent->is_doubles);
    }

    // -------------------------------------------------------------------------
    // isDoubles() helper
    // -------------------------------------------------------------------------

    public function test_is_doubles_returns_false_by_default(): void
    {
        $categoryEvent = CategoryEvent::factory()->create();

        $this->assertFalse($categoryEvent->isDoubles());
    }

    public function test_is_doubles_returns_true_when_set(): void
    {
        $categoryEvent = CategoryEvent::factory()->create(['is_doubles' => true]);

        $this->assertTrue($categoryEvent->isDoubles());
    }

    public function test_is_doubles_can_be_updated(): void
    {
        $categoryEvent = CategoryEvent::factory()->create(['is_doubles' => false]);

        $categoryEvent->update(['is_doubles' => true]);
        $categoryEvent->refresh();

        $this->assertTrue($categoryEvent->isDoubles());
    }

    // -------------------------------------------------------------------------
    // FeatureFlags::DOUBLES_FOUNDATION
    // -------------------------------------------------------------------------

    public function test_doubles_foundation_flag_constant_exists(): void
    {
        $this->assertSame('doubles_foundation', FeatureFlags::DOUBLES_FOUNDATION);
        $this->assertContains(FeatureFlags::DOUBLES_FOUNDATION, FeatureFlags::ALL_FLAGS);
    }

    public function test_doubles_foundation_flag_is_disabled_by_default(): void
    {
        // Clear any cache override from other tests
        FeatureFlags::clearOverride(FeatureFlags::DOUBLES_FOUNDATION);

        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION));
    }

    public function test_doubles_foundation_flag_can_be_enabled_at_runtime(): void
    {
        FeatureFlags::enable(FeatureFlags::DOUBLES_FOUNDATION);

        $this->assertTrue(FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION));

        // Clean up
        FeatureFlags::clearOverride(FeatureFlags::DOUBLES_FOUNDATION);
    }

    public function test_doubles_foundation_flag_can_be_disabled_after_enabling(): void
    {
        FeatureFlags::enable(FeatureFlags::DOUBLES_FOUNDATION);
        FeatureFlags::disable(FeatureFlags::DOUBLES_FOUNDATION);

        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION));

        FeatureFlags::clearOverride(FeatureFlags::DOUBLES_FOUNDATION);
    }

    // -------------------------------------------------------------------------
    // Existing categories are completely unaffected
    // -------------------------------------------------------------------------

    public function test_existing_category_events_have_is_doubles_false(): void
    {
        $events = CategoryEvent::factory()->count(5)->create();

        foreach ($events as $event) {
            $this->assertFalse($event->isDoubles(), "CategoryEvent {$event->id} unexpectedly has is_doubles=true");
        }
    }
}
