<?php

namespace Tests\Feature\ShareholderAccounts;

use App\Support\ShareholderAccounts\AccountOwnerFilter;
use App\Support\ShareholderAccounts\ShareholderAccountAccess;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The owner-filter rules, with no database involved.
 *
 * These cover the decisions most easily broken by a later refactor:
 * the default is Company (never All), a specific owner only narrows the
 * shareholders view, and a user without the permission cannot escape
 * Company by hand-crafting a query string.
 *
 * See docs/shareholder-accounts.md.
 */
class AccountOwnerFilterTest extends TestCase
{
    /** D2 — an unfiltered page shows official company figures, not everything. */
    public function test_it_defaults_to_company_accounts(): void
    {
        $this->assertTrue(AccountOwnerFilter::make(null)->isCompanyOnly());
        $this->assertTrue(AccountOwnerFilter::make('')->isCompanyOnly());
        $this->assertTrue(AccountOwnerFilter::make('nonsense')->isCompanyOnly());
        $this->assertTrue(AccountOwnerFilter::fromRequest(new Request)->isCompanyOnly());
    }

    public function test_it_accepts_the_three_declared_owners(): void
    {
        $this->assertTrue(AccountOwnerFilter::make('all')->isAll());
        $this->assertTrue(AccountOwnerFilter::make('company')->isCompanyOnly());
        $this->assertTrue(AccountOwnerFilter::make('shareholders')->isShareholdersOnly());
    }

    /** D3 — "All shareholders" is the shareholders view with no owner id. */
    public function test_a_specific_owner_is_kept_only_inside_the_shareholders_view(): void
    {
        $this->assertSame(7, AccountOwnerFilter::make('shareholders', 7)->shareholderPartnerId);
        $this->assertNull(AccountOwnerFilter::make('shareholders', 0)->shareholderPartnerId);
        $this->assertNull(AccountOwnerFilter::make('shareholders', null)->shareholderPartnerId);

        // Dropped rather than silently narrowing a company/all view.
        $this->assertNull(AccountOwnerFilter::make('company', 7)->shareholderPartnerId);
        $this->assertNull(AccountOwnerFilter::make('all', 7)->shareholderPartnerId);
    }

    /** D6 — the query string is ignored entirely without the permission. */
    public function test_a_user_without_the_permission_is_pinned_to_company_accounts(): void
    {
        $request = new Request([
            AccountOwnerFilter::REQUEST_KEY => 'all',
            AccountOwnerFilter::SHAREHOLDER_REQUEST_KEY => 7,
        ]);

        $filter = AccountOwnerFilter::fromRequest($request, false);

        $this->assertTrue($filter->isCompanyOnly());
        $this->assertNull($filter->shareholderPartnerId);

        // ...and the same request IS honoured for someone who may see them.
        $this->assertTrue(AccountOwnerFilter::fromRequest($request, true)->isAll());
    }

    public function test_the_permission_key_matches_the_registry(): void
    {
        $this->assertTrue(
            \App\Support\Permissions\PermissionRegistry::has(ShareholderAccountAccess::PERMISSION_KEY),
            'shareholder_account.view must exist in PermissionRegistry, or PermissionResolver fails it closed.'
        );
    }

    /**
     * The two columns are always written as a consistent pair: a company
     * account never keeps a stale owner id.
     */
    public function test_ownership_is_normalised_into_a_consistent_pair(): void
    {
        $this->assertSame(
            ['is_shareholder_account' => true, 'shareholder_partner_id' => 7],
            ShareholderAccountAccess::normalizeOwnership(['is_shareholder_account' => true, 'shareholder_partner_id' => 7])
        );

        // Unticked → the owner id is dropped, not left behind.
        $this->assertSame(
            ['is_shareholder_account' => false, 'shareholder_partner_id' => null],
            ShareholderAccountAccess::normalizeOwnership(['is_shareholder_account' => false, 'shareholder_partner_id' => 7])
        );

        // Ticked with no owner chosen → no half-written flag.
        $this->assertSame(
            ['is_shareholder_account' => true, 'shareholder_partner_id' => null],
            ShareholderAccountAccess::normalizeOwnership(['is_shareholder_account' => true, 'shareholder_partner_id' => 0])
        );

        // Form values arrive as strings.
        $this->assertSame(
            ['is_shareholder_account' => true, 'shareholder_partner_id' => 7],
            ShareholderAccountAccess::normalizeOwnership(['is_shareholder_account' => '1', 'shareholder_partner_id' => '7'])
        );

        $this->assertSame(
            ['is_shareholder_account' => false, 'shareholder_partner_id' => null],
            ShareholderAccountAccess::normalizeOwnership([])
        );
    }
}
