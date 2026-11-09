# Permissions

**Permissions are held per user.** Each person has their own set, and that set is
the only thing that grants access. A role is a *template* and a label — never a
live source of authority.

```
User ── has ──▶ Permissions        ← what actually decides access
  │
  └── has ──▶ Role                 ← a label, and a template copied at creation
```

The practical consequence, because the opposite is the natural assumption:
**editing a role changes nothing for anyone who already exists.** It changes what
future users start with. To change someone, edit that person.

## The short version

| Concern | Where |
|---|---|
| What permissions exist | `app/Support/Permissions/PermissionRegistry.php` |
| Which route needs which permission | `app/Support/Permissions/RoutePermissionMap.php` |
| Does this user hold it | `app/Support/Permissions/PermissionResolver.php` |
| Copying a template onto a user | `app/Support/Permissions/RoleTemplate.php` |
| Backend enforcement | `app/Http/Middleware/EnforcePermission.php` (in the `web` group) |
| Explicit per-route guard | `->middleware('permission:role.update')` |
| Frontend check | `resources/js/composables/usePermissions.js` |
| **Per-user permissions UI** | `UsersAndPermissionsController` + `SuperAdmin/Users/Permissions.vue` |
| Template management UI | `RoleController` + `SuperAdmin/Roles/` |
| Seeding role templates | `database/seeders/PermissionSeeder.php`, `php artisan permissions:sync` |
| Giving new keys to existing users | `app/Support/Permissions/PermissionRollout.php`, `php artisan permissions:rollout` |

Permission keys are `module.action` — `money_received.delete`, `cash_expense.create`.

## Adding a module

1. Add one entry to `PermissionRegistry::MODULES`.
2. Map its route names in `RoutePermissionMap::MAP`.
3. Deploy. `deploy.sh` runs `php artisan permissions:sync` (role templates, for
   future users) and then `php artisan permissions:rollout --force` (existing users).

No controller, Gate, middleware or Vue change is needed. `RouteCoverageTest`
fails the build if step 2 is skipped.

### ⚠️ A new key reaches no existing user on its own

Permissions are per user, and `permissions:sync` only fills role templates. So
after a release adds `report_x.view`, nobody but a Super Admin can open it.

`permissions:rollout` fixes that **once per key**. It gives the new key to every
user who already holds the screen it came from, meaning one of its `legacy`
names or another canonical key that stands for the same legacy name. That's the
same rule `migrate-to-user` used. Example: `report_bank_charges_analysis.view`
(legacy `view bank statement report`) goes to everyone with
`report_bank_statement.view`.

- **Give the new key a `legacy` alias** pointing at the screen it was carved out
  of. Without one it has no parent, reaches nobody, and a Super Admin grants it
  by hand. The rollout output says so.
- **Once.** Each rolled-out key gets a row in `permission_rollouts`, and a key
  with a row is never looked at again. If an admin later removes it from
  someone, the next deploy leaves that alone.
- Run `php artisan permissions:rollout` without `--force` to see who would get
  what. It writes nothing.

Why not just honour the alias at check time? After the migration users hold
canonical keys only, and `PermissionResolver` matches names literally. Widening
that would make every `INHERITED` key permanent, and the point of the new key is
that an admin can tighten it.

## Checking a permission

**Backend** — all four forms resolve identically, against the user's own grants:

```php
$user->can('cash_expense.delete');
$user->hasPermissionKey('cash_expense.delete');
hasAuthFor('cash_expense.delete');          // helper, null-safe
$user->checkPermissionTo('cash_expense.delete');
```

**Frontend** — the composable, the global, or the directive:

```vue
<script setup>
import { usePermissions } from '@/composables/usePermissions';
const { can, canAny, canAll } = usePermissions();
</script>

<button v-if="can('money_received.create')">Create</button>
<button v-if="$can('money_received.create')">Create</button>
<button v-can="'money_received.delete'">Delete</button>
```

`auth.permissions` is shared on every response via `Inertia::always()`, so
`can()` never makes a request. **It is UX only** — the server decides.

Never branch on a role name in business logic. `$user->role === 'admin'` hardcodes
today's org chart; `can('cash_expense.delete')` lets access change without a deploy.

### ⚠️ Why `User::checkPermissionTo()` is overridden

Spatie registers its own `Gate::before` (`PermissionRegistrar::registerPermissions`)
that calls `checkPermissionTo()`, whose stock implementation resolves the **union of
role and direct** grants. Gate `before` callbacks run in registration order and
Spatie's is registered first, so an application-level `before` cannot reliably win.

