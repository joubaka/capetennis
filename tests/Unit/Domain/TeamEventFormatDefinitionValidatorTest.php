<?php

namespace Tests\Unit\Domain;

use App\Domain\TeamDraw\RubberType;
use App\Domain\TeamDraw\TeamEventFormatDefinitionValidationException;
use App\Domain\TeamDraw\TeamEventFormatDefinitionValidator;
use Tests\TestCase;

class TeamEventFormatDefinitionValidatorTest extends TestCase
{
    private TeamEventFormatDefinitionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TeamEventFormatDefinitionValidator();
    }

    private function basePayload(array $rubbers): array
    {
        return [
            'name' => 'Phase 1 Test Format',
            'min_roster_size' => 1,
            'max_roster_size' => 12,
            'allow_player_reuse' => false,
            'rubbers' => $rubbers,
        ];
    }

    public function test_singles_requires_one_player_per_team(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('singles requires 1 player(s) per team.');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::SINGLES,
                'name' => 'Singles 1',
                'player_count_per_team' => 2,
            ],
        ]));
    }

    public function test_reverse_singles_requires_one_player_per_team(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reverse_singles requires 1 player(s) per team.');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::REVERSE_SINGLES,
                'name' => 'Reverse Singles 1',
                'player_count_per_team' => 2,
            ],
        ]));
    }

    public function test_doubles_requires_two_players_per_team(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doubles requires 2 player(s) per team.');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::DOUBLES,
                'name' => 'Doubles',
                'player_count_per_team' => 1,
            ],
        ]));
    }

    public function test_mixed_doubles_requires_two_players_per_team(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mixed_doubles requires 2 player(s) per team.');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::MIXED_DOUBLES,
                'name' => 'Mixed Doubles',
                'player_count_per_team' => 1,
            ],
        ]));
    }

    public function test_invalid_rubber_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported rubber type');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => 'unknown_type',
                'name' => 'Unknown',
                'player_count_per_team' => 1,
            ],
        ]));
    }

    public function test_rejects_max_roster_size_above_twelve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum roster size cannot exceed 12');

        $payload = $this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::SINGLES,
                'name' => 'Singles 1',
                'player_count_per_team' => 1,
            ],
        ]);
        $payload['max_roster_size'] = 13;

        $this->validator->validate($payload);
    }

    public function test_rejects_minimum_roster_greater_than_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum roster size cannot exceed maximum roster size');

        $payload = $this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::SINGLES,
                'name' => 'Singles 1',
                'player_count_per_team' => 1,
            ],
        ]);
        $payload['min_roster_size'] = 6;
        $payload['max_roster_size'] = 4;

        $this->validator->validate($payload);
    }

    public function test_rejects_duplicate_rubber_sequences_in_one_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rubber sequence must be unique within a format');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::SINGLES,
                'name' => 'Singles 1',
                'player_count_per_team' => 1,
            ],
            [
                'sequence' => 1,
                'rubber_code' => RubberType::DOUBLES,
                'name' => 'Doubles',
                'player_count_per_team' => 2,
            ],
        ]));
    }

    public function test_rejects_non_positive_position_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a positive integer');

        $this->validator->validate($this->basePayload([
            [
                'sequence' => 1,
                'rubber_code' => RubberType::SINGLES,
                'name' => 'Singles 1',
                'player_count_per_team' => 1,
                'singles_position' => 0,
            ],
        ]));
    }

    public function test_rejects_zero_or_negative_sequence_values(): void
    {
        foreach ([0, -1] as $sequence) {
            try {
                $this->validator->validate($this->basePayload([
                    [
                        'sequence' => $sequence,
                        'rubber_code' => RubberType::SINGLES,
                        'name' => 'Singles 1',
                        'player_count_per_team' => 1,
                    ],
                ]));
                $this->fail('Expected sequence validation failure was not thrown.');
            } catch (TeamEventFormatDefinitionValidationException $e) {
                $this->assertArrayHasKey('rubbers.0.sequence', $e->errors());
            }
        }
    }

    public function test_rejects_non_positive_minimum_or_maximum_roster_size(): void
    {
        try {
            $payload = $this->basePayload([
                [
                    'sequence' => 1,
                    'rubber_code' => RubberType::SINGLES,
                    'name' => 'Singles 1',
                    'player_count_per_team' => 1,
                ],
            ]);
            $payload['min_roster_size'] = 0;
            $this->validator->validate($payload);
            $this->fail('Expected min_roster_size validation failure was not thrown.');
        } catch (TeamEventFormatDefinitionValidationException $e) {
            $this->assertArrayHasKey('min_roster_size', $e->errors());
        }

        try {
            $payload = $this->basePayload([
                [
                    'sequence' => 1,
                    'rubber_code' => RubberType::SINGLES,
                    'name' => 'Singles 1',
                    'player_count_per_team' => 1,
                ],
            ]);
            $payload['max_roster_size'] = 0;
            $this->validator->validate($payload);
            $this->fail('Expected max_roster_size validation failure was not thrown.');
        } catch (TeamEventFormatDefinitionValidationException $e) {
            $this->assertArrayHasKey('max_roster_size', $e->errors());
        }
    }
}
