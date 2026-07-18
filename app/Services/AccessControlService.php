<?php

namespace App\Services;

use App\Exceptions\EntryValidationException;
use App\Jobs\OpenTurnstileJob;
use App\Models\AccessLog;
use App\Models\Turnstile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessControlService
{
    /** Seconds a generated QR code remains valid before it must be refreshed. */
    private const QR_TTL_SECONDS = 120;

    /** Maximum total entries allowed across all users/turnstiles per day. */
    private const GLOBAL_DAILY_LIMIT = 100;

    /** Maximum entries allowed for a single user per day. */
    private const PER_USER_DAILY_LIMIT = 2;

    /**
     * Return the turnstile's current valid QR code, generating a fresh one
     * if none exists or the existing one has expired.
     */
    public function getOrRefreshQrCode(Turnstile $turnstile): Turnstile
    {
        return DB::transaction(function () use ($turnstile) {
            $turnstile = Turnstile::whereKey($turnstile->id)->lockForUpdate()->firstOrFail();

            if (! $turnstile->hasValidQrCode()) {
                $this->assignFreshQrCode($turnstile);
            }

            return $turnstile;
        });
    }

    /**
     * Validate and process a QR code scan for a user at a turnstile.
     *
     * @throws EntryValidationException
     */
    public function scanEntry(User $user, int $turnstileId, string $qrCode): AccessLog
    {
        return DB::transaction(function () use ($user, $turnstileId, $qrCode) {
            // Lock every turnstile row so concurrent scans across BOTH turnstiles
            // serialize against each other. With only two turnstiles this keeps
            // the global daily-limit check race-free without heavier locking.
            $turnstiles = Turnstile::orderBy('id')->lockForUpdate()->get();
            $turnstile = $turnstiles->firstWhere('id', $turnstileId);

            if (! $turnstile) {
                throw new EntryValidationException('Turnstile not found.', 404);
            }

            if (! $turnstile->isActive()) {
                throw EntryValidationException::turnstileInactive();
            }

            if ($turnstile->current_qr_code !== $qrCode || ! $turnstile->hasValidQrCode()) {
                throw EntryValidationException::invalidQrCode();
            }

            $globalCountToday = AccessLog::today()->count();
            if ($globalCountToday >= self::GLOBAL_DAILY_LIMIT) {
                throw EntryValidationException::globalLimitExceeded();
            }

            $userCountToday = AccessLog::today()->forUser($user->id)->count();
            if ($userCountToday >= self::PER_USER_DAILY_LIMIT) {
                throw EntryValidationException::perUserLimitExceeded();
            }

            $accessLog = AccessLog::create([
                'user_id' => $user->id,
                'turnstile_id' => $turnstile->id,
                'scanned_at' => now(),
            ]);

            // Single-use enforcement: invalidate the scanned code immediately,
            // then prepare the next code so the turnstile is ready for the next scan.
            $this->assignFreshQrCode($turnstile);

            OpenTurnstileJob::dispatch($turnstile->id, $user->id)->afterCommit();

            return $accessLog;
        });
    }

    private function assignFreshQrCode(Turnstile $turnstile): void
    {
        do {
            $code = Str::random(40);
        } while (Turnstile::where('current_qr_code', $code)->exists());

        $turnstile->update([
            'current_qr_code' => $code,
            'qr_expires_at' => now()->addSeconds(self::QR_TTL_SECONDS),
        ]);
    }
}
