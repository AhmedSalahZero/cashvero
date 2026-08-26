<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';
import ToastStack from '@/Components/ToastStack.vue';
import { useToasts } from '@/composables/useToasts';
import NavIcon from '@/Components/NavIcon.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { theme, toggleTheme } = useTheme();
const page = usePage();
const isRtl = computed(() => (page.props.dir || 'ltr') === 'rtl');

const userName = computed(() => page.props.auth?.user?.name || 'User');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
const isSuperAdmin = computed(() => !!page.props.auth?.isSuperAdmin);
const profileUrl = computed(() => page.props.profileUrl);
const logoutUrl = computed(() => page.props.logoutUrl);
/**
 * Admin shortcuts the CURRENT USER may actually reach. Built from
 * permissions server-side (HandleInertiaRequests), so this menu can
 * never offer a screen the request would then refuse.
 */
const adminUrls = computed(() => page.props.adminUrls ?? {});
const hasAdminLinks = computed(() => Object.keys(adminUrls.value).length > 0);

/* ── User menu dropdown (username, top right) ─────────────────────
   Previously just a static name + avatar — no way to log out at all.
   Real gap, not something removed on purpose. ───────────────────── */
const userMenuOpen = ref(false);
function logout() {
    router.post(logoutUrl.value);
}

/* ── Super Admin management dropdown (Companies / Users) ─────────
   Only rendered at all when isSuperAdmin is true. ────────────────── */
const adminMenuOpen = ref(false);

/*
 * The full sidebar is shared globally by HandleInertiaRequests (see
 * app/Support/SidebarMenu.php) — computed once, server-side, for
 * every page. No per-controller navUrls prop needed anymore.
 */
const menu = computed(() => page.props.sidebarMenu ?? {});
const notificationMenu = computed(() => page.props.notificationMenu ?? []);

const sectionKeys = computed(() =>
    Object.keys(menu.value).filter(k => k !== 'home')
);

/* ── Sidebar collapse (whole sidebar width) ──────────────────────── */
const sidebarExpanded = ref(true);
/** Mobile (< lg): sidebar is an off-canvas overlay, closed by default. */
const isMobileNav = ref(false);
const mobileNavOpen = ref(false);

function checkMobileNav() {
    if (typeof window === 'undefined') return;
    isMobileNav.value = window.matchMedia('(max-width: 1023px)').matches;
    if (!isMobileNav.value) {
        mobileNavOpen.value = false;
    }
}

function toggleSidebar() {
    if (isMobileNav.value) {
        mobileNavOpen.value = !mobileNavOpen.value;
        return;
    }
    sidebarExpanded.value = !sidebarExpanded.value;
    localStorage.setItem('cvr_sidebar', sidebarExpanded.value ? 'expanded' : 'collapsed');
}

function closeMobileNav() {
    mobileNavOpen.value = false;
}

/* ── Per-section collapse/expand — remembered across visits ─────── */
const expandedSections = ref({});
function toggleSection(key) {
    expandedSections.value[key] = !expandedSections.value[key];
    localStorage.setItem('cvr_sidebar_sections', JSON.stringify(expandedSections.value));
}
function isSectionExpanded(key) {
    return !!expandedSections.value[key];
}

onMounted(() => {
    const saved = localStorage.getItem('cvr_sidebar');
    if (saved !== null) sidebarExpanded.value = saved === 'expanded';

    const savedSections = localStorage.getItem('cvr_sidebar_sections');
    if (savedSections) {
        try { expandedSections.value = JSON.parse(savedSections); } catch { /* ignore */ }
    }

    checkMobileNav();
    window.addEventListener('resize', checkMobileNav);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobileNav);
});

/* Close the mobile drawer on every Inertia visit so the next page
   opens with full-width content instead of an open overlay. */
const removeNavListener = router.on('finish', () => {
    closeMobileNav();
});
onUnmounted(() => removeNavListener());

function isActiveLink(link) {
    if (!link || link === '#') return false;
    try {
        const linkUrl = new URL(link);
        const currentUrl = new URL(page.url, window.location.origin);
        if (!currentUrl.pathname.startsWith(linkUrl.pathname)) return false;
        /**
         * FIX (per report, 2026-08-13): "Partners" and "Subsidiary
         * Companies" — and any other sidebar item built the same way —
         * point at the exact same route/pathname and differ only by a
         * query param (e.g. ?type=subsidiary-companies). The pathname
         * check above used to be the whole story, so both items lit up
         * together any time either page's pathname matched, regardless
         * of which specific type was actually being viewed. Now: every
         * query param the link itself specifies must match the current
         * page's value too...
         */
        for (const [key, value] of linkUrl.searchParams) {
            if (currentUrl.searchParams.get(key) !== value) return false;
        }
        // ...and the reverse also has to hold: a link with NO query
        // params (plain "Partners") must not stay active once the
        // current page has picked up a `type` the link never asked
        // for — otherwise "Partners" alone would still match every
        // sibling tab sharing its pathname (e.g. Subsidiary Companies).
        if (linkUrl.searchParams.toString() === '' && currentUrl.searchParams.has('type')) {
            return false;
        }
        return true;
    } catch {
        return false;
    }
}

