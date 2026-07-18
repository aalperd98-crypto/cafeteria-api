<?php

namespace App\Jobs;

use App\Models\Turnstile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OpenTurnstileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $turnstileId,
        public int $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $turnstile = Turnstile::find($this->turnstileId);
        $user = User::find($this->userId);

        if (! $turnstile || ! $user) {
            Log::warning("OpenTurnstileJob: turnstile {$this->turnstileId} or user {$this->userId} no longer exists.");

            return;
        }

        // Simulated hardware trigger. In production this would call out to the
        // physical turnstile controller (e.g. HTTP/serial/MQTT command).
        Log::info("Turnstile {$turnstile->id} opened for User {$user->id}.");
    }
}
