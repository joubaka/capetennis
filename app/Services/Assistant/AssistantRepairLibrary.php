<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Curated repository guidance only; records are never executable instructions. */
final class AssistantRepairLibrary
{
    public function search(string $query = ''): array
    {
        $query = trim($query);
        if (mb_strlen($query) > 200) {
            throw new RuntimeException('Search must be 200 characters or fewer.');
        }
        $path = dirname(__DIR__, 3).'/docs/assistant/repairs/catalogue.json';
        $contents = @file_get_contents($path, false, null, 0, 262145);
        if ($contents === false || strlen($contents) > 262144) {
            throw new RuntimeException('Repair catalogue is unavailable or too large.');
        }
        return $this->find($this->validate(json_decode($contents, true, 16, JSON_THROW_ON_ERROR)), $query);
    }

    public function validate(mixed $catalogue): array
    {
        if (! is_array($catalogue) || ($catalogue['version'] ?? null) !== 1
            || ! is_array($catalogue['repairs'] ?? null) || ! array_is_list($catalogue['repairs'])
            || count($catalogue['repairs']) > 100) {
            throw new RuntimeException('Unsupported repair catalogue.');
        }
        $ids = [];
        foreach ($catalogue['repairs'] as $record) {
            if (! is_array($record)) {
                throw new RuntimeException('Invalid repair record.');
            }
            foreach (['id', 'title', 'scope', 'symptoms', 'cause', 'fix', 'verification', 'limits'] as $field) {
                if (! is_string($record[$field] ?? null) || trim($record[$field]) === '' || mb_strlen($record[$field]) > 2000) {
                    throw new RuntimeException('Invalid repair field: '.$field);
                }
            }
            if (! preg_match('/^RK-[0-9]{3}$/D', $record['id']) || isset($ids[$record['id']])
                || ($record['assistant_action'] ?? null) !== 'guide_only') {
                throw new RuntimeException('Invalid repair identity or unsupported execution status.');
            }
            foreach (['sources', 'tests'] as $field) {
                if (! is_array($record[$field] ?? null) || ! array_is_list($record[$field])
                    || count($record[$field]) < 1 || count($record[$field]) > 10) {
                    throw new RuntimeException('Invalid repair references.');
                }
                foreach ($record[$field] as $reference) {
                    if (! is_string($reference) || strlen($reference) > 240
                        || ! preg_match('/^(app|tests|docs)\/[A-Za-z0-9_\/-]+\.[A-Za-z0-9]+$/D', $reference)
                        || str_contains($reference, '..')) {
                        throw new RuntimeException('Invalid repository reference.');
                    }
                }
            }
            $ids[$record['id']] = true;
        }
        return $catalogue['repairs'];
    }

    private function find(array $records, string $query): array
    {
        if ($query === '') {
            return $records;
        }
        $terms = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($records, function (array $record) use ($terms): bool {
            $text = mb_strtolower(implode(' ', array_map(
                fn ($value) => is_array($value) ? implode(' ', $value) : $value, $record
            )));
            foreach ($terms as $term) {
                if (! str_contains($text, $term)) {
                    return false;
                }
            }
            return true;
        }));
    }
}
