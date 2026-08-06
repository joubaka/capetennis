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
        $this->assertSame('reverse_doubles', RubberType::REVERSE_DOUBLES);
        $this->assertSame('mixed_doubles', RubberType::MIXED_DOUBLES);
        $this->assertSame('reverse_mixed_doubles', RubberType::REVERSE_MIXED_DOUBLES);
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

    public function test_canonical_values_map_to_expected_legacy_fixture_types(): void
    {
        $this->assertSame(1, RubberType::toLegacyFixtureType(RubberType::SINGLES));
        $this->assertSame(2, RubberType::toLegacyFixtureType(RubberType::DOUBLES));
        $this->assertSame(3, RubberType::toLegacyFixtureType(RubberType::MIXED_DOUBLES));
        $this->assertSame(4, RubberType::toLegacyFixtureType(RubberType::REVERSE_SINGLES));
    }

    public function test_unsupported_canonical_values_are_rejected_for_legacy_mapping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported rubber code');

        RubberType::toLegacyFixtureType('unknown_canonical_code');
    }
}
