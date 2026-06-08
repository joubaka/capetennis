<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RegistrationPair
 *
 * Foundation model for the doubles pair lifecycle.
 *
 * Status values:
 *   pending_partner  — pair created, no partner yet
 *   invited          — invitation sent, awaiting acceptance
 *   active           — both players confirmed and paid
 *   incomplete       — one partner withdrew, replacement window open
 *   dissolved        — pair no longer active
 *
 * Payment model values:
 *   full   — player1 (owner) pays the full pair fee
 *   split  — each player pays their own share
 *
 * PHASE 1 — FOUNDATION ONLY.
 * No business logic, no observers, no events.
 * Not connected to any existing workflow.
 */
class RegistrationPair extends Model
{
    protected $table = 'registration_pairs';

    protected $fillable = [
        'registration_id',
        'category_event_id',
        'player1_cer_id',
        'player2_cer_id',
        'owner_user_id',
        'status',
        'invite_token',
        'invite_email',
        'invite_expires_at',
        'accepted_at',
        'payment_model',
    ];

    protected $casts = [
        'invite_expires_at' => 'datetime',
        'accepted_at'       => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Status constants
    // -------------------------------------------------------------------------

    public const STATUS_PENDING_PARTNER = 'pending_partner';
    public const STATUS_INVITED         = 'invited';
    public const STATUS_ACTIVE          = 'active';
    public const STATUS_INCOMPLETE      = 'incomplete';
    public const STATUS_DISSOLVED       = 'dissolved';

    // Payment model constants
    public const PAYMENT_FULL  = 'full';
    public const PAYMENT_SPLIT = 'split';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function registration()
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function categoryEvent()
    {
        return $this->belongsTo(CategoryEvent::class, 'category_event_id');
    }

    public function player1Cer()
    {
        return $this->belongsTo(CategoryEventRegistration::class, 'player1_cer_id');
    }

    public function player2Cer()
    {
        return $this->belongsTo(CategoryEventRegistration::class, 'player2_cer_id');
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
