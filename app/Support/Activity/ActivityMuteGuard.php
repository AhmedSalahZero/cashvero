<?php

namespace App\Support\Activity;

/**
 * Scope guard returned by ActivityLogger::mute().
 *
 * Muting a queue job cannot be a bare "turn it off" call: workers are
 * long-lived processes that run many jobs in sequence, so a mute with
 * no matching unmute would silence the audit trail for every job that
 * followed in that worker — and an exception mid-job would make it
 * permanent.
 *
 * Holding the mute in an object ties it to a scope instead:
 *
 *     public function handle(): void
 *     {
 *         $mute = ActivityLogger::mute();
 *         …
 *     }
 *
 * PHP destroys `$mute` when handle() returns AND when it throws, so the
 * mute always unwinds exactly once.
 */
final class ActivityMuteGuard
{
    private bool $released = false;

    public function __construct()
    {
        ActivityLogger::incrementMute();
    }

    /**
     * Release early, before the scope ends.
     */
    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        ActivityLogger::decrementMute();
    }

    public function __destruct()
    {
        $this->release();
    }
}
