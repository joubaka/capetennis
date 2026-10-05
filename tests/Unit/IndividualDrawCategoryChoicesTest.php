<?php

namespace Tests\Unit;

use App\Support\IndividualDrawCategoryChoices;
use PHPUnit\Framework\TestCase;

class IndividualDrawCategoryChoicesTest extends TestCase
{
    public function test_standard_choices_deduplicate_aliases_without_promoting_divisions(): void
    {
        $categories = collect([
            (object) ['pivot_id' => 1, 'name' => 'u / 13 Boys'],
            (object) ['pivot_id' => 3, 'name' => 'u/13 Boys'],
            (object) ['pivot_id' => 2, 'name' => 'u/13 Boys'],
            (object) ['pivot_id' => 4, 'name' => 'u/13 Boys A division'],
            (object) ['pivot_id' => 5, 'name' => 'u/13 Boys B division'],
            (object) ['pivot_id' => 6, 'name' => 'u/13 Girls'],
            (object) ['pivot_id' => 7, 'name' => 'u/12 Girls A division'],
            (object) ['pivot_id' => 8, 'name' => 'Division 1 Boys'],
        ]);
        $choices = IndividualDrawCategoryChoices::make($categories);

        $this->assertSame(['u/13 Boys', 'u/13 Girls'], array_column($choices, 'name'));
        $this->assertSame([2, 6], array_column($choices, 'pivot_id'));
        $this->assertSame('u/13 Boys', $choices[0]->source_name);
        $this->assertEquals($choices, IndividualDrawCategoryChoices::make($categories->reverse()));
    }
}
