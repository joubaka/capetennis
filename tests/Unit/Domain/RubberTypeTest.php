<?php

namespace Tests\Unit\Domain;

use App\Domain\TeamDraw\RubberType;
use Tests\TestCase;

class RubberTypeTest extends TestCase
{
    public function test_canonical_rubber_type_values_are_stable(): void
    {
        $this->assertSame('singles', RubberType::SINGLES);
        $this->assertSame('reverse_singles', RubberType::REVERSE_SINGLES);
        $this->assertSame('doubles', RubberType::DOUBLES);
        $this->assertSame('mixed_doubles', RubberType::MIXED_DOUBLES);
    }

    public function test_legacy_numeric_mapping_matches_repository_behavior(): void
    {
        $this->assertSame(RubberType::SINGLES, RubberType::fromLegacyFixtureType(1));
        $this->assertSame(RubberType::DOUBLES, RubberType::fromLegacyFixtureType(2));
        $this->assertSame(RubberType::MIXED_DOUBLES, RubberType::fromLegacyFixtureType(3));
        $this->assertSame(RubberType::REVERSE_SINGLES, RubberType::fromLegacyFixtureType(4));
    }

    public function test_unsupported_legacy_numeric_values_are_rejected_with_null(): void
    {
        $this->assertNull(RubberType::fromLegacyFixtureType(5));
        $this->assertNull(RubberType::fromLegacyFixtureType(6));
        $this->assertNull(RubberType::fromLegacyFixtureType(999));
    }
}
