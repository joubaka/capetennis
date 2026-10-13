<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectionPreviewComment extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Preview comments are append-only.'));
        static::deleting(fn () => throw new \LogicException('Preview discussion history is retained.'));
    }
}
