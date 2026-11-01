<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SportStack\Assistant\DurationEstimator;
use SportStack\Assistant\DurationEvaluation;
use SportStack\Assistant\FeedbackClassifier;
use SportStack\Assistant\ProposalFingerprint;

require_once __DIR__.'/../../packages/assistant-foundation/autoload.php';

class AssistantFoundationTest extends TestCase
{
    public function test_duration_evaluation_holds_out_whole_later_events_without_using_their_outcomes(): void
    {
        $rows = [];
        foreach ([1, 2, 3] as $event) {
            foreach (range(1, 20) as $_) $rows[] = ['event_id' => $event, 'event_date' => '2026-10-0'.$event,
                'verified' => true, 'format' => 'one_set', 'age_group' => 'u14', 'actual_minutes' => $event === 3 ? 80 : 40];
        }
        $result = (new DurationEvaluation)->evaluate($rows);
        $this->assertSame(2, $result['training_events']);
        $this->assertSame(1, $result['held_out_events']);
        $this->assertSame(20, $result['evaluated_matches']);
        $this->assertSame(40.0, $result['mae_minutes']);
        $this->assertSame(20.0, $result['default_mae_minutes']);
        $this->assertFalse($result['automatically_enabled']);
        $this->assertSame('insufficient_events', (new DurationEvaluation)->evaluate([])['status']);
    }

    public function test_fingerprint_normalizes_keys_but_preserves_semantic_list_order(): void
    {
        $this->assertSame(ProposalFingerprint::make(['actor' => 1, 'options' => ['b' => 2, 'a' => 1]]), ProposalFingerprint::make(['options' => ['a' => 1, 'b' => 2], 'actor' => 1]));
        $this->assertNotSame(ProposalFingerprint::make(['draws' => [1, 2]]), ProposalFingerprint::make(['draws' => [2, 1]]));
        $this->assertNotSame(ProposalFingerprint::make(['event' => 1]), ProposalFingerprint::make(['event' => 2]));
    }

    public function test_feedback_is_categorized_without_copying_private_text_into_labels(): void
    {
        $this->assertSame('voice', FeedbackClassifier::classify('The transcription was incorrect'));
        $this->assertSame('understanding', FeedbackClassifier::classify('Wrong draw'));
        $this->assertSame('other', FeedbackClassifier::classify('A private comment'));
    }

    public function test_duration_estimation_requires_verified_sufficient_matching_observations(): void
    {
        $estimator = new DurationEstimator;
        $valid = ['verified' => true, 'format' => 'one_set', 'age_group' => 'u14', 'actual_minutes' => 42];
        $rows = array_fill(0, 19, $valid);
        $this->assertSame('configured_default', $estimator->estimate($rows, 'one_set', 'u14')['source']);
        $rows[] = $valid;
        $rows[] = $valid + ['walkover' => true];
        $rows[] = array_replace($valid, ['actual_minutes' => 300, 'interrupted' => true]);
        $rows[] = array_replace($valid, ['actual_minutes' => 300, 'verified' => false]);
        $rows[] = array_replace($valid, ['actual_minutes' => 300, 'format' => 'three_sets']);
        $estimate = $estimator->estimate($rows, 'one_set', 'u14');
        $this->assertSame(20, $estimate['samples']);
        $this->assertSame(45, $estimate['minutes']);
        $this->assertSame('verified_observations', $estimate['source']);
    }
}
