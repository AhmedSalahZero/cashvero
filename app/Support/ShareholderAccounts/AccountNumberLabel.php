<?php

namespace App\Support\ShareholderAccounts;

use App\Models\FinancialInstitutionAccount;

/**
 * Display-only "12345 — Ahmed" label for a current-account number that
 * belongs to a shareholder. The stored value stays the raw number
 * (decision D7, docs/shareholder-accounts.md).
 *
 * Lookups are cached for the rest of the request so an index page that
 * maps fifty rows does not fire fifty identical queries.
 */
class AccountNumberLabel
{
    /** @var array<string, string|null> */
    private static array $nameCache = [];

    public static function format(?string $accountNumber, ?string $shareholderName): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return $accountNumber;
        }

        $name = trim((string) $shareholderName);
        if ($name === '') {
            return $accountNumber;
        }

        return $accountNumber.' — '.$name;
    }

    /**
     * Label a current-account number (company + bank + number).
     * Company-owned accounts come back unchanged.
     */
    public static function forCurrentAccount(int $companyId, $financialInstitutionId, ?string $accountNumber): ?string
    {
        return self::format(
            $accountNumber,
            self::shareholderNameForCurrentAccount($companyId, $financialInstitutionId, $accountNumber)
        );
    }

    /**
     * Label an instrument that carries HasShareholderOwnership itself
     * (current account, TD, CD, MTL).
     */
    public static function forOwnedInstrument($model): ?string
    {
        if (! $model || ! method_exists($model, 'getAccountNumber')) {
            return null;
        }

        $name = method_exists($model, 'getShareholderName')
            ? $model->getShareholderName()
            : null;

        return self::format($model->getAccountNumber(), $name);
    }

    public static function shareholderNameForCurrentAccount(int $companyId, $financialInstitutionId, ?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '' || ! $financialInstitutionId) {
            return null;
        }

        $cacheKey = $companyId.'|'.$financialInstitutionId.'|'.$accountNumber;
        if (array_key_exists($cacheKey, self::$nameCache)) {
            return self::$nameCache[$cacheKey];
        }

        $account = FinancialInstitutionAccount::query()
            ->with('shareholderPartner')
            ->where('company_id', $companyId)
            ->where('financial_institution_id', $financialInstitutionId)
            ->where('account_number', $accountNumber)
            ->first();

        $name = $account ? $account->getShareholderName() : null;
        self::$nameCache[$cacheKey] = $name;

        return $name;
    }

    /**
     * Replace shareholder account numbers inside already-stored comment
     * text (e.g. "From ADCB Account No 99999") with the display label.
     * Does not rewrite the database; display / Excel only.
     */
    public static function decorateText(int $companyId, ?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return self::decorateTextWithMap($text, self::shareholderAccountNumbersForCompany($companyId));
    }

    /**
     * @param  array<string, string>  $numbersToNames
     */
    public static function decorateTextWithMap(?string $text, array $numbersToNames): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        foreach ($numbersToNames as $accountNumber => $shareholderName) {
            $accountNumber = (string) $accountNumber;
            if ($accountNumber === '' || str_contains($text, $accountNumber.' — ')) {
                continue;
            }

            $replaced = preg_replace(
                '/(?<![0-9A-Za-z])'.preg_quote($accountNumber, '/').'(?![0-9A-Za-z])/',
                self::format($accountNumber, $shareholderName),
                $text
            );

            if (is_string($replaced)) {
                $text = $replaced;
            }
        }

        return $text;
    }

    /**
     * @return array<string, string> account_number => shareholder name, longest first
     */
    public static function shareholderAccountNumbersForCompany(int $companyId): array
    {
        $request = app()->bound('request') ? request() : null;
        $attrKey = 'shareholder_account_number_labels_'.$companyId;
        if ($request && $request->attributes->has($attrKey)) {
            return $request->attributes->get($attrKey);
        }

        $map = [];
        $accounts = FinancialInstitutionAccount::query()
            ->with('shareholderPartner')
            ->where('company_id', $companyId)
            ->where('is_shareholder_account', 1)
            ->whereNotNull('shareholder_partner_id')
            ->get(['account_number', 'shareholder_partner_id', 'is_shareholder_account']);

        foreach ($accounts as $account) {
            $name = $account->getShareholderName();
            $number = (string) $account->getAccountNumber();
            if ($name && $number !== '') {
                $map[$number] = $name;
            }
        }

        uksort($map, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        if ($request) {
            $request->attributes->set($attrKey, $map);
        }

        return $map;
    }

    public static function flushRequestCache(): void
    {
        self::$nameCache = [];
        if (app()->bound('request')) {
            foreach (array_keys(request()->attributes->all()) as $key) {
                if (str_starts_with((string) $key, 'shareholder_account_number_labels_')) {
                    request()->attributes->remove($key);
                }
            }
        }
    }
}