Left alone, `$user->can('view cash expenses')` would pass on a permission held only
by the user's role — reinstating role inheritance through the back door for every
legacy call site (`App\Notification` alone has 13) while dotted keys behaved
correctly. Overriding the method in `App\Models\User` fixes it at the source so
every path agrees. `AuthorizationEnforcementTest::test_a_role_held_legacy_name_does_not_leak_through_can`
guards it.

## Roles as templates

Four built-in roles ship with templates from `PermissionRegistry::ROLE_DEFAULTS`:

| Role | Template | Notes |
|---|---|---|
| `super-admin` | everything | Also a centralised `Gate::before` bypass |
| `company-admin` | everything bar `super_admin.*` / `company.*` | |
| `manager` | view/create/update/export/approve/settle everywhere | **no delete**, no admin |
| `user` | view + export | read-only starting point |

A template is copied onto a user at exactly two moments:

1. **User creation** — `UserController::store()` applies the chosen role's template.
2. **On demand** — "Start from a template" on the per-user permission screen fills
   the boxes; nothing is saved until Save is pressed.

Changing a user's role on the edit form does **not** rewrite their permissions —
their set may have been tuned since. An opt-in checkbox appears when the role
actually changed, for when replacing them *is* the intent.

Role names are fixed for the four built-ins (application logic matches on them);
templates are fully editable, and custom roles can be created.

## Two safeguards worth knowing

- **You cannot grant what you do not hold.** `RoleTemplate::applyTo()` / `setFor()`
  and `RoleController::syncPermissions()` drop any key the editor lacks;
  `UserController::authUserCanAssignRole()` refuses a custom role carrying
  permissions the assigner lacks. Without these, anyone who can edit permissions
  could mint themselves an admin.
- **Unknown keys fail closed.** A typo denies rather than allows, and a forged
  permission name is discarded rather than created.

## Legacy names

182 natural-language permissions (`'delete cash expenses'`) are live in production.
Each registry action lists them under `legacy`, and a user passes if they hold the
canonical key **or** any legacy alias — so nothing was revoked when this shipped.
Some are marked `INHERITED` where a screen is gated today by a neighbouring
module's permission; the new key lets an admin tighten it later without a code change.

## Migrating to user-based access

```bash
php artisan permissions:migrate-to-user            # dry run — prints a table
php artisan permissions:migrate-to-user --force    # apply
```

For each user it computes what they can do **today** (their role's permissions ∪
their own), expands legacy names to canonical keys, and writes the result directly
onto the user. Nobody gains or loses access; afterwards everyone is edited
individually.

**Order matters:** run it **before** the first `permissions:sync`. Once role
templates are filled, `direct ∪ role` would copy them onto every user, granting
access nobody had. The command warns and refuses in that case.

Production has already been migrated (November 2026). This is history now: don't
run it again there, and it isn't part of `deploy.sh`.

## Config

`config/permissions.php`:

- `enforce` — master switch; `false` degrades `EnforcePermission` to log-only.
- `unmapped` — `deny` (the default) 403s a route that has no rule; `allow` logs
  and passes it. Use `PERMISSIONS_UNMAPPED=allow` only as a temporary emergency
  switch; `RouteCoverageTest` keeps the map complete anyway.
- `bulk_deletable_models` — whitelist for the generic bulk-delete endpoint
  (`DeletingClass`), which used to take a model name straight from the request.
  The generic review endpoint has its own fixed list in code instead:
  `MovementReviewController::MOVEMENTS`.

## Tests

```
./vendor/bin/phpunit tests/Feature/Permissions/
```

- `RouteCoverageTest` — no unmapped route, no stale entry, no public write route.
  Runs without a database; this is the anti-drift guard.
- `PermissionRegistryTest` — registry integrity and the template matrix.
- `PermissionRolloutTest` — a new key goes to holders of its parent screen only,
  once (an admin's later removal sticks), never to Super Admins, never for keys
  already handed out, and a dry run writes nothing.
- `AuthorizationEnforcementTest` — real HTTP: 403 without, 200 with, view does not
  imply delete, **role permissions are not inherited**, editing a role does not
  change an existing user, applying a template copies onto the user, the legacy-name
  leak stays closed, Super Admin bypass, roleless users, hardened endpoints.
  Skips if the database is unreachable.
