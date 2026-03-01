<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'total_taps',
        'coin_balance',
        'last_tap_at',
        'tap_window_started_at',
        'tap_window_count',
        'last_client_seq',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_tap_at' => 'datetime',
            'tap_window_started_at' => 'datetime',
        ];
    }

    public function tapEvents(): HasMany
    {
        return $this->hasMany(TapEvent::class);
    }
}
