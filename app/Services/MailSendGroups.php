<?php

namespace App\Services;

use App\Models\EventCommunicationBatch;
use Illuminate\Database\Eloquent\Builder;

/** All aggregation and recipient lookup starts from the caller's authorized, filtered query. */
class MailSendGroups
{
    public function expression(Builder $query): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $json = fn ($key) => 'NULLIF(NULLIF('.$grammar->wrap('payload->'.$key).", 'null'), '')";
        $concat = fn (array $parts) => $query->getConnection()->getDriverName() === 'sqlite'
            ? '('.implode(' || ', $parts).')' : 'CONCAT('.implode(', ', $parts).')';
        $batch = $json('event_communication_batch_id');
        $preview = $json('preview_id');
        $subject = 'COALESCE('.$json('subject').', '.$json('rendered_subject').', '.$json('title').", '')";
        $legacy = $concat(["'legacy:'", "COALESCE(mail_type, '')", "':'", $subject, "':'", "DATE(created_at)", "':'", 'COALESCE('.$json('created_by').", '')"]);
        $relatedBatch = $query->getConnection()->getPdo()->quote(EventCommunicationBatch::class);

        return "CASE WHEN $batch IS NOT NULL THEN ".$concat(["'batch:'", $batch]).
            " WHEN $preview IS NOT NULL THEN ".$concat(["'preview:'", $preview]).
            " WHEN related_type = $relatedBatch THEN ".$concat(["'batch:'", 'related_id']).
            " ELSE $legacy END";
    }

    public function paginate(Builder $authorized)
    {
        $expression = $this->expression($authorized);
        $grammar = $authorized->getQuery()->getGrammar();
        $json = fn ($key) => 'NULLIF(NULLIF('.$grammar->wrap('payload->'.$key).", 'null'), '')";
        $subject = 'COALESCE('.$json('subject').', '.$json('rendered_subject').', '.$json('title').", 'Subject not recorded')";

        return (clone $authorized)->reorder()->selectRaw("$expression AS send_key, MIN(id) AS representative_id, MAX(id) AS latest_id, MIN($subject) AS send_subject, MIN(mail_type) AS send_type, MIN(created_at) AS first_recorded, MAX(created_at) AS last_recorded, COUNT(*) AS recipient_count, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count, SUM(CASE WHEN status IN ('queued', 'sending') THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped_count, SUM(CASE WHEN status = 'acceptance_unknown' THEN 1 ELSE 0 END) AS uncertain_count")
            ->groupByRaw($expression)->orderByDesc('latest_id')->paginate(15, ['*'], 'history_sends_page')->withQueryString();
    }

    public function recipients(Builder $authorized, int $representative)
    {
        $expression = $this->expression($authorized);
        $sample = (clone $authorized)->reorder()->selectRaw("id, $expression AS send_key")->whereKey($representative)->firstOrFail();

        return (clone $authorized)->whereRaw("($expression) = ?", [$sample->send_key])->latest('id')->paginate(25, ['*'], 'send_recipients_page')->withQueryString();
    }
}
