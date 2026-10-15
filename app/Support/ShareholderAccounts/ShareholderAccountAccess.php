<?php

namespace App\Support\ShareholderAccounts;

use App\Models\Partner;
use App\Models\User;

/**
 * One place to ask "may this user see shareholder-owned accounts?" and to
 * fetch the shareholder list the owner filter's second select is built from.
 *
 * Decision D6 (docs/shareholder-accounts.md): a single permission in v1,
 * `shareholder_account.view`. Record-level (per-account) permissions were
 * explicitly deferred, so this is intentionally a yes/no answer rather than
 * a per-account one.
 */
class ShareholderAccountAccess
{
    public const PERMISSION_KEY = 'shareholder_account.view';

    public static function canView(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasPermissionKey(self::PERMISSION_KEY);
    }

    /**
     * Shareholders of this company, for the "which owner?" select.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public static function shareholdersForSelect(int $companyId): array
    {
        return Partner::onlyCompany($companyId)
            ->onlyShareholders()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => [
                'id' => (int) $partner->id,
                'name' => (string) $partner->getName(),
            ])
            ->values()
            ->all();
    }

    /**
     * Read the owner filter off the current request, already gated by the
     * permission — the single call sites should use.
     */
    public static function filterFromRequest(\Illuminate\Http\Request $request, ?User $user = null): AccountOwnerFilter
    {
        return AccountOwnerFilter::fromRequest($request, self::canView($user));
    }

    /**
     * The props every create/edit form needs to render the
     * "Account Owner: Company / Shareholder" control.
     *
     * When the user lacks the permission the control is not rendered at
     * all, and the shareholder list is empty — no owner names leak to a
     * page that is not allowed to show them.
     *
     * @return array{canManageShareholderAccounts: bool, shareholders: array<int, array{id:int, name:string}>}
     */
    public static function formProps(int $companyId): array
    {
        $canView = self::canView();

        return [
            'canManageShareholderAccounts' => $canView,
            'shareholders' => $canView ? self::shareholdersForSelect($companyId) : [],
        ];
    }

    /**
     * The stored ownership of one record, in the shape the Vue forms read.
     *
     * @return array{is_shareholder_account: bool, shareholder_partner_id: int|null}
     */
    public static function modelProps($model): array
    {
        return [
            'is_shareholder_account' => (bool) ($model->is_shareholder_account ?? false),
            'shareholder_partner_id' => $model->shareholder_partner_id ? (int) $model->shareholder_partner_id : null,
        ];
    }

    /**
     * The two ownership columns, read off a submitted form and normalised.
     *
     * Returns an EMPTY array for a user without the permission, so the two
     * columns are simply left out of the write — a create keeps its
     * company-owned defaults and an update leaves whatever was already
     * stored alone, rather than a permission-less user silently clearing
     * another user's flag.
     *
     * @param  string  $prefix  field prefix for repeater rows, e.g. "accounts.0."
     * @return array{is_shareholder_account?: bool, shareholder_partner_id?: int|null}
     */
    public static function ownershipFromRequest(\Illuminate\Http\Request $request, string $prefix = ''): array
    {
        if (! self::canView()) {
            return [];
        }

        return self::normalizeOwnership([
            'is_shareholder_account' => $request->input($prefix.'is_shareholder_account'),
            'shareholder_partner_id' => $request->input($prefix.'shareholder_partner_id'),
        ]);
    }

    /**
     * Keep the two columns consistent with each other: a company account
     * never keeps a stale owner id, and a shareholder account never keeps a
     * zero/empty one.
     *
     * @param  array<string, mixed>  $data
     * @return array{is_shareholder_account: bool, shareholder_partner_id: int|null}
     */
    public static function normalizeOwnership(array $data): array
    {
        $isShareholderAccount = filter_var(
            $data['is_shareholder_account'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $shareholderPartnerId = (int) ($data['shareholder_partner_id'] ?? 0);

        return [
            'is_shareholder_account' => $isShareholderAccount,
            'shareholder_partner_id' => $isShareholderAccount && $shareholderPartnerId
                ? $shareholderPartnerId
                : null,
        ];
    }
}
