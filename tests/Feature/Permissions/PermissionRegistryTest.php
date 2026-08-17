<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Tests\TestCase;

/**
 * Structural guarantees about the registry itself.
 *
 * These run without a database — they check the declaration, which is
 * what every other layer derives from. If the registry is wrong,
 * everything downstream is wrong in the same way.
 */
class PermissionRegistryTest extends TestCase
{
    public function test_every_permission_key_is_well_formed(): void
    {
        $malformed = [];

        foreach (PermissionRegistry::all() as $key => $permission) {
            if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $key)) {
                $malformed[] = $key;
            }

            if ($key !== "{$permission['module']}.{$permission['action']}") {
                $malformed[] = "{$key} (key does not match module.action)";
            }
        }

        $this->assertSame([], $malformed,
            "Malformed permission key(s):\n  ".implode("\n  ", $malformed)
        );
    }

    public function test_every_module_declares_a_view_action(): void
    {
        $missing = [];

        foreach (PermissionRegistry::modules() as $module => $definition) {
            if (! isset($definition['actions']['view'])) {
                $missing[] = $module;
            }
        }

        $this->assertSame([], $missing,
            "Module(s) with no `view` action — nothing can gate reaching the page:\n  "
            .implode("\n  ", $missing)
        );
    }

    /**
     * The non-breaking-deploy guarantee.
     *
     * Every key must resolve to at least one permission NAME that already
     * exists in production, otherwise turning enforcement on would revoke
     * access from users who legitimately have it today. `grantNames()`
     * always includes the canonical key plus the declared legacy aliases,
     * so the real assertion is that a legacy alias was declared at all.
     */
    public function test_every_permission_declares_a_legacy_grant_name(): void
    {
        $orphans = [];

        foreach (PermissionRegistry::all() as $key => $permission) {
            if ($permission['legacy'] === []) {
                $orphans[] = $key;
            }

            $this->assertContains($key, PermissionRegistry::grantNames($key),
                "grantNames({$key}) must always include the canonical key itself"
            );
        }

        $this->assertSame([], $orphans, sprintf(
            "%d permission key(s) declare no legacy alias. On deploy nobody would hold them,\n".
            "so the feature they guard would become inaccessible to every existing user.\n".
            "Map each to the permission that governs it today:\n\n  %s\n",
            count($orphans),
            implode("\n  ", $orphans)
        ));
    }

    public function test_group_labels_exist_for_every_module(): void
    {
        $unknown = [];

        foreach (PermissionRegistry::modules() as $module => $definition) {
            if (! isset(PermissionRegistry::GROUPS[$definition['group']])) {
                $unknown[] = "{$module} => {$definition['group']}";
            }
        }

        $this->assertSame([], $unknown,
            "Module(s) in an undeclared group (they would render unlabelled in Role Management):\n  "
            .implode("\n  ", $unknown)
        );
    }

    public function test_action_labels_exist_for_every_action(): void
    {
        $unknown = [];

        foreach (PermissionRegistry::all() as $key => $permission) {
            if (! isset(PermissionRegistry::ACTION_LABELS[$permission['action']])) {
                $unknown[] = $key;
            }
        }

        $this->assertSame([], $unknown,
            "Permission(s) using an action with no declared label:\n  ".implode("\n  ", $unknown)
        );
    }

    public function test_tree_covers_every_permission_exactly_once(): void
    {
        $seen = [];

        foreach (PermissionRegistry::tree() as $group) {
            foreach ($group['modules'] as $module) {
                foreach ($module['permissions'] as $permission) {
                    $seen[] = $permission['key'];
                }
            }
        }

        sort($seen);
        $expected = PermissionRegistry::keys();
        sort($expected);

        $this->assertSame($expected, $seen,
            'The Role Management tree must expose every declared permission exactly once — '
            .'anything missing simply cannot be granted through the UI.'
        );
    }

    /* ── Role defaults ─────────────────────────────────────────────── */

    public function test_super_admin_defaults_to_every_permission(): void
    {
        $this->assertSame(
            PermissionRegistry::keys(),
            PermissionRegistry::defaultKeysForRole(User::SUPER_ADMIN)
        );
    }

    /**
     * The differentiation the whole system exists to provide, asserted
     * against the exact example in the specification:
     *
     *   Manager  ✓ Create Money Received  ✓ Edit Money Received
     *            ✗ Delete Money Received  ✓ Create Cash Expense
     *            ✗ Delete Cash Expense    ✓ View Cash Flow
     */
    public function test_manager_can_create_and_edit_but_not_delete(): void
    {
        $manager = array_fill_keys(PermissionRegistry::defaultKeysForRole(User::MANAGER), true);

        $this->assertArrayHasKey('money_received.create', $manager);
        $this->assertArrayHasKey('money_received.update', $manager);
        $this->assertArrayNotHasKey('money_received.delete', $manager);

        $this->assertArrayHasKey('cash_expense.create', $manager);
        $this->assertArrayNotHasKey('cash_expense.delete', $manager);

        $this->assertArrayHasKey('cash_flow_report.view', $manager);
    }

    public function test_manager_cannot_administer_users_roles_or_companies(): void
    {
        $manager = array_fill_keys(PermissionRegistry::defaultKeysForRole(User::MANAGER), true);

        foreach (['role.view', 'role.update', 'user.create', 'user.delete',
            'user.assign_roles', 'company.view', 'super_admin.view'] as $key) {
            $this->assertArrayNotHasKey($key, $manager, "Manager must not hold {$key} by default");
        }
    }

    public function test_plain_user_is_read_only(): void
    {
        $user = PermissionRegistry::defaultKeysForRole(User::USER);

        foreach ($user as $key) {
            $action = PermissionRegistry::all()[$key]['action'];
            $this->assertContains($action, ['view', 'export'],
                "The `user` role defaults to read-only, but holds {$key}"
            );
        }

        $set = array_fill_keys($user, true);
        $this->assertArrayHasKey('money_received.view', $set);
        $this->assertArrayNotHasKey('money_received.create', $set);
    }

    public function test_no_role_holds_more_permissions_than_the_one_above_it(): void
    {
        $counts = [];
        foreach ([User::SUPER_ADMIN, User::COMPANY_ADMIN, User::MANAGER, User::USER] as $role) {
            $counts[$role] = count(PermissionRegistry::defaultKeysForRole($role));
        }

        $this->assertGreaterThan($counts[User::COMPANY_ADMIN], $counts[User::SUPER_ADMIN]);
        $this->assertGreaterThan($counts[User::MANAGER], $counts[User::COMPANY_ADMIN]);
        $this->assertGreaterThan($counts[User::USER], $counts[User::MANAGER]);
    }

    public function test_seedable_names_include_keys_and_legacy_aliases(): void
    {
        $seedable = array_fill_keys(PermissionRegistry::seedableNames(), true);

        foreach (PermissionRegistry::all() as $key => $permission) {
            $this->assertArrayHasKey($key, $seedable, "Canonical key {$key} must be seeded");

            foreach ($permission['legacy'] as $legacy) {
                $this->assertArrayHasKey($legacy, $seedable,
                    "Legacy alias {$legacy} must exist as a row, or syncPermissions() throws"
                );
            }
        }
    }
}
