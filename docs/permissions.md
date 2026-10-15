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
| Seeding | `database/seeders/PermissionSeeder.php`, `php artisan permissions:sync` |

Permission keys are `module.action` — `money_received.delete`, `cash_expense.create`.

## Adding a module

1. Add one entry to `PermissionRegistry::MODULES`.
2. Map its route names in `RoutePermissionMap::MAP`.
3. `php artisan permissions:sync`.

No controller, Gate, middleware or Vue change is needed. `RouteCoverageTest`
fails the build if step 2 is skipped.

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

## Config

`config/permissions.php`:

- `enforce` — master switch; `false` degrades `EnforcePermission` to log-only.
- `unmapped` — `allow` (current) logs and passes an unmapped route; `deny` 403s.
  Switch to `deny` once the log has been quiet for a release cycle.
- `bulk_deletable_models` / `reviewable_tables` — whitelists for the two generic
  endpoints that previously took a model/table name straight from the request.

## Tests

```
./vendor/bin/phpunit tests/Feature/Permissions/
```

- `RouteCoverageTest` — no unmapped route, no stale entry, no public write route.
  Runs without a database; this is the anti-drift guard.
- `PermissionRegistryTest` — registry integrity and the template matrix.
- `AuthorizationEnforcementTest` — real HTTP: 403 without, 200 with, view does not
  imply delete, **role permissions are not inherited**, editing a role does not
  change an existing user, applying a template copies onto the user, the legacy-name
  leak stays closed, Super Admin bypass, roleless users, hardened endpoints.
  Skips if the database is unreachable.
