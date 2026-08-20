<script setup>
/**
 * DashboardTabs.vue
 * ------------------------------------------------------------------
 * Shared top-level navigation between the Dashboard section's real
 * Inertia pages (Cash Status / LG & LC Status / Cash Forecast /
 * Contract Dashboard — same labels as app/Support/SidebarMenu.php's
 * Dashboard section, so the sidebar and this in-page tab bar never
 * drift out of sync).
 *
 * Each tab is a real page (its own route, its own heavy controller
 * logic), not a client-side content switch — so this uses <Link>
 * (full Inertia visit) rather than the client-side .cvr-filter-pill
 * pattern used for same-page tabs elsewhere (e.g. FinancialInstitutions
 * /Index.vue's Banks/Leasing/Factoring tabs).
 */
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, required: true }, // 'cash' | 'contracts' | 'lglc' | 'forecast'
    urls: { type: Object, required: true }, // { cash, contracts, lglc, forecast }
});

const tabs = [
    { key: 'cash', label: 'Cash Status', icon: '💵' },
    { key: 'lglc', label: 'LG & LC Status', icon: '📜' },
    { key: 'forecast', label: 'Cash Forecast', icon: '🔮' },
    { key: 'contracts', label: 'Contract Dashboard', icon: '📋' },
];
</script>

<template>
    <div class="flex items-center gap-2 flex-wrap mb-6">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="urls[tab.key]"
            class="cvr-dash-tab"
            :class="{ 'cvr-dash-tab-active': active === tab.key }"
        >
            <span>{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
        </Link>
    </div>
</template>
