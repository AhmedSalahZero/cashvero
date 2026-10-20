<?php

namespace App\Support\Serials;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models;
use Carbon\Carbon;

/**
 * SerialRegistry
 * ==================================================================
 * * الرقم المسلسل للحركات المالية — زي أودو :
 *
 *     MR/CIS/2026/10/00001
 *     │  │   │    │  └─ الترتيب جوه (الشركة + الموديول + النوع + السنة)
 *     │  │   │    └──── شهر تاريخ الحركة (للعرض بس ، مش بيصفّر العدّاد)
 *     │  │   └───────── سنة تاريخ الحركة (العدّاد بيبدأ من ١ كل سنة)
 *     │  └───────────── كود النوع
 *     └──────────────── كود الموديول
 *
 * * كل شركة ليها عدّاد مستقل : أول Cash In Safe في شركة ١٤٨ هو 00001
 * * و أول Cash In Safe في شركة تانية برضه 00001 .
 *
 * * تغيير كود هنا بيأثر على الحركات الجديدة بس — اللي اتسجّل قبل كده
 * * رقمه متخزّن في عمود serial و مش بيتغيّر .
 */
class SerialRegistry
{
    /**
     * class => [module, type column, date column, type value => code]
     *
     * ⚠️ عمود النوع بيتقري من الداتابيز مباشرة مش من getType() :
     * * LetterOfCreditIssuance::getType() بترجع 'lc-settlement' دايما
     */
    public const MODELS = [
        Models\MoneyReceived::class => [
            'module' => 'MR',
            'type_column' => 'type',
            'date_column' => 'receiving_date',
            'codes' => [
                Models\MoneyReceived::CASH_IN_SAFE => 'CIS',
                Models\MoneyReceived::CASH_IN_BANK => 'CIB',
                Models\MoneyReceived::INCOMING_TRANSFER => 'IT',
                Models\MoneyReceived::CHEQUE => 'CHQ',
            ],
        ],
        Models\MoneyPayment::class => [
            'module' => 'MP',
            'type_column' => 'type',
            'date_column' => 'delivery_date',
            'codes' => [
                Models\MoneyPayment::CASH_PAYMENT => 'CP',
                Models\MoneyPayment::PAYABLE_CHEQUE => 'PC',
                Models\MoneyPayment::OUTGOING_TRANSFER => 'OT',
                Models\MoneyPayment::LEASING => 'LP',
            ],
        ],
        Models\CashExpense::class => [
            'module' => 'CE',
            'type_column' => 'type',
            'date_column' => 'payment_date',
            'codes' => [
                Models\CashExpense::CASH_PAYMENT => 'CP',
                Models\CashExpense::PAYABLE_CHEQUE => 'PC',
                Models\CashExpense::OUTGOING_TRANSFER => 'OT',
            ],
        ],
        Models\MultipleCashExpense::class => [
            'module' => 'MCE',
            'type_column' => 'type',
            'date_column' => 'payment_date',
            'codes' => [
                Models\MultipleCashExpense::CASH_PAYMENT => 'CP',
                Models\MultipleCashExpense::PAYABLE_CHEQUE => 'PC',
                Models\MultipleCashExpense::OUTGOING_TRANSFER => 'OT',
            ],
        ],
        Models\InternalMoneyTransfer::class => [
            'module' => 'IMT',
            'type_column' => 'type',
            'date_column' => 'transfer_date',
            'codes' => [
                Models\InternalMoneyTransfer::BANK_TO_BANK => 'BTB',
                Models\InternalMoneyTransfer::BANK_TO_SAFE => 'BTS',
                Models\InternalMoneyTransfer::SAFE_TO_BANK => 'STB',
                Models\InternalMoneyTransfer::SAFE_TO_SAFE => 'STS',
            ],
        ],
        Models\BuyOrSellCurrency::class => [
            'module' => 'FX',
            'type_column' => 'type',
            'date_column' => 'transaction_date',
            'codes' => [
                Models\BuyOrSellCurrency::BANK_TO_BANK => 'BTB',
                Models\BuyOrSellCurrency::BANK_TO_SAFE => 'BTS',
                Models\BuyOrSellCurrency::SAFE_TO_BANK => 'STB',
                Models\BuyOrSellCurrency::SAFE_TO_SAFE => 'STS',
            ],
        ],
        Models\LetterOfGuaranteeIssuance::class => [
            'module' => 'LG',
            'type_column' => 'lg_type',
            'date_column' => 'issuance_date',
            'codes' => [
                LgTypes::BID_BOND => 'BB',
                LgTypes::FINAL_LGS => 'FIN',
                LgTypes::ADVANCED_PAYMENT_LGS => 'AP',
                LgTypes::PERFORMANCE_LG => 'PER',
            ],
        ],
        Models\LetterOfCreditIssuance::class => [
            'module' => 'LC',
            'type_column' => 'lc_type',
            'date_column' => 'issuance_date',
            'codes' => [
                LcTypes::SIGHT_LC => 'SLC',
                LcTypes::DEFERRED => 'DEF',
                LcTypes::CASH_AGAINST_DOCUMENT => 'CAD',
            ],
        ],
        Models\FactoringTransaction::class => [
            'module' => 'FAC',
            'type_column' => 'recourse_type',
            'date_column' => 'factoring_date',
            'codes' => [
                Models\FactoringTransaction::WITH_RECOURSE => 'WR',
                Models\FactoringTransaction::WITHOUT_RECOURSE => 'WOR',
            ],
        ],
        Models\LcSettlementInternalMoneyTransfer::class => [
            'module' => 'LCS',
            'type_column' => 'type',
            'date_column' => 'transfer_date',
            'codes' => [
                Models\LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT => 'BLC',
            ],
        ],
    ];

    public static function tracks(string $class): bool
    {
        return isset(self::MODELS[$class]);
    }

    /** @return array{module:string, type_column:string, date_column:string, codes:array<string,string>} */
    public static function config(string $class): array
    {
        return self::MODELS[$class];
    }

    /**
     * MR/CIS — أو null لو النوع مش معروف (الصف بيفضل من غير رقم و
     * الواجهة بتعرض #id بداله)
     */
    public static function prefixFor(string $class, mixed $type): ?string
    {
        if (! self::tracks($class)) {
            return null;
        }

        if ($type instanceof \BackedEnum) {
            $type = $type->value;
        }

        $config = self::config($class);
        $code = $config['codes'][(string) $type] ?? null;

        return $code === null ? null : $config['module'].'/'.$code;
    }

    /** MR/CIS + 2026-10-05 + 1 → MR/CIS/2026/10/00001 */
    public static function format(string $prefix, Carbon $date, int $number): string
    {
        return sprintf('%s/%04d/%02d/%05d', $prefix, $date->year, $date->month, $number);
    }

    /** @return array<int, string> */
    public static function tables(): array
    {
        return array_map(fn (string $class) => (new $class)->getTable(), array_keys(self::MODELS));
    }
}