/* ── Odoo Integration action items (Read Partners/Invoices/Contracts)
   — genuine actions in the original app (confirm-and-sync), not
   navigable pages. Opens a small confirm modal, POSTs on confirm. */
/* ── Odoo Integration action items (Read Partners/Invoices/Contracts)
   — genuine actions in the original app (confirm-and-sync), not
   navigable pages. Opens a small confirm modal, POSTs on confirm.
   ⚠️ Start/End Date fields were missing here — the backend
   (ReadOdooPartners/Invoices/Contracts::handle()) already reads
   odoo_start_date/odoo_end_date from the request and always did, but
   this modal only ever POSTed an empty body, so every sync silently
   ran with both dates null. Defaults match the original Blade's
   modals (layouts/dashboard.blade.php) exactly: Start Date = Jan 1 of
   the current year, End Date = today. */
const actionTarget = ref(null);
function defaultOdooStartDate() {
    return `${new Date().getFullYear()}-01-01`;
}
function defaultOdooEndDate() {
    return new Date().toISOString().slice(0, 10);
}
const odooStartDate = ref(defaultOdooStartDate());
const odooEndDate = ref(defaultOdooEndDate());
function openAction(item) {
    actionTarget.value = item;
    odooStartDate.value = defaultOdooStartDate();
    odooEndDate.value = defaultOdooEndDate();
}
function confirmAction() {
    router.post(actionTarget.value.action_url, {
        odoo_start_date: odooStartDate.value,
        odoo_end_date: odooEndDate.value,
    }, {
        onFinish: () => { actionTarget.value = null; },
    });
}

/* ── Notifications bell (top nav) ─────────────────────────────────
   Real counts, same data the original Blade sidebar used
   (App\Notification::formatForMenuItem()). Clicking a sub-item now
   fetches its real filtered table — same data + dynamic columns as
   the original notifications/popup.blade.php modal — via the new
   notifications.detail endpoint, and shows it right here without
   leaving the page. */
const notificationsOpen = ref(false);
const totalNotificationCount = computed(() =>
    notificationMenu.value.reduce((sum, cat) => sum + (cat.count || 0), 0)
);

const notificationDetail = ref(null);
const notificationDetailLoading = ref(false);
async function openNotificationDetail(sub) {
    notificationsOpen.value = false;
    notificationDetailLoading.value = true;
    notificationDetail.value = { title: sub.title, headers: [], rows: [] };
    try {
        const res = await fetch(sub.detail_url, { headers: { Accept: 'application/json' } });
        notificationDetail.value = await res.json();
    } finally {
        notificationDetailLoading.value = false;
    }
}

/*
 * Flash success/error toasts.
 *
 * Root cause of the delayed-toast bug (2026-07-27): php-flasher's
 * SessionMiddleware was stealing session('success') on every redirect
 * (flash_bag mapping → flasher::envelopes + session()->forget). That is
 * now disabled in config/flasher.php. On top of that we:
 *
 * 1. Listen to Inertia v3's native `flash` event (page.flash), which is
 *    what HandleInertiaRequests bridges session flashes into.
 * 2. Keep watching props.flash.token as a fallback for any response that
 *    only populates the shared prop.
 * 3. Deduplicate by token so the two channels never double-toast.
 * 4. Clear consumed native flash after handling so partial reloads don't
 *    re-fire it (inertiajs/inertia#3015).
 *
 * ⚠️ The queue and the dedup token live at MODULE scope (useToasts.js),
 * not inside setup(). This layout is not a persistent Inertia layout —
 * every page component renders its own <AppLayout>, so a visit tears one
 * instance down and builds another. The `flash` event fires while the
 * response is being applied, which can be before the incoming instance
 * exists: with a per-instance queue that toast was pushed into the
 * outgoing instance and disappeared with it, which is the "the message
 * only shows up if I reload" half of the report. Shared scope means
 * whichever instance is on screen renders the same queue, and a token
 * handled by one instance is not re-toasted by the next.
 */
