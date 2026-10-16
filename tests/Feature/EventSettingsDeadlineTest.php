<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSettingsDeadlineTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validDeadlines')]
    public function test_settings_save_with_a_blank_or_valid_deadline(mixed $deadline, int $expected): void
    {
        $event = Event::factory()->create(['deadline' => 14]);
        $otherEvent = Event::factory()->create(['deadline' => 21]);

        $this->actingAs($this->administrator())
            ->patchJson(route('admin.events.settings.update', $event), [
                'name' => 'Updated event settings',
                'deadline' => $deadline,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Updated event settings',
            'deadline' => $expected,
        ]);
        $this->assertSame(21, (int) $otherEvent->fresh()->deadline);
    }

    public static function validDeadlines(): array
    {
        return [
            'blank form field' => ['', 14],
            'null field' => [null, 14],
            'zero days' => [0, 0],
            'explicit days' => ['7', 7],
        ];
    }

    public function test_omitting_deadline_preserves_existing_days(): void
    {
        $event = Event::factory()->create(['deadline' => 14]);

        $this->actingAs($this->administrator())
            ->patchJson(route('admin.events.settings.update', $event), ['name' => 'Renamed event'])
            ->assertOk();

        $this->assertSame(14, (int) $event->fresh()->deadline);
        $this->assertSame('Renamed event', $event->fresh()->name);
    }

    #[DataProvider('invalidDeadlines')]
    public function test_invalid_deadline_does_not_save_settings(mixed $deadline): void
    {
        $event = Event::factory()->create(['deadline' => 14]);

        $this->actingAs($this->administrator())
            ->patchJson(route('admin.events.settings.update', $event), [
                'name' => 'Must not save',
                'deadline' => $deadline,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');

        $this->assertSame(14, (int) $event->fresh()->deadline);
        $this->assertSame($event->name, $event->fresh()->name);
    }

    public static function invalidDeadlines(): array
    {
        return [
            'negative days' => [-1],
            'fractional days' => [1.5],
            'non-numeric value' => ['tomorrow'],
        ];
    }

    private function administrator(): User
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);

        return User::factory()->create()->assignRole('super-user');
    }
}
