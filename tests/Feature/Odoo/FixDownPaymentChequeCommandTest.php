<?php

namespace Tests\Feature\Odoo;

use App\Console\Commands\FixDownPaymentChequeOdooEntriesCommand;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * odoo:fix-down-payment-cheques repairs entries that were posted to the
 * branch cash account instead of notes receivable — see the command's own
 * docblock and DownPaymentChequeRoutingTest for the accounting.
 *
 * It touches a client's live Odoo ledger, so what is pinned here is mostly
 * what it must NOT do: not act without --apply, not act on a record it
 * cannot positively identify, and not pick an Odoo identity on its own.
 */
class FixDownPaymentChequeCommandTest extends TestCase
{
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Pointed at the development database on purpose: these assertions are
        // about the real records. Restored in tearDown so the rest of the suite
        // is not left talking to it.
        $this->originalDatabase = config('database.connections.mysql.database');

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /** Every column the command is capable of writing, across both tables. */
    private function odooColumnFingerprint(): string
    {
        $parts = [];

        foreach (['money_received', 'money_payments'] as $table) {
            $rows = DB::table($table)
                ->orderBy('id')
                ->get(['id', 'odoo_id', 'odoo_move_id', 'journal_entry_id',
                    'account_bank_statement_line_id', 'odoo_reference',
                    'synced_with_odoo', 'odoo_error_message']);

            $parts[] = $table.':'.md5($rows->toJson());
        }

        return implode('|', $parts);
    }

    private function command(): FixDownPaymentChequeOdooEntriesCommand
    {
        return new FixDownPaymentChequeOdooEntriesCommand;
    }

    private function call_(FixDownPaymentChequeOdooEntriesCommand $command, string $method, array $args = [])
    {
        $ref = (new ReflectionClass($command))->getMethod($method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($command, $args);
    }

    /* ── it must not write anything without --apply ───────────────── */

    public function test_the_report_changes_nothing(): void
    {
        $before = $this->odooColumnFingerprint();

        $this->artisan('odoo:fix-down-payment-cheques')->assertSuccessful();

        $this->assertSame($before, $this->odooColumnFingerprint(),
            'Report-only mode must not write a single Odoo column.');
    }

    /** --apply still asks, and answering no must leave everything alone. */
    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $command = $this->command();
        $this->bootCommandOptions($command);

        [$repairable] = $this->call_($command, 'findAffected');

        $before = $this->odooColumnFingerprint();

        $run = $this->artisan('odoo:fix-down-payment-cheques --apply');

        // The confirmation is only reached when there is something to repair;
        // on a database with nothing affected the command stops earlier.
        if ($repairable !== []) {
            $run->expectsConfirmation('Proceed?', 'no');
        }

        $run->run();

        $this->assertSame($before, $this->odooColumnFingerprint(),
            'Nothing may be written unless the operator confirms.');
    }

    /* ── it must select exactly the affected rows ─────────────────── */

    /**
     * The selection is the whole safety story: a row picked up wrongly gets
     * a real journal entry deleted from a client's books.
     */
    public function test_it_selects_only_down_payment_cheques_of_customers_and_suppliers(): void
    {
        $command = $this->command();

        foreach ([MoneyReceived::class, MoneyPayment::class] as $class) {
            foreach ($class::cursor() as $model) {
                try {
                    $expected = $model->isChequeOrChequePayment()
                        && $model->isDownPayment()
                        && in_array($model->getPartnerType(), ['is_customer', 'is_supplier'], true);
                } catch (\Throwable $e) {
                    continue;
                }

                $this->assertSame(
                    $expected,
                    $this->call_($command, 'tookTheWrongPath', [$model]),
                    class_basename($class).'#'.$model->id.' is selected wrongly.'
                );
            }
        }
    }

    /**
     * The live data happens to contain no down-payment cheque whose partner
     * is neither a customer nor a supplier, so a selection widened to every
     * cheque would look identical against it. This walks the whole truth
     * table on stand-ins instead, so each condition is pinned on its own.
     */
    public function test_each_selection_condition_is_required(): void
    {
        $command = $this->command();

        $cases = [
            // [isCheque, isDownPayment, partnerType, must be selected]
            [true,  true,  'is_customer', true,  'a customer down-payment cheque is the bug'],
            [true,  true,  'is_supplier', true,  'a supplier down-payment cheque is the bug'],
            [true,  true,  'is_employee', false, 'these already took the cheque path and are correct'],
            [true,  true,  null,          false, 'no partner type means it already took the cheque path'],
            [true,  false, 'is_customer', false, 'an ordinary cheque has its own path and is correct'],
            [false, true,  'is_customer', false, 'cash is not affected by a cheque routing bug'],
            [false, false, 'is_customer', false, 'nothing about this record is affected'],
        ];

        foreach ($cases as [$isCheque, $isDownPayment, $partnerType, $expected, $why]) {
            $model = $this->stubMoney($isCheque, $isDownPayment, $partnerType);

            $this->assertSame(
                $expected,
                $this->call_($command, 'tookTheWrongPath', [$model]),
                sprintf(
                    'cheque=%s downPayment=%s partner=%s — %s',
                    var_export($isCheque, true),
                    var_export($isDownPayment, true),
                    var_export($partnerType, true),
                    $why
                )
            );
        }
    }

