<?php

namespace App\Domain\TeamDraw;

final class TeamEventFormatDefinitionValidator
{
    /**
     * @param  array<string,mixed>  $payload
     */
    public function validate(array $payload): void
    {
        $minRoster = (int) ($payload['min_roster_size'] ?? 0);
        $maxRoster = (int) ($payload['max_roster_size'] ?? 0);

        if ($minRoster < 1) {
            throw TeamEventFormatDefinitionValidationException::single(
                'min_roster_size',
                'Minimum roster size must be a positive integer.'
            );
        }

        if ($maxRoster < 1) {
            throw TeamEventFormatDefinitionValidationException::single(
                'max_roster_size',
                'Maximum roster size must be a positive integer.'
            );
        }

        if ($maxRoster > 12) {
            throw TeamEventFormatDefinitionValidationException::single(
                'max_roster_size',
                'Maximum roster size cannot exceed 12.'
            );
        }

        if ($minRoster > $maxRoster) {
            throw TeamEventFormatDefinitionValidationException::single(
                'min_roster_size',
                'Minimum roster size cannot exceed maximum roster size.'
            );
        }

        $rubbers = $payload['rubbers'] ?? [];

        if (!is_array($rubbers) || count($rubbers) === 0) {
            throw TeamEventFormatDefinitionValidationException::single(
                'rubbers',
                'At least one rubber definition is required.'
            );
        }

        $sequences = [];

        foreach ($rubbers as $index => $rubber) {
            $line = $index + 1;
            $type = (string) ($rubber['rubber_code'] ?? '');
            $count = (int) ($rubber['player_count_per_team'] ?? 0);
            $sequence = (int) ($rubber['sequence'] ?? 0);

            if (!in_array($type, RubberType::ALL, true)) {
                throw TeamEventFormatDefinitionValidationException::single(
                    "rubbers.{$index}.rubber_code",
                    "Unsupported rubber type at row {$line}: {$type}."
                );
            }

            if ($sequence < 1) {
                throw TeamEventFormatDefinitionValidationException::single(
                    "rubbers.{$index}.sequence",
                    "Rubber sequence must be positive at row {$line}."
                );
            }

            if (in_array($sequence, $sequences, true)) {
                throw TeamEventFormatDefinitionValidationException::single(
                    "rubbers.{$index}.sequence",
                    'Rubber sequence must be unique within a format.'
                );
            }
            $sequences[] = $sequence;

            $expectedCount = RubberType::expectedPlayerCountPerTeam($type);
            if ($count !== $expectedCount) {
                throw TeamEventFormatDefinitionValidationException::single(
                    "rubbers.{$index}.player_count_per_team",
                    "{$type} requires {$expectedCount} player(s) per team."
                );
            }

            foreach (['singles_position', 'reverse_from_position'] as $positionField) {
                if (array_key_exists($positionField, $rubber) && $rubber[$positionField] !== null) {
                    if ((int) $rubber[$positionField] < 1) {
                        throw TeamEventFormatDefinitionValidationException::single(
                            "rubbers.{$index}.{$positionField}",
                            "{$positionField} must be a positive integer."
                        );
                    }
                }
            }
        }
    }
}
