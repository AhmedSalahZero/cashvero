<?php

namespace App\Services\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OdooSync
 * ------------------------------------------------------------------
 * Odoo is a remote XML-RPC service. Any call to it can fail in two ways:
 *
 *   - the server is unreachable / times out  → ripcord throws
 *     Ripcord_TransportException (see public/apis/ripcord_client.php)
 *   - the server answers with a faultCode    → AuthTrait::execute() throws
 *     \Exception($faultString)
 *
 * Both used to escape all the way up to the exception handler. That is what
 * made the money-payment / money-received / LG-issuance / cash-expense flows
 * dangerous: update() is implemented as "deleteRelations() → delete() →
 * store()" with no transaction, so an Odoo failure in the middle left the
 * record half deleted, or deleted the old record and created a new one that
 * was never linked to Odoo.
 *
 * This class fixes both halves of the problem, without queues/jobs:
 *
 *   1. transaction() runs the local database work inside a real DB
 *      transaction, so it either fully happens or fully rolls back.
 *   2. defer() registers an Odoo call instead of running it inline. The
 *      registered calls run in FIFO order *after* the transaction commits, so
 *      no XML-RPC round trip is ever made while a transaction is open, and a
 *      failing call cannot roll back local data.
 *   3. run() (used internally for every deferred call) swallows the failure
 *      and records it on the model as synced_with_odoo = 0 +
 *      odoo_error_message, plus a flash message for the user — the same
 *      pattern OdooPayment::createDownPayment already used.
 *
 * defer() outside of any transaction() falls back to running the call
 * immediately (still guarded), so call sites converted to defer() keep
 * working from code paths that have not been wrapped.
 */
class OdooSync
{
    /** @var list<array{0: callable, 1: Model|null, 2: string|null}> */
    private static array $deferred = [];

    private static int $depth = 0;

    /**
     * Runs $callback inside a database transaction and flushes every Odoo call
     * deferred during it once the transaction has committed. If the
     * transaction rolls back, the deferred Odoo calls are dropped: the local
     * data they were meant to mirror never made it to the database.
     *
     * Nested calls join the outer transaction (and the outer flush).
     *
     * @return mixed whatever $callback returns
     */
    public static function transaction(callable $callback)
    {
        $isOutermost = self::$depth === 0;
        self::$depth++;

        try {
            $result = DB::transaction($callback);
        } catch (Throwable $e) {
            self::$depth--;
            if ($isOutermost) {
                self::$deferred = [];
            }
            throw $e;
        }

        self::$depth--;
        if ($isOutermost) {
            self::flush();
        }

        return $result;
    }

    /**
     * Registers an Odoo call to run after the surrounding transaction commits.
     *
     * The callback must capture plain values (ids, amounts, dates) rather than
     * rely on rows that the transaction deletes — it runs after the delete has
     * been committed.
     *
     * @param  Model|null  $model  model to flag with the failure, when the call fails
     * @param  string|null  $context  short label used in the log / error message
     */
    public static function defer(callable $callback, ?Model $model = null, ?string $context = null): void
    {
        if (self::$depth === 0) {
            self::run($callback, $model, $context);

            return;
        }

        self::$deferred[] = [$callback, $model, $context];
    }

    /**
     * Runs an Odoo call right now, converting any failure into recorded state.
     *
     * @return mixed the callback result, or null when the call failed
     */
    public static function run(callable $callback, ?Model $model = null, ?string $context = null)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            self::recordFailure($e, $model, $context);

