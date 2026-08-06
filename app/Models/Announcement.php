<?php

namespace App\Models;

use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'event_id',
    'title',
    'message',
  ];

  public function event()
  {
    return $this->belongsTo(Event::class);
  }
  public function getIsHiddenAttribute(): bool
  {
    return $this->trashed();
  }

  public function setMessageAttribute(?string $value): void
  {
    $this->attributes['message'] = app(RichTextSanitizer::class)->sanitize($value);
  }

  public function getMessageAttribute(?string $value): ?string
  {
    // Also protects legacy rows that pre-date input sanitization.
    return app(RichTextSanitizer::class)->sanitize($value);
  }

}
