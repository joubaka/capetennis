<?php

namespace App\Models;

use Bavix\Wallet\Traits\HasWallet;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use Notifiable, AuthenticationLoggable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
  protected $fillable = [
    'userName',
    'userSurname',
    'email',
    'cell_nr',
    'userType',
    'image',
    'wallet_id',
    'priviledge',
  ];



  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',

    ];

    function events()
    {
        return $this->hasMany(Event::class, 'admin', 'id');
    }
    function orders()
    {
        return $this->hasMany(Order::class, 'id', 'user_id');
    }


    public function is_admin($event_id)
    {
        return $this->hasMany(EventAdmin::class, 'user_id', 'id')->where('event_id', $event_id)->get();
    }
    public function is_convenor($event_id)
    {
        return $this->hasMany(EventConvenor::class, 'user_id', 'id')->where('event_id', $event_id)->get();
    }
  public function players()
  {
    return $this->belongsToMany(Player::class, 'user_players')
      ->withPivot('id')
      ->withTimestamps();
  }

  public function clothingOrders()
    {
        return $this->hasMany(ClothingOrder::class, 'user_id', 'id');
    }
    public function wallet()
    {
        return $this->morphOne(\App\Models\Wallet::class, 'payable');
    }
  public function is_event_admin($event_id): bool
  {
    return $this->hasMany(EventAdmin::class, 'user_id', 'id')
      ->where('event_id', $event_id)
      ->exists();
  }

}
