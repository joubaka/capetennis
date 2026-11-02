<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\AssistantRepairLibrary;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AssistantRepairLibraryTest extends TestCase
{
    public function test_curated_guides_are_searchable_without_application_or_database_bootstrap(): void
    {
        $library = new AssistantRepairLibrary;
        $records = $library->search();
        $this->assertNotEmpty($records);
        $this->assertSame('RK-001', $library->search('rK-001')[0]['id']);
        $this->assertSame('RK-001', $library->search('ROUND rotating')[0]['id']);
        $this->assertSame([], $library->search('round nonexistent-symptom'));
        foreach ($records as $record) {
            $this->assertSame('guide_only', $record['assistant_action']);
            foreach (array_merge($record['sources'], $record['tests']) as $path) {
                $this->assertFileExists(dirname(__DIR__, 3).'/'.$path);
            }
        }
    }

    public function test_duplicate_ids_and_executable_records_are_rejected(): void
    {
        $library = new AssistantRepairLibrary;
        $record = $library->search()[0];
        foreach ([['version' => 2, 'repairs' => []],
            ['version' => 1, 'repairs' => [$record, $record]],
            ['version' => 1, 'repairs' => [array_replace($record, ['assistant_action' => 'execute'])]],
            ['version' => 1, 'repairs' => [array_replace($record, ['sources' => ['app/../secrets.php']])]],
            ['version' => 1, 'repairs' => [array_replace($record, ['tests' => []])]],
        ] as $invalid) {
            try {
                $library->validate($invalid);
                $this->fail('Invalid repair catalogue was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_search_input_is_bounded(): void
    {
        $this->expectException(RuntimeException::class);
        (new AssistantRepairLibrary)->search(str_repeat('a', 201));
    }
}
