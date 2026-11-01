<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAssistantConversation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['expires_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(EventAssistantMessage::class, 'conversation_id');
    }
}
