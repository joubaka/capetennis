<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCommunicationBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'recipients' => 'array', 'issues' => 'array', 'approved_at' => 'datetime'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function logs()
    {
        return BulkEmailLog::query()->where(fn ($q) => $q->where('payload->event_communication_batch_id', $this->id)->orWhere('payload->origin_batch_id', $this->id));
    }
}
