<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\User;
use App\Services\Api\MoneyPaymentOdooService;
use App\Services\Api\OdooPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * FixDownPaymentChequeOdooEntriesCommand
 * ------------------------------------------------------------------
 * Repairs the Odoo entries left behind by the down-payment cheque bug.
 *
 * THE BUG
 * createNonCustomerOrSupplierOdooExpense() only sent a cheque through
 * createDownPayment() when the partner was NOT a customer or supplier.
 * A customer's (or supplier's) down-payment cheque therefore fell through
 * to the raw journal entry below it, which debits the BRANCH'S CASH
 * account instead of Notes Receivable:
 *
 *     receive:  Dr <branch cash>        1,000,000
 *               Cr customer receivable  1,000,000
 *
 * The later collection credits the cheques-receivable account, which is a
 * DIFFERENT account, so the cash account is never relieved:
 *
 *     collect:  Dr bank                 1,000,000
 *               Cr notes receivable     1,000,000
 *
 * Net effect in Odoo: the safe still holds the money AND the bank holds it
 * too — the same cheque counted twice.
 *
 * The routing itself is fixed in HasNonCustomerOrSupplier, so no NEW record
 * can land this way. This command is only for the ones already written.
 *
 * WHICH ROWS
 * A down payment, paid/received by cheque, whose partner IS a customer or
 * supplier — exactly the combination the old predicate excluded — AND which
 * carries a journal_entry_id, because that column is written only by the raw
 * journal-entry path. A row without it never reached Odoo through that path
 * and must not be touched; it is listed separately for a human to look at.
 *
 * synced_with_odoo is NOT evidence of anything here: the column defaults to
 * 1, so it reads as synced on rows that never talked to Odoo at all.
 *
 * WHAT IT DOES
 * Per record: deletes the wrong journal entry in Odoo through the app's own
 * MoneyPaymentOdooService::unlink() — the same atomic call the app makes when
 * a user deletes one of these records, which restores the entry's state in
 * Odoo if the delete fails — then clears the local Odoo references and
 * re-creates the payment through OdooPayment::createDownPayment(), which now
 * puts it on the cheque payment method and therefore on Notes Receivable.
 *
 * Everything createDownPayment() needs is checked BEFORE the old entry is
 * deleted, so the usual causes of failure (no payment method line on the
 * journal, unmapped currency, date outside the integration window) stop the
 * record before anything has been removed.
 *
 * If Odoo refuses to delete the entry — the usual reason is a locked
 * accounting period — the record is reported as failed and left exactly as
 * it was. Unlocking the period, or posting a reversing entry by hand, is an
 * accountant's decision, not this command's.
 *
 * SAFETY
 *   - Reports only, unless --apply is passed.
 *   - --apply additionally requires an interactive confirmation.
 *   - Never touches a record it cannot positively identify.
 *   - Each record is independent; one failure does not stop the rest.
 *
 * USAGE
 *     php artisan odoo:fix-down-payment-cheques                 # report
 *     php artisan odoo:fix-down-payment-cheques --company=92    # narrow
 *     php artisan odoo:fix-down-payment-cheques --apply         # repair
 */
class FixDownPaymentChequeOdooEntriesCommand extends Command
{
    protected $signature = 'odoo:fix-down-payment-cheques
                            {--company= : limit to this company id}
                            {--model= : MoneyReceived or MoneyPayment}
                            {--id= : a single record id, together with --model}
                            {--user= : the user whose Odoo credentials to use (defaults to the one who entered each record)}
                            {--apply : actually repair (the default is report only)}';

    /** The id is stored locally but resolves to nothing in Odoo. */
    private const STATE_MISSING = 'not in odoo';

    /** Odoo could not be reached or refused the read. */
    private const STATE_UNKNOWN = 'unknown';

    /** No usable Odoo credentials for the record — see --user. */
    private const STATE_NO_CREDENTIALS = 'no credentials';

    private ?User $resolvedUser = null;

