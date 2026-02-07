<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
  protected $fillable = ['payable_type', 'payable_id'];

  public function payable()
  {
    return $this->morphTo();
  }

  public function transactions()
  {
    return $this->hasMany(WalletTransaction::class);
  }

  public function getBalanceAttribute(): float
  {
    return (float) $this->transactions()
      ->selectRaw("
            COALESCE(SUM(
              CASE WHEN type = 'credit'
              THEN amount
              ELSE -amount END
            ), 0) AS balance
        ")
      ->value('balance');
  }

}

