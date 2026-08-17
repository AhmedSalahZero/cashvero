import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * usePermissions
 * ==================================================================
 * The ONE way a Vue component asks about permissions.
 *
 *   import { usePermissions } from '@/composables/usePermissions';
 *   const { can, canAny, canAll } = usePermissions();
 *
 *   <button v-if="can('money_received.create')">Create</button>
 *
 * Also available without importing anything, via the global property
 * and directive registered in app.js:
 *
 *   <button v-if="$can('money_received.create')">Create</button>
 *   <button v-can="'money_received.delete'">Delete</button>
 *   <button v-can:any="['a.view', 'b.view']">…</button>
 *
 * Reads `auth.permissions` — the flat list of canonical keys shared by
 * HandleInertiaRequests on every response. No network request is made;
 * the list is already on the page and stays reactive across Inertia
 * visits, so changing a user's role updates the UI on their next
 * navigation.
 *
 * ⚠️ This is UX only. Every one of these keys is independently
 * enforced by App\Http\Middleware\EnforcePermission on the server.
 * Hiding a button is a courtesy, never a security control.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props?.auth?.permissions ?? []);
    const permissionSet = computed(() => new Set(permissions.value));
    const isSuperAdmin = computed(() => Boolean(page.props?.auth?.isSuperAdmin));
    const role = computed(() => page.props?.auth?.role ?? null);

    /**
     * Does the user hold this permission key?
     * Passing an array is treated as "any of".
     */
    function can(key) {
        if (!key) return false;
        if (isSuperAdmin.value) return true;
        if (Array.isArray(key)) return key.some((k) => permissionSet.value.has(k));
        return permissionSet.value.has(key);
    }

    /** At least one of the given keys. */
    function canAny(keys) {
        if (!Array.isArray(keys) || keys.length === 0) return false;
        if (isSuperAdmin.value) return true;
        return keys.some((k) => permissionSet.value.has(k));
    }

    /** Every one of the given keys. */
    function canAll(keys) {
        if (!Array.isArray(keys) || keys.length === 0) return false;
        if (isSuperAdmin.value) return true;
        return keys.every((k) => permissionSet.value.has(k));
    }

    /** Convenience: none of the given keys. */
    function cannot(key) {
        return !can(key);
    }

    return { can, canAny, canAll, cannot, permissions, isSuperAdmin, role };
}

/**
 * Non-reactive lookup for code running outside a component's setup()
 * — route guards, event handlers created at module scope, etc.
 * Prefer usePermissions() inside components.
 */
export function checkPermission(pageProps, key) {
    if (!key) return false;
    if (pageProps?.auth?.isSuperAdmin) return true;
    const granted = pageProps?.auth?.permissions ?? [];
    if (Array.isArray(key)) return key.some((k) => granted.includes(k));
    return granted.includes(key);
}