            return null;
        }
    }

    /**
     * Runs everything registered by defer(), in registration order. Each call
     * is independent: one failing call does not stop the ones after it.
     */
    public static function flush(): void
    {
        $deferred = self::$deferred;
        self::$deferred = [];

        foreach ($deferred as [$callback, $model, $context]) {
            self::run($callback, $model, $context);
        }
    }

    /**
     * Drops any pending Odoo call without running it.
     */
    public static function discard(): void
    {
        self::$deferred = [];
    }

    public static function hasPending(): bool
    {
        return self::$deferred !== [];
    }

    /**
     * Signatures of a connection failure, as opposed to Odoo answering with
     * a complaint about the data. Matched on the message because the
     * transport exception class lives in public/apis and is not always
     * loaded when this runs.
     */
    private const TRANSPORT_SIGNATURES = [
        'could not access',
        'failed to open stream',
        'connection refused',
        'connection timed out',
        'could not resolve host',
        'curl error',
        'operation timed out',
        'network is unreachable',
        'ssl',
    ];

    private static function recordFailure(Throwable $e, ?Model $model, ?string $context): void
    {
        $raw = ($context ? $context.': ' : '').$e->getMessage();

        try {
            // The log keeps everything — URL, PHP function, stack trace.
            // That is where this detail belongs, and the only place.
            Log::error('Odoo sync failed — '.$raw, [
                'model' => $model ? get_class($model).'#'.$model->getKey() : null,
                'exception' => $e,
            ]);
        } catch (Throwable $ignored) {
            // logging must never break the request
        }

        $shown = self::userFacingMessage($e, $context);

        self::flagModel($model, $shown);

        try {
            session()->flash('fail', $shown);
        } catch (Throwable $ignored) {
            // no session in console context
        }
    }

    /**
     * What the person in front of the screen is allowed to see.
     *
     * The raw text of a transport failure is
     * "Could not access https://<tenant>.odoo.com/xmlrpc/2/object —
     * file_get_contents(...): Failed to open stream: Connection refused".
     * That names the internal Odoo host, quotes a PHP function, and tells
     * the reader nothing they can act on. It went straight to a flash
     * message and onto the row, where the bug icon shows it.
     *
     * A complaint FROM Odoo ("missing required field") is different: it is
     * about the data and the operator can fix it, so it is kept — with any
     * URL still stripped out.
     */
    public static function userFacingMessage(Throwable $e, ?string $context = null): string
    {
        if (self::isTransportFailure($e)) {
            return __('Could not reach Odoo. Your record was saved and marked as not sent — it can be sent again once the connection is back.');
        }

        $detail = self::redact($e->getMessage());

        if ($detail === '') {
            return __('Odoo rejected this record. Please contact your administrator.');
        }

        return __('Error While Connecting With Odoo : ').($context ? $context.': ' : '').$detail;
    }

    private static function isTransportFailure(Throwable $e): bool
    {
        if (class_exists(\Ripcord_TransportException::class, false) && $e instanceof \Ripcord_TransportException) {
            return true;
        }

        $message = mb_strtolower($e->getMessage());

        foreach (self::TRANSPORT_SIGNATURES as $signature) {
            if (str_contains($message, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes what the reader must not see and cannot use: the server
     * address, and PHP\'s own stream-function noise.
     */
    private static function redact(string $message): string
    {
        // The host is the sensitive part — it identifies the tenant.
        $message = preg_replace('~https?://\S+~i', '[server]', $message) ?? $message;

        // "file_get_contents(...): " and friends.
        $message = preg_replace('~\b(file_get_contents|fopen|fsockopen|stream_socket_client|curl_exec)\s*\([^)]*\)\s*:?\s*~i', '', $message) ?? $message;

        return trim(Str::limit(trim($message, " \t\n\r\0\x0B-—:"), 300));
    }

    /**
     * Writes the failure straight to the row so it survives whatever the
     * caller does with the model afterwards. Skipped when the model was
     * deleted (update/destroy flows) or has no sync columns.
     */
    private static function flagModel(?Model $model, string $message): void
    {
        if (! $model || ! $model->exists || ! $model->getKey()) {
            return;
        }

        try {
            $values = [
                'synced_with_odoo' => 0,
                'odoo_error_message' => Str::limit($message, 1000),
            ];

            DB::connection($model->getConnectionName())
                ->table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update($values);

            foreach ($values as $column => $value) {
                $model->setAttribute($column, $value);
            }
        } catch (Throwable $ignored) {
            // the row may not carry the sync columns; the log entry above is enough
        }
    }
}
