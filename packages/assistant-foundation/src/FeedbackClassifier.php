<?php

namespace SportStack\Assistant;

/** Fixed categories for review queues; this is not model training. */
final class FeedbackClassifier
{
    public static function classify(string $reason): string
    {
        $reason = mb_strtolower($reason);
        foreach ([
            'voice' => ['voice', 'audio', 'transcri', 'microphone'],
            'understanding' => ['understand', 'misunderstood', 'wrong draw', 'wrong date', 'wrong event'],
            'missing_capability' => ['unsupported', 'missing', 'cannot', 'capability'],
            'schedule_quality' => ['schedule', 'rest', 'court', 'waiting', 'duration', 'clash'],
        ] as $category => $terms) {
            foreach ($terms as $term) {
                if (str_contains($reason, $term)) {
                    return $category;
                }
            }
        }

        return 'other';
    }
}