    /**
     * A stand-in rather than a real model: booting Eloquent just to answer
     * three predicates drags in observers and a database. That the real
     * models answer these three is covered by
     * test_it_selects_only_down_payment_cheques_of_customers_and_suppliers,
     * which runs against every row on file.
     */
    private function stubMoney(bool $isCheque, bool $isDownPayment, ?string $partnerType): object
    {
        return new class($isCheque, $isDownPayment, $partnerType)
        {
            public function __construct(private bool $cheque, private bool $downPayment, private ?string $partnerType) {}

            public function isChequeOrChequePayment(): bool
            {
                return $this->cheque;
            }

            public function isDownPayment(): bool
            {
                return $this->downPayment;
            }

            public function getPartnerType(): ?string
            {
                return $this->partnerType;
            }
        };
    }

    /** A non-cheque, or a non-down-payment, must never be selected. */
    public function test_ordinary_records_are_never_selected(): void
    {
        $command = $this->command();
        $checked = 0;

        foreach (MoneyReceived::where('type', '!=', 'cheque')->limit(200)->cursor() as $model) {
            $this->assertFalse($this->call_($command, 'tookTheWrongPath', [$model]),
                'MoneyReceived#'.$model->id.' is not a cheque and must be left alone.');
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No non-cheque rows were available to check.');
    }

    /* ── it must refuse what it cannot identify ───────────────────── */

    /**
     * synced_with_odoo defaults to 1, so it reads as synced on rows that
     * never talked to Odoo. Only journal_entry_id — written solely by the
     * raw journal-entry path — proves the wrong entry exists.
     */
    public function test_a_row_without_a_journal_entry_id_is_never_repairable(): void
    {
        $command = $this->command();
        $this->bootCommandOptions($command);

        [$repairable, $needsReview] = $this->call_($command, 'findAffected');

        foreach ($repairable as $entry) {
            $this->assertNotEmpty($entry['journalEntryId'],
                'A repairable record must carry the id of the entry to be deleted.');
        }

        foreach ($needsReview as $model) {
            $this->assertEmpty($model->journal_entry_id,
                'Only rows with no Odoo reference belong in the review list.');
        }

        $this->assertSame(
            [],
            array_intersect(
                array_map(fn ($e) => $this->label($e['model']), $repairable),
                array_map(fn ($m) => $this->label($m), $needsReview)
            ),
            'A record may appear in one list or the other, never both.'
        );
    }

    private function label($model): string
    {
        return class_basename($model).'#'.$model->id;
    }

    /**
     * The states that mean "I cannot see this entry" must both stop a
     * repair. Deleting on a guess is the one thing that cannot be undone.
     */
    public function test_it_refuses_to_repair_an_entry_it_cannot_confirm(): void
    {
        $ref = new ReflectionClass(FixDownPaymentChequeOdooEntriesCommand::class);
        $constants = $ref->getConstants();

        $this->assertArrayHasKey('STATE_MISSING', $constants);
        $this->assertArrayHasKey('STATE_UNKNOWN', $constants);

        $source = file_get_contents($ref->getFileName());
        $repairBody = substr($source, strpos($source, 'private function repair('));

        $this->assertMatchesRegularExpression(
            '/if \(\$state === self::STATE_MISSING \|\| \$state === self::STATE_UNKNOWN\) \{\s*throw new/',
            $repairBody,
            'repair() must abort before unlink() when the entry cannot be confirmed in Odoo.'
        );

        $this->assertLessThan(
            strpos($repairBody, '->unlink('),
            strpos($repairBody, 'STATE_MISSING'),
            'The confirmation has to happen BEFORE the delete, not after it.'
        );
    }

    /* ── it must not invent an Odoo identity ──────────────────────── */

    /**
     * Odoo credentials live on the user row, and the entries this posts are
     * accounting entries. It may use the person who entered the record, or
     * one named with --user — never "whoever happens to have credentials".
     */
    public function test_it_uses_the_records_own_user_when_none_is_given(): void
    {
        $command = $this->command();

        $model = MoneyReceived::whereNotNull('user_id')->first();

        if (! $model) {
            $this->markTestSkipped('No record with a user_id on file.');
        }

        $this->bootCommandOptions($command);

        $user = $this->call_($command, 'odooUserFor', [$model]);

        $this->assertNotNull($user, 'The person who entered the record is the default identity.');
        $this->assertSame((int) $model->user_id, (int) $user->id);
    }

    /** With no user on the record and no --user, it must resolve to nobody. */
    public function test_it_resolves_to_no_user_rather_than_guessing(): void
    {
        $command = $this->command();
        $this->bootCommandOptions($command);

        $orphan = new MoneyReceived;
        $orphan->id = 0;
        $orphan->user_id = null;

        $this->assertNull($this->call_($command, 'odooUserFor', [$orphan]),
            'Falling back to an arbitrary user would post accounting entries under an identity nobody chose.');
    }

    private function bootCommandOptions(FixDownPaymentChequeOdooEntriesCommand $command): void
    {
        $input = new \Symfony\Component\Console\Input\ArrayInput([], $command->getDefinition());
        $output = new \Symfony\Component\Console\Output\NullOutput;

        $ref = new ReflectionClass(\Illuminate\Console\Command::class);

        foreach (['input', 'output'] as $property) {
            if (! $ref->hasProperty($property)) {
                continue;
            }
            $p = $ref->getProperty($property);
            $p->setAccessible(true);
            $p->setValue($command, $property === 'input' ? $input : new \Illuminate\Console\OutputStyle($input, $output));
        }
    }
}
