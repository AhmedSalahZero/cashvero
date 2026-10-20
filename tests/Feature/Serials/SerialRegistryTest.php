<?php

namespace Tests\Feature\Serials;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models;
use App\Support\Serials\SerialRegistry;
use App\Traits\Models\HasSerial;
use App\Traits\Models\IsReviewable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * * كل نوع حركة بيتخزّن لازم يبقى ليه كود ، و إلا الحركة بتتحفظ من
 * * غير رقم مسلسل من غير ما حد ياخد باله .
 */
class SerialRegistryTest extends SerialTestCase
{
    /** @return array<string, array{0:class-string}> */
    public static function models(): array
    {
        return collect(array_keys(SerialRegistry::MODELS))
            ->mapWithKeys(fn (string $class) => [class_basename($class) => [$class]])
            ->all();
    }

    public function test_the_format_matches_odoo_style(): void
    {
        $this->assertSame('MR/CIS/2026/10/00001', SerialRegistry::format('MR/CIS', Carbon::parse('2026-10-05'), 1));
        $this->assertSame('LG/FIN/2026/01/01234', SerialRegistry::format('LG/FIN', Carbon::parse('2026-01-31'), 1234));
    }

    public function test_every_stored_type_has_a_code(): void
    {
        $stored = [
            Models\MoneyReceived::class => [
                Models\MoneyReceived::CASH_IN_SAFE, Models\MoneyReceived::CASH_IN_BANK,
                Models\MoneyReceived::INCOMING_TRANSFER, Models\MoneyReceived::CHEQUE,
            ],
            Models\MoneyPayment::class => Models\MoneyPayment::getAllTypes(),
            Models\CashExpense::class => Models\CashExpense::getAllTypes(),
            Models\MultipleCashExpense::class => [
                Models\MultipleCashExpense::CASH_PAYMENT, Models\MultipleCashExpense::PAYABLE_CHEQUE,
                Models\MultipleCashExpense::OUTGOING_TRANSFER,
            ],
            // getAllTypes() هنا ناقصها safe-to-safe ، فالقايمة مكتوبة صريحة
            Models\InternalMoneyTransfer::class => [
                Models\InternalMoneyTransfer::BANK_TO_BANK, Models\InternalMoneyTransfer::BANK_TO_SAFE,
                Models\InternalMoneyTransfer::SAFE_TO_BANK, Models\InternalMoneyTransfer::SAFE_TO_SAFE,
            ],
            Models\BuyOrSellCurrency::class => array_keys(Models\BuyOrSellCurrency::getAllTypes()),
            Models\LetterOfGuaranteeIssuance::class => array_keys(LgTypes::getAll()),
            Models\LetterOfCreditIssuance::class => array_keys(LcTypes::getAll()),
            Models\FactoringTransaction::class => [
                Models\FactoringTransaction::WITH_RECOURSE, Models\FactoringTransaction::WITHOUT_RECOURSE,
            ],
            Models\LcSettlementInternalMoneyTransfer::class => [
                Models\LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT,
            ],
        ];

        $this->assertEqualsCanonicalizing(array_keys(SerialRegistry::MODELS), array_keys($stored));

        foreach ($stored as $class => $types) {
            foreach ($types as $type) {
                $this->assertNotNull(SerialRegistry::prefixFor($class, $type), "{$class} type [{$type}] has no serial code");
            }
        }
    }

    public function test_codes_are_unique_within_a_module_and_modules_are_unique(): void
    {
        $modules = array_column(SerialRegistry::MODELS, 'module');
        $this->assertSame(count($modules), count(array_unique($modules)), 'Two models share a module code');

        foreach (SerialRegistry::MODELS as $class => $config) {
            $this->assertSame(count($config['codes']), count(array_unique($config['codes'])), "{$class} has a repeated type code");
        }
    }

    /** @dataProvider models */
    public function test_the_model_is_wired(string $class): void
    {
        $traits = class_uses_recursive($class);
        $this->assertContains(HasSerial::class, $traits);
        $this->assertContains(IsReviewable::class, $traits);

        $table = (new $class)->getTable();
        $schema = DB::connection()->getSchemaBuilder();
        $config = SerialRegistry::config($class);

        foreach (['serial', 'company_id', $config['type_column'], $config['date_column']] as $column) {
            $this->assertTrue($schema->hasColumn($table, $column), "{$table} is missing {$column}");
        }

        $index = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->where('Key_name', 'ser_'.substr(md5($table), 0, 16).'_uq');

        $this->assertSame(['company_id', 'serial'], $index->sortBy('Seq_in_index')->pluck('Column_name')->values()->all());
        $this->assertSame(0, (int) $index->first()->Non_unique);
    }
}
