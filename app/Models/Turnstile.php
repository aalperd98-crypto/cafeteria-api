<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turnstile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'current_qr_code',
        'qr_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'qr_expires_at' => 'datetime',
        ];
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasValidQrCode(): bool
    {
        return ! is_null($this->current_qr_code)
            && ! is_null($this->qr_expires_at)
            && $this->qr_expires_at->isFuture();
    }
}