    protected $description = 'Reports (and with --apply repairs) Odoo entries where a down-payment cheque was posted to the cash account instead of notes receivable';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'MODE: repair' : 'MODE: report only (pass --apply to repair)');
        $this->newLine();

        [$repairable, $needsReview] = $this->findAffected();

        if ($repairable === [] && $needsReview === []) {
            $this->info('Nothing to repair — no down-payment cheque took the cash-account path.');

            return self::SUCCESS;
        }

        if ($repairable !== []) {
            $this->warn(count($repairable).' record(s) were posted to the wrong Odoo account:');
            $this->table(
                ['record', 'company', 'amount', 'date', 'posted to', 'should be', 'odoo entry'],
                array_map(fn ($e) => [
                    $this->label($e['model']),
                    $e['model']->company_id,
                    number_format((float) $e['model']->getAmount(), 2),
                    $e['date'],
                    $e['wrongAccount'] ?? '?',
                    $e['correctAccount'] ?? '?',
                    $e['journalEntryId'].' ('.$e['odooState'].')',
                ], $repairable)
            );
        }

        $missing = array_filter($repairable, fn ($e) => $e['odooState'] === self::STATE_MISSING);

        if ($missing !== []) {
            $this->newLine();
            $this->error(count($missing).' of those no longer exist in the Odoo database this app is');
            $this->error('connected to. They will be SKIPPED. Check that you are pointed at the right');
            $this->error('Odoo database before reading anything into that.');
        }

        if ($needsReview !== []) {
            $this->newLine();
            $this->warn(count($needsReview).' record(s) match the bug but carry no Odoo journal entry id.');
            $this->line('  Nothing will be done to them. Check in Odoo whether an entry exists for each');
            $this->line('  before deciding — this command will not guess which entry belongs to which row.');
            $this->table(
                ['record', 'company', 'amount', 'date'],
                array_map(fn ($m) => [
                    $this->label($m),
                    $m->company_id,
                    number_format((float) $m->getAmount(), 2),
                    $m->getDate(),
                ], $needsReview)
            );
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Nothing was changed. Re-run with --apply to repair the listed records.');

            return self::SUCCESS;
        }

        if ($repairable === []) {
            $this->error('There is nothing that can be repaired automatically.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('For each record this deletes the wrong journal entry in Odoo and re-creates the');
        $this->warn('payment on the cheque payment method. Take an Odoo backup first.');

        if (! $this->confirm('Proceed?', false)) {
            $this->info('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        $repaired = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($repairable as $entry) {
            $label = $this->label($entry['model']);

            if (in_array($entry['odooState'], [self::STATE_MISSING, self::STATE_NO_CREDENTIALS], true)) {
                $this->line("  {$label} skipped — {$entry['odooState']}.");
                $skipped++;

                continue;
            }

            try {
                $this->repair($entry);
                $this->info("  {$label} repaired.");
                $repaired++;
            } catch (\Throwable $e) {
                $this->error("  {$label} FAILED: ".$e->getMessage());
                Log::error('Down-payment cheque repair failed', [
                    'record' => $label,
                    'exception' => $e,
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Repaired: {$repaired}   Failed: {$failed}   Skipped: {$skipped}   Left for review: ".count($needsReview));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function label($model): string
    {
        return class_basename($model).'#'.$model->id;
    }

    /**
     * Odoo is talked to as a user, not as the application — the credentials
     * live on the user row. A command has no logged-in user, so one has to be
     * named: --user, or failing that the person who entered the record, whose
     * credentials wrote the wrong entry in the first place.
     *
     * No company-wide fallback on purpose. Posting accounting entries under
     * an identity nobody chose is not something to do quietly.
     */
    private function odooUserFor($model): ?User
    {
        if ($this->option('user')) {
            $this->resolvedUser ??= User::find($this->option('user'));

            return $this->resolvedUser;
        }

        return $model->user_id ? User::find($model->user_id) : null;
    }

    private function credentialsFor($model): ?User
    {
        $user = $this->odooUserFor($model);
        $company = $model->company;

        if (! $company || ! $user || ! $company->hasOdooIntegrationCredentials($user)) {
            return null;
        }

        return $user;
    }

    /**
     * Splits the affected rows into the ones that can be repaired and the
     * ones that carry no Odoo reference and therefore must not be touched.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, mixed>}
     */
    private function findAffected(): array
    {
        $repairable = [];
        $needsReview = [];

        $classes = [MoneyReceived::class => 'is_customer', MoneyPayment::class => 'is_supplier'];

        foreach ($classes as $class => $ownType) {
            if ($this->option('model') && class_basename($class) !== $this->option('model')) {
                continue;
            }

            $query = $class::query();

            if ($this->option('company')) {
                $query->where('company_id', $this->option('company'));
            }
            if ($this->option('id')) {
                $query->where('id', $this->option('id'));
            }

            foreach ($query->cursor() as $model) {
                if (! $this->tookTheWrongPath($model)) {
                    continue;
                }

                if (! $model->journal_entry_id) {
                    $needsReview[] = $model;

                    continue;
                }

                $repairable[] = [
                    'model' => $model,
                    'date' => $model->getDate(),
                    'journalEntryId' => (int) $model->journal_entry_id,
                    'wrongAccount' => $this->cashAccountFor($model),
                    'correctAccount' => $this->chequesAccountFor($model),
                    'odooState' => $this->odooStateOf($model, (int) $model->journal_entry_id),
                ];
            }
        }

        return [$repairable, $needsReview];
    }

    /**
     * The old predicate routed a cheque correctly only when the partner was
     * neither a customer nor a supplier, so those two partner types are the
     * ones that went the wrong way. Only a down payment reaches the method
     * at all.
     */
    private function tookTheWrongPath($model): bool
    {
        try {
            return $model->isChequeOrChequePayment()
                && $model->isDownPayment()
                && in_array($model->getPartnerType(), ['is_customer', 'is_supplier'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reads the entry's state straight from Odoo. A stored journal_entry_id
     * is not proof the entry is there — a database that was copied or reset,
     * or an app pointed at a different Odoo, leaves ids behind that resolve
     * to nothing. Deleting is not attempted on anything this cannot see.
     */
    private function odooStateOf($model, int $journalEntryId): string
    {
        $user = $this->credentialsFor($model);

        if (! $user) {
            return self::STATE_NO_CREDENTIALS;
        }

        try {
            $entry = (new MoneyPaymentOdooService($model->company, $user))
                ->execute('account.move', 'read', [[$journalEntryId], ['state']]);

            if (! is_array($entry) || isset($entry['faultString'])) {
                return self::STATE_UNKNOWN;
            }

            return $entry === [] ? self::STATE_MISSING : (string) ($entry[0]['state'] ?? self::STATE_UNKNOWN);
        } catch (\Throwable $e) {
            Log::warning('Could not read the journal entry from Odoo', [
                'record' => $this->label($model),
                'journal_entry_id' => $journalEntryId,
                'exception' => $e,
            ]);

            return self::STATE_UNKNOWN;
        }
    }

    /** The branch cash / bank account the raw entry wrongly used. */
    private function cashAccountFor($model): ?int
    {
        try {
            return (int) $model->getChequeOdooId() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The account the collection clears, and where the cheque belongs. */
    private function chequesAccountFor($model): ?int
    {
        try {
            $setting = $model->company?->odooSetting;

            return $setting ? ((int) $setting->getChequesReceivableId() ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Deletes the wrong entry, then re-creates the payment through the fixed
     * path. Everything the re-create needs is verified first, so a record
     * that cannot be re-created is never left with its entry deleted.
     */
    private function repair(array $entry): void
    {
        $model = $entry['model'];
        $company = $model->company;
        $user = $this->credentialsFor($model);

        if (! $user) {
            throw new \RuntimeException('No Odoo credentials for this record — name one with --user.');
        }

        $this->assertCanBeRecreated($model, $company);

        // Re-read rather than trusting the state from the report: minutes may
        // have passed, and this is the last check before a delete.
        $state = $this->odooStateOf($model, $entry['journalEntryId']);

        if ($state === self::STATE_MISSING || $state === self::STATE_UNKNOWN) {
            throw new \RuntimeException("Journal entry {$entry['journalEntryId']} could not be confirmed in Odoo ({$state}); nothing was touched.");
        }

        (new MoneyPaymentOdooService($company, $user))->unlink($entry['journalEntryId']);

        $model->update([
            'journal_entry_id' => null,
            'account_bank_statement_line_id' => null,
            'odoo_reference' => null,
            'odoo_id' => null,
            'odoo_move_id' => null,
            'synced_with_odoo' => false,
            'odoo_error_message' => null,
        ]);

        (new OdooPayment($company, $user))->createDownPayment($model->refresh());

        $model->refresh();

        if (! $model->odoo_id) {
            throw new \RuntimeException(
                'The wrong entry was removed but the corrected payment was NOT created: '
                .($model->odoo_error_message ?: 'no reason recorded').
                ' — this record now has nothing in Odoo and needs attention.'
            );
        }
    }

    /**
     * createDownPayment() swallows its own failures into odoo_error_message
     * rather than throwing, so its preconditions are checked here, while the
     * old entry is still in place and backing out costs nothing.
     */
    private function assertCanBeRecreated($model, $company): void
    {
        $date = $model->getReceivingOrPaymentMoneyDate();

        if (! $company->withinIntegrationDate($date)) {
            throw new \RuntimeException("Date {$date} is outside the company's Odoo integration window, so the payment cannot be re-created.");
        }

        if (! $model->partner || ! $model->partner->getOdooId()) {
            throw new \RuntimeException('The partner is not linked to Odoo.');
        }

        $paymentMethodLineId = $model->getPaymentMethodLineId();

        if (! is_numeric($paymentMethodLineId) || (int) $paymentMethodLineId <= 0) {
            throw new \RuntimeException('No Odoo payment method line is configured for the safe or account used, so the cheque cannot be posted to notes receivable.');
        }

        $currency = $model->getReceivingOrPaymentCurrency();

        if (! Currency::getOdooId($currency)) {
            throw new \RuntimeException("Currency {$currency} is not mapped to Odoo.");
        }
    }
}