const { toasts, dismissToast, handleFlashPayload } = useToasts();
const removeFlashListener = router.on('flash', (event) => {
    handleFlashPayload(event.detail.flash);
    router.flash(() => ({}));
});
onUnmounted(() => removeFlashListener());
watch(() => page.props.flash?.token, () => {
    handleFlashPayload(page.props.flash);
}, { immediate: true });
if (page.flash?.success || page.flash?.error) {
    handleFlashPayload(page.flash);
}
</script>

<template>
    <div class="min-h-screen cvr-bg flex">
        <!-- Mobile backdrop — closes the off-canvas sidebar -->
        <div
            v-if="isMobileNav && mobileNavOpen"
            class="cvr-sidebar-backdrop"
            @click="closeMobileNav"
        />

        <!-- Sidebar: desktop = collapsible column; mobile = off-canvas overlay -->
        <aside
            class="cvr-sidebar flex flex-col transition-all duration-200"
            :class="[
                isMobileNav
                    ? ['cvr-sidebar-mobile', mobileNavOpen ? 'cvr-sidebar-mobile-open' : '']
                    : [sidebarExpanded ? 'w-64' : 'w-16', 'flex-shrink-0'],
            ]"
            style="background-color: var(--cvr-nav-bg);"
        >
            <!-- Logo -->
            <div
                class="h-14 flex items-center flex-shrink-0"
                :class="(isMobileNav || sidebarExpanded) ? 'px-5' : 'justify-center px-0'"
                style="border-bottom: 1px solid var(--cvr-nav-divider);"
            >
                <span v-if="isMobileNav || sidebarExpanded" class="font-bold text-xl tracking-tight whitespace-nowrap" style="color: var(--cvr-nav-text-active);">
                    {{ $t('Cash') }}<span style="color: var(--cvr-amber-bright);">Vero</span>
                </span>
                <span v-else class="font-bold text-lg" style="color: var(--cvr-amber-bright);">CV</span>
            </div>

            <nav class="flex-1 overflow-y-auto py-3 space-y-1 px-2">
                <!-- HOME (Super Admin only) -->
                <Link
                    v-if="menu.home?.show"
                    :href="menu.home.link"
                    :title="!(isMobileNav || sidebarExpanded) ? $t('Home') : ''"
                    class="cvr-nav-item flex items-center gap-2 py-2 rounded text-sm mb-2"
                    :class="[(isMobileNav || sidebarExpanded) ? 'px-3' : 'px-0 justify-center', { 'cvr-nav-item-active': isActiveLink(menu.home.link) }]"
                >
                    <NavIcon :name="menu.home.icon" :size="18" />
                    <span v-if="isMobileNav || sidebarExpanded" class="truncate">{{ $t('Home') }}</span>
                </Link>

                <!-- 12 collapsible sections -->
                <template v-for="key in sectionKeys" :key="key">
                    <div v-if="menu[key]?.show" class="mb-1">
                        <button
                            @click="toggleSection(key)"
                            class="cvr-nav-section-title w-full flex items-center justify-between py-2 rounded text-sm font-bold"
                            :class="(isMobileNav || sidebarExpanded) ? 'px-3' : 'px-0 justify-center'"
                            :title="!(isMobileNav || sidebarExpanded) ? menu[key].title : ''"
                        >
                            <span class="flex items-center gap-2 truncate">
                                <NavIcon :name="menu[key].icon" :size="18" />
                                <span v-if="isMobileNav || sidebarExpanded" class="truncate">{{ menu[key].title }}</span>
                            </span>
                            <NavIcon
                                v-if="isMobileNav || sidebarExpanded"
                                :name="isSectionExpanded(key) ? 'chevron-down' : (isRtl ? 'chevron-left' : 'chevron-right')"
                                :size="14"
                            />
                        </button>

                        <div v-if="isSectionExpanded(key) && (isMobileNav || sidebarExpanded)" class="ps-2 mt-0.5 space-y-0.5">
                            <template v-for="(sub, idx) in menu[key].items" :key="idx">
                                <template v-if="sub.show">
                                    <!-- Action item (Odoo Read Partners/Invoices/Contracts) -->
                                    <button
                                        v-if="sub.type === 'action'"
                                        @click="openAction(sub); closeMobileNav()"
                                        class="cvr-nav-sub-item w-full text-start flex items-center gap-2 py-1.5 px-3 rounded text-xs"
                                    >
                                        <NavIcon :name="sub.icon" :size="14" />
                                        <span class="truncate">{{ sub.title }}</span>
                                    </button>
                                    <!-- Migrated page → real Inertia Link -->
                                    <Link
                                        v-else-if="sub.inertia"
                                        :href="sub.link"
                                        class="cvr-nav-sub-item flex items-center gap-2 py-1.5 px-3 rounded text-xs"
                                        :class="{ 'cvr-nav-item-active': isActiveLink(sub.link) }"
                                    >
                                        <NavIcon :name="sub.icon" :size="14" />
                                        <span class="truncate">{{ sub.title }}</span>
                                    </Link>
                                    <!-- Still-Blade page → plain full-page link -->
                                    <a
                                        v-else
                                        :href="sub.link"
                                        class="cvr-nav-sub-item flex items-center gap-2 py-1.5 px-3 rounded text-xs"
                                    >
                                        <NavIcon :name="sub.icon" :size="14" />
                                        <span class="truncate">{{ sub.title }}</span>
                                    </a>
                                </template>
                            </template>
                        </div>
                    </div>
                </template>
            </nav>
        </aside>

        <!-- Main column -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header
                class="h-14 flex items-center justify-between px-3 sm:px-6 flex-shrink-0 relative"
                style="background-color: var(--cvr-bg-surface); border-bottom: 1px solid var(--cvr-border); height: var(--cvr-header-height);"
            >
                <button
                    @click="toggleSidebar"
                    class="cvr-action-btn"
                    :title="isMobileNav ? $t('Open menu') : $t('Toggle sidebar')"
                    :aria-label="$t('Toggle navigation')"
                >
                    <NavIcon name="menu" :size="18" />
                </button>

                <div class="flex items-center gap-3">
                    <!-- Admin shortcuts — shown when the user holds at
                         least one of company.view / user.view / role.view.
                         Was v-if="isSuperAdmin", which made those three
                         permissions unable to reveal it at all. -->
                    <div v-if="hasAdminLinks" class="relative">
                        <button
                            @click="adminMenuOpen = !adminMenuOpen"
                            class="cvr-action-btn"
                            :title="$t('Manage Companies & Users')"
                        >
                            <NavIcon name="wrench" :size="18" />
                        </button>
                        <div
                            v-if="adminMenuOpen"
                            class="absolute end-0 mt-2 w-52 cvr-modal rounded-lg shadow-lg z-50 py-1"
                        >
                            <Link
                                v-if="adminUrls.companies"
                                :href="adminUrls.companies"
                                @click="adminMenuOpen = false"
                                class="flex items-center gap-2 w-full text-start px-4 py-2 text-sm cvr-text-secondary cvr-table-row"
                            >
                                <NavIcon name="building-2" :size="16" />
                                {{ $t('Companies') }}
                            </Link>
                            <Link
                                v-if="adminUrls.users"
                                :href="adminUrls.users"
                                @click="adminMenuOpen = false"
                                class="flex items-center gap-2 w-full text-start px-4 py-2 text-sm cvr-text-secondary cvr-table-row"
                            >
                                <NavIcon name="user" :size="16" />
                                {{ $t('Users') }}
                            </Link>
                            <Link
                                v-if="adminUrls.roles"
                                :href="adminUrls.roles"
                                @click="adminMenuOpen = false"
                                class="flex items-center gap-2 w-full text-start px-4 py-2 text-sm cvr-text-secondary cvr-table-row"
                            >
                                <NavIcon name="shield" :size="16" />
                                {{ $t('Roles & Permissions') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Notifications bell -->
                    <div class="relative">
                        <button
                            @click="notificationsOpen = !notificationsOpen"
                            class="cvr-action-btn relative"
                            :title="$t('Notifications')"
                        >
                            <NavIcon name="bell" :size="18" />
                            <span
                                v-if="totalNotificationCount > 0"
                                class="absolute -top-1 -end-1 text-[0.6rem] leading-none rounded-full px-1.5 py-0.5 cvr-badge-overdue font-bold"
                            >{{ totalNotificationCount }}</span>
                        </button>

                        <div
                            v-if="notificationsOpen"
                            class="absolute end-0 mt-2 w-80 cvr-modal rounded-lg shadow-lg z-50 py-2"
                        >
                            <div class="px-4 py-2 flex items-center justify-between">
                                <p class="text-sm font-semibold cvr-text-primary">{{ $t('Notifications') }}</p>
                                <button @click="notificationsOpen = false" class="text-xs cvr-text-muted">✕</button>
                            </div>
                            <div v-if="notificationMenu.length === 0" class="px-4 py-6 text-center text-sm cvr-text-muted">
                                {{ $t('Nothing to show.') }}
                            </div>
                            <div v-for="(cat, i) in notificationMenu" :key="i" class="px-4 py-2 border-t" style="border-color: var(--cvr-border);">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--cvr-blue-text);">{{ cat.title }}</p>
                                    <span v-if="cat.count > 0" class="cvr-num-red text-xs font-bold">{{ cat.count }}</span>
                                </div>
                                <button
                                    v-for="(sub, j) in cat.submenu"
                                    :key="j"
                                    @click="openNotificationDetail(sub)"
                                    class="w-full flex items-center justify-between text-xs py-1 px-1 rounded cvr-table-row"
                                >
                                    <span class="cvr-text-secondary">{{ sub.title }}</span>
                                    <span :class="sub.count > 0 ? 'cvr-num-amber font-semibold' : 'cvr-text-muted'">{{ sub.count }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <LanguageSwitcher />

                    <button
                        @click="toggleTheme"
                        class="text-xs px-3 py-1.5 rounded cvr-btn-secondary border inline-flex items-center gap-1.5"
                    >
                        <NavIcon :name="theme === 'dark' ? 'sun' : 'moon'" :size="14" />
                        {{ theme === 'dark' ? $t('Light') : $t('Dark') }}
                    </button>
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2">
                            <span class="text-sm text-white hidden sm:inline">{{ userName }}</span>
                            <div class="cvr-avatar">{{ userInitial }}</div>
                        </button>
                        <div
                            v-if="userMenuOpen"
                            class="absolute end-0 mt-2 w-44 cvr-modal rounded-lg shadow-lg z-50 py-1"
                        >
                            <div class="px-4 py-2 text-sm font-medium cvr-text-primary border-b" style="border-color: var(--cvr-border);">
                                {{ userName }}
                            </div>
                            <Link
                                :href="profileUrl"
                                @click="userMenuOpen = false"
                                class="flex items-center gap-2 w-full text-start px-4 py-2 text-sm cvr-text-secondary cvr-table-row"
                            >
                                <NavIcon name="user" :size="16" />
                                {{ $t('Profile') }}
                            </Link>
                            <button
                                @click="logout"
                                class="flex items-center gap-2 w-full text-start px-4 py-2 text-sm cvr-text-secondary cvr-table-row"
                            >
                                <NavIcon name="log-out" :size="16" />
                                {{ $t('Logout') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <!-- Flash messages — shown for any redirect()->with('success'/'fail', ...)
                 anywhere in the app, dark/light theme aware. See ToastStack.vue. -->
            <ToastStack :toasts="toasts" @dismiss="dismissToast" />

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>

        <!-- Odoo action confirm modal (Read Partners / Invoices / Contracts) -->
        <div v-if="actionTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="cvr-modal rounded-lg p-6 w-full sm:max-w-sm max-w-[calc(100vw-2rem)]">
                <h2 class="text-lg font-medium cvr-text-primary mb-4">
                    {{ actionTarget.title }}?
                </h2>
                <p class="text-sm cvr-text-muted mb-4">
                    {{ $t('This will sync now from Odoo. Continue?') }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                        <input v-model="odooStartDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }}</label>
                        <input v-model="odooEndDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button @click="actionTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Cancel') }}</button>
                    <button @click="confirmAction" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                </div>
            </div>
        </div>

        <!-- Notification detail table — real data, same source as the
             original notifications/popup.blade.php modal -->
        <div v-if="notificationDetail" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="cvr-modal rounded-lg p-4 sm:p-6 w-full sm:max-w-7xl max-w-[calc(100vw-2rem)] max-h-[92vh] flex flex-col">
                <div class="flex items-center justify-between mb-4 gap-2">
                    <h2 class="text-lg font-medium cvr-text-primary truncate">{{ notificationDetail.title }}</h2>
                    <button @click="notificationDetail = null" class="cvr-btn-secondary px-3 py-1.5 rounded border flex-shrink-0">{{ $t('Close') }}</button>
                </div>
                <div class="overflow-auto flex-1 cvr-table-scroll">
                    <div v-if="notificationDetailLoading" class="text-center py-8 cvr-text-muted text-sm">{{ $t('Loading...') }}</div>
                    <table v-else class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-start">#</th>
                                <th v-for="(h, i) in notificationDetail.headers" :key="i" class="px-3 py-2 text-start">{{ h }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in notificationDetail.rows" :key="i" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ i + 1 }}</td>
                                <td v-for="(h, j) in notificationDetail.headers" :key="j" class="px-3 py-2 cvr-text-primary">{{ row[h] }}</td>
                            </tr>
                            <tr v-if="notificationDetail.rows.length === 0">
                                <td :colspan="notificationDetail.headers.length + 1" class="px-3 py-8 text-center cvr-text-muted">
                                    {{ $t('Nothing here right now.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>