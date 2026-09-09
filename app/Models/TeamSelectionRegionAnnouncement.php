<?php

namespace App\Models;

use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamSelectionRegionAnnouncement extends Model
{
    use SoftDeletes;

    protected $fillable = ['event_id', 'event_region_id', 'region_id', 'created_by', 'title', 'message', 'emailed_at'];
    protected $casts = ['emailed_at' => 'datetime'];

    public function event() { return $this->belongsTo(Event::class); }
    public function eventRegion() { return $this->belongsTo(EventRegion::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function emailLogs()
    {
        return $this->hasMany(BulkEmailLog::class, 'related_id')
            ->where('related_type', self::class);
    }

    public function setMessageAttribute(?string $value): void
    {
        $this->attributes['message'] = app(RichTextSanitizer::class)->sanitize($value);
    }

    public function getMessageAttribute(?string $value): ?string
    {
        return app(RichTextSanitizer::class)->sanitize($value);
    }
}
