<?php

namespace App\Support\ShareholderAccounts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * The "All accounts / Company accounts / Shareholders accounts" selection,
 * plus the optional single-shareholder narrowing that appears underneath it.
 *
 * Decision D2: the default is Company accounts — never All — so that a page
 * loaded without an explicit choice shows official company figures.
 * Decision D3: per-owner narrowing is available from day one.
 * Decision D6: a user without the shareholder-accounts permission is pinned
 * to Company accounts; construct through forCompanyOnly() for that.
 *
 * See docs/shareholder-accounts.md.
 */
class AccountOwnerFilter
{
    public const ALL = 'all';

    public const COMPANY = 'company';

    public const SHAREHOLDERS = 'shareholders';

    public const OWNERS = [self::ALL, self::COMPANY, self::SHAREHOLDERS];

    /** The request key both the backend and the Vue pages use. */
    public const REQUEST_KEY = 'account_owner';

    public const SHAREHOLDER_REQUEST_KEY = 'shareholder_partner_id';

    private function __construct(
        public readonly string $owner,
        public readonly ?int $shareholderPartnerId
    ) {
    }

    public static function make(?string $owner, $shareholderPartnerId = null): self
    {
        $owner = in_array($owner, self::OWNERS, true) ? $owner : self::COMPANY;

        // A specific shareholder only means anything while viewing
        // shareholder accounts; anywhere else it is dropped rather than
        // silently narrowing a company or all-accounts view.
        $shareholderPartnerId = $owner === self::SHAREHOLDERS ? (int) $shareholderPartnerId : 0;

        return new self($owner, $shareholderPartnerId ?: null);
    }

    /**
     * Read the filter off a request. $canViewShareholderAccounts is the
     * permission gate — when false the request's choice is ignored entirely
     * rather than trusted, so a hand-crafted query string cannot reveal
     * owner data (D6).
     */
    public static function fromRequest(Request $request, bool $canViewShareholderAccounts = true): self
    {
        if (! $canViewShareholderAccounts) {
            return self::forCompanyOnly();
        }

        return self::make(
            $request->get(self::REQUEST_KEY),
            $request->get(self::SHAREHOLDER_REQUEST_KEY)
        );
    }

    public static function forCompanyOnly(): self
    {
        return new self(self::COMPANY, null);
    }

    public function isAll(): bool
    {
        return $this->owner === self::ALL;
    }

    public function isCompanyOnly(): bool
    {
        return $this->owner === self::COMPANY;
    }

    public function isShareholdersOnly(): bool
    {
        return $this->owner === self::SHAREHOLDERS;
    }

    /** True when this filter constrains nothing — used to skip work. */
    public function includesEverything(): bool
    {
        return $this->isAll();
    }

    /**
     * Constrain a raw query builder. $table is the table (or alias) the
     * ownership columns live on.
     */
    public function applyToQuery(QueryBuilder $query, string $table): QueryBuilder
    {
        if ($this->isAll()) {
            return $query;
        }

        if ($this->isCompanyOnly()) {
            return $query->where(function (QueryBuilder $builder) use ($table) {
                $builder->where($table.'.is_shareholder_account', 0)
                    ->orWhereNull($table.'.shareholder_partner_id');
            });
        }

        $query->where($table.'.is_shareholder_account', 1)
            ->whereNotNull($table.'.shareholder_partner_id');

        if ($this->shareholderPartnerId) {
            $query->where($table.'.shareholder_partner_id', $this->shareholderPartnerId);
        }

        return $query;
    }

    public function applyToEloquent(EloquentBuilder $query, string $table): EloquentBuilder
    {
        if ($this->isAll()) {
            return $query;
        }

        if ($this->isCompanyOnly()) {
            return $query->where(function (EloquentBuilder $builder) use ($table) {
                $builder->where($table.'.is_shareholder_account', 0)
                    ->orWhereNull($table.'.shareholder_partner_id');
            });
        }

        $query->where($table.'.is_shareholder_account', 1)
            ->whereNotNull($table.'.shareholder_partner_id');

        if ($this->shareholderPartnerId) {
            $query->where($table.'.shareholder_partner_id', $this->shareholderPartnerId);
        }

        return $query;
    }

    /** Shape handed to the Vue pages so the controls can echo the state back. */
    public function toArray(): array
    {
        return [
            self::REQUEST_KEY => $this->owner,
            self::SHAREHOLDER_REQUEST_KEY => $this->shareholderPartnerId,
        ];
    }
}
