<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Models\DrawRecoveryCase;
use App\Models\DrawRecoverySnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DrawRecoverySnapshotService
{
    public function capture(Draw $draw, string $kind, ?DrawRecoveryCase $case = null): DrawRecoverySnapshot
    {
        $payload = $this->payload($draw);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return DrawRecoverySnapshot::create([
            'draw_id' => $draw->id,
            'recovery_case_id' => $case?->id,
            'created_by' => auth()->id(),
            'kind' => $kind,
            'checksum' => hash('sha256', $json),
            'payload' => $payload,
        ]);
    }

    public function checksum(Draw $draw): string
    {
        return hash('sha256', json_encode(
            $this->payload($draw),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * Restore the individual competition graph. The restored draw deliberately
     * remains locked and unpublished so a human must review it before release.
     */
    public function restore(DrawRecoverySnapshot $snapshot): array
    {
        $payload = $snapshot->payload;
        if (! is_array($payload) || (int) data_get($payload, 'draw.id') !== (int) $snapshot->draw_id) {
            throw new RuntimeException('The recovery snapshot is invalid or belongs to another draw.');
        }
        $payloadChecksum = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
        if (! hash_equals($snapshot->checksum, $payloadChecksum)) {
            throw new RuntimeException('The recovery snapshot checksum is invalid; restore was refused.');
        }

        return DB::transaction(function () use ($snapshot, $payload) {
            $draw = Draw::whereKey($snapshot->draw_id)->lockForUpdate()->firstOrFail();
            $fixtureRows = collect($payload['fixtures'] ?? [])->map(fn ($row) => (array) $row);
            $snapshotIds = $fixtureRows->pluck('id')->map(fn ($id) => (int) $id);
            $currentIds = DB::table('fixtures')->where('draw_id', $draw->id)->pluck('id')->map(fn ($id) => (int) $id);
            $allIds = $snapshotIds->merge($currentIds)->unique()->values();

            $collision = DB::table('fixtures')->whereIn('id', $snapshotIds)
                ->where('draw_id', '!=', $draw->id)->exists();
            if ($collision) {
                throw new RuntimeException('A fixture ID from this snapshot has since been reused by another draw.');
            }

            $this->deleteChildren($draw->id, $allIds->all());
            DB::table('fixtures')->where('draw_id', $draw->id)->delete();

            if ($fixtureRows->isNotEmpty()) {
                $links = $fixtureRows->mapWithKeys(fn ($row) => [(int) $row['id'] => [
                    'parent_fixture_id' => $row['parent_fixture_id'] ?? null,
                    'loser_parent_fixture_id' => $row['loser_parent_fixture_id'] ?? null,
                ]]);
                $base = $fixtureRows->map(function ($row) {
                    $row['parent_fixture_id'] = null;
                    $row['loser_parent_fixture_id'] = null;
                    return $row;
                })->all();
                DB::table('fixtures')->insert($base);
                foreach ($links as $id => $fields) {
                    DB::table('fixtures')->where('id', $id)->update($fields);
                }
            }

            $this->insertRows('fixture_results', $payload['fixture_results'] ?? []);
            $this->insertRows('order_of_plays', $payload['order_of_plays'] ?? []);
            $this->insertRows('schedules', $payload['schedules'] ?? []);

            $draw->forceFill([
                'published' => false,
                'oop_published' => false,
                'locked' => true,
                'oop_created' => (bool) data_get($payload, 'draw.oop_created', false),
            ])->save();

            return [
                'fixtures' => $fixtureRows->count(),
                'results' => count($payload['fixture_results'] ?? []),
                'scheduled_times' => count($payload['order_of_plays'] ?? []),
            ];
        });
    }

    private function payload(Draw $draw): array
    {
        $fixtureIds = DB::table('fixtures')->where('draw_id', $draw->id)->orderBy('id')->pluck('id');
        $groupIds = DB::table('draw_groups')->where('draw_id', $draw->id)->orderBy('id')->pluck('id');

        return [
            'version' => 1,
            'draw' => (array) DB::table('draws')->where('id', $draw->id)->first(),
            'draw_settings' => $this->rows('draw_settings', fn ($query) => $query->where('draw_id', $draw->id)),
            'draw_registrations' => $this->rows('draw_registrations', fn ($query) => $query->where('draw_id', $draw->id)),
            'draw_groups' => $this->rows('draw_groups', fn ($query) => $query->where('draw_id', $draw->id)),
            'draw_group_registrations' => $this->rows('draw_group_registrations', fn ($query) => $query->whereIn('draw_group_id', $groupIds)),
            'fixtures' => $this->rows('fixtures', fn ($query) => $query->where('draw_id', $draw->id)),
            'fixture_results' => $this->rows('fixture_results', fn ($query) => $query->whereIn('fixture_id', $fixtureIds)),
            'order_of_plays' => $this->rows('order_of_plays', fn ($query) => $query->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds)),
            'schedules' => $this->rows('schedules', fn ($query) => $query->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds)),
            'flexible_monrad_draws' => $this->rows('flexible_monrad_draws', fn ($query) => $query->where('draw_id', $draw->id)),
        ];
    }

    private function rows(string $table, callable $scope): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);
        $scope($query);

        $columns = Schema::getColumnListing($table);
        foreach (array_values(array_intersect(['id', 'draw_id', 'draw_group_id', 'fixture_id', 'registration_id'], $columns)) as $column) {
            $query->orderBy($column);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    private function deleteChildren(int $drawId, array $fixtureIds): void
    {
        if (Schema::hasTable('order_of_plays')) {
            DB::table('order_of_plays')->where('draw_id', $drawId)->orWhereIn('fixture_id', $fixtureIds)->delete();
        }
        if (Schema::hasTable('schedules')) {
            DB::table('schedules')->where('draw_id', $drawId)->orWhereIn('fixture_id', $fixtureIds)->delete();
        }
        DB::table('fixture_results')->whereIn('fixture_id', $fixtureIds)->delete();
    }

    private function insertRows(string $table, array $rows): void
    {
        if ($rows && Schema::hasTable($table)) {
            DB::table($table)->insert(array_map(fn ($row) => (array) $row, $rows));
        }
    }
}
