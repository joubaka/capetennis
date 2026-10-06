<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PlayerProfileAgeGroupsTest extends TestCase
{
    private function field(int $eventId, ?string $name): CategoryEvent
    {
        $field = new CategoryEvent(['event_id' => $eventId]);
        $field->setRelation('category', $name === null ? null : new Category(['name' => $name]));

        return $field;
    }

    private function renderGroups(array $fields, ?Event $event): string
    {
        $registration = new Registration;
        $registration->setRelation('categoryEvents', new Collection($fields));

        return view('multiend.partials.registration-age-groups', [
            'registration' => $registration,
            'registeredEvent' => $event,
        ])->render();
    }

    public function test_shows_recorded_groups_once_and_excludes_other_events(): void
    {
        $event = new Event;
        $event->id = 10;
        $html = $this->renderGroups([
            $this->field(10, 'U/12 Girls'),
            $this->field(10, 'U/12 Girls'),
            $this->field(10, 'U/14 Girls'),
            $this->field(20, 'U/16 Girls'),
        ], $event);

        $this->assertSame(1, substr_count($html, 'U/12 Girls'));
        $this->assertStringContainsString('U/14 Girls', $html);
        $this->assertStringNotContainsString('U/16 Girls', $html);
        $this->assertStringNotContainsString('Not recorded', $html);
    }

    public function test_missing_categories_and_events_have_an_explicit_fallback(): void
    {
        $event = new Event;
        $event->id = 10;

        $this->assertStringContainsString('Not recorded', $this->renderGroups([
            $this->field(10, null), $this->field(10, ''),
        ], $event));
        $this->assertStringContainsString('Not recorded', $this->renderGroups([
            $this->field(10, 'U/12 Girls'),
        ], null));
    }

    public function test_category_labels_are_escaped(): void
    {
        $event = new Event;
        $event->id = 10;
        $html = $this->renderGroups([$this->field(10, '<script>alert(1)</script>')], $event);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
