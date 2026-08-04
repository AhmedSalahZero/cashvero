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

    private static function recordFailure(Throwable $e, ?Model $model, ?string $context): void
    {
        $message = ($context ? $context.': ' : '').$e->getMessage();

        try {
            Log::error('Odoo sync failed — '.$message, [
                'model' => $model ? get_class($model).'#'.$model->getKey() : null,
                'exception' => $e,
            ]);
        } catch (Throwable $ignored) {
            // logging must never break the request
        }

        self::flagModel($model, $message);

        try {
            session()->put('fail', __('Error While Connecting With Odoo : ').$message);
        } catch (Throwable $ignored) {
            // no session in console context
        }
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
