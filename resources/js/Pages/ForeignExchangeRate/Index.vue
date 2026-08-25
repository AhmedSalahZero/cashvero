<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

/*
 * ForeignExchangeRate/Index.vue
 * ------------------------------------------------------------------
 * One tab per currency, each tab a paginated, searchable, date-ranged
 * table of that currency's historical rates. See the controller's
 * class docblock for the two deliberate fixes made here (real SQL
 * pagination instead of loading everything into memory, and a
 * corrected "which row is editable" rule) — both requested explicitly
 * after reviewing this page with hundreds of rows in mind.
 *
 * The Add Rate form stays inline on the list page (as it always was —
 * there was never a separate "create" page for this), and doubles as
 * the Edit form when a row's Edit action is clicked (?edit=ID).
 */

const props = defineProps({
    company: Object,
    mainFunctionalCurrency: String,
    existingCurrencies: Array,
    activeTab: String,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    hasOdooIntegration: Boolean,
    currencies: Object, // {code: label}
    searchFieldOptions: Object, // {from_currency: 'From Currency', ...}
    filters: Object, // {field, value, startDate, endDate}
    rates: Object, // Laravel paginator: { data, links, current_page, last_page, from, to, total }
    editingRate: Object, // null unless ?edit=ID was present
    indexUrl: String,
    storeUrl: String,
});

function switchTab(currency) {
    router.get(props.indexUrl, { active: currency }, { preserveState: true, preserveScroll: true });
}

/* ── Search — server-side, debounced ──────────────────────────────
   Matches the Partners/Index.vue pattern: with hundreds of rows per
   currency, this can no longer filter an in-memory array like the
   old page did (see controller docblock, SCALING FIX). */
const searchField = ref(props.filters.field || 'from_currency');
const searchValue = ref(props.filters.value || '');
let searchTimer = null;
function triggerSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(props.indexUrl, {
            active: props.activeTab,
            field: searchField.value,
            value: searchValue.value,
            startDate: dateFilters.value.startDate,
            endDate: dateFilters.value.endDate,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}
watch(searchValue, triggerSearch);
watch(searchField, () => { if (searchValue.value) triggerSearch(); });

/* ── Date range filter ────────────────────────────────────────────
   Explicit Apply button (matches the old page's own two-date form,
   which submitted on its own rather than per-keystroke). */
const dateFilters = ref({
    startDate: props.filters.startDate,
    endDate: props.filters.endDate,
});
function applyDateFilter() {
    router.get(props.indexUrl, {
        active: props.activeTab,
        field: searchField.value,
        value: searchValue.value,
        startDate: dateFilters.value.startDate,
        endDate: dateFilters.value.endDate,
    }, { preserveState: true, preserveScroll: true });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

/* ── Add / Edit Rate form ─────────────────────────────────────────
   One shared inline form for both — matches the old page's own
   _form.blade.php, which already did this (isset($model) ? update :
   store) rather than having two separate forms. */
function emptyForm() {
    return {
        date: new Date().toISOString().slice(0, 10),
        from_currency: props.activeTab,
        to_currency: 'EGP',
        exchange_rate: 1,
    };
}
const form = ref(props.editingRate ? {
    date: props.editingRate.date,
    from_currency: props.editingRate.from_currency,
    to_currency: props.editingRate.to_currency,
    exchange_rate: props.editingRate.exchange_rate,
} : emptyForm());
const isEditing = ref(!!props.editingRate);

const submitting = ref(false);
function submitForm() {
    submitting.value = true;
    if (isEditing.value) {
        router.patch(props.editingRate.update_url, form.value, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.storeUrl, form.value, { onFinish: () => { submitting.value = false; } });
    }
}
function cancelEdit() {
    router.get(props.indexUrl, { active: props.activeTab }, { preserveScroll: true });
}

/* ── Delete confirmation ─────────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Foreign Exchange Rate') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">
                Main functional currency: {{ mainFunctionalCurrency }}
                <span v-if="hasOdooIntegration"> {{ $t('— rates also sync automatically from Odoo') }}</span>
            </p>

            <!-- Currency tabs -->
            <div class="flex items-center gap-1 mb-4 flex-wrap">
                <button
                    v-for="currency in existingCurrencies"
                    :key="currency"
                    @click="switchTab(currency)"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeTab === currency }"
                >
                    {{ currency }} Table
                </button>
            </div>

            <!-- Add / Edit Rate form -->
            <div class="cvr-card mb-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                    {{ isEditing ? $t('Edit Exchange Rate') : $t('Foreign Exchange Rates Section') }}
                </h2>
                <form @submit.prevent="submitForm" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Date') }}</label>
                        <input v-model="form.date" type="date" :max="new Date().toISOString().slice(0, 10)" class="cvr-input px-3 py-2 rounded w-48" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('From Currency') }}</label>
                        <select v-model="form.from_currency" class="cvr-input px-3 py-2 rounded w-32">
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('To Currency') }}</label>
                        <select v-model="form.to_currency" class="cvr-input px-3 py-2 rounded w-32">
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }}</label>
                        <input v-model="form.exchange_rate" type="number" step="0.0001" min="0" class="cvr-input px-3 py-2 rounded w-32" />
                    </div>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                    <button v-if="isEditing" type="button" @click="cancelEdit" class="cvr-btn-secondary px-4 py-2 rounded border">
                        {{ $t('Cancel') }}
                    </button>
                </form>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">{{ $t('Search In') }}</label>
                    <select v-model="searchField" class="cvr-input px-3 py-2 rounded w-48">
                        <option v-for="(label, field) in searchFieldOptions" :key="field" :value="field">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('Value') }}</label>
                    <input v-model="searchValue" type="text" :placeholder="$t('Search...')" class="cvr-input px-3 py-2 rounded w-32" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                    <input v-model="dateFilters.startDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('End Date') }}</label>
                    <input v-model="dateFilters.endDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applyDateFilter" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Apply') }}</button>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('From Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('To Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Exchange Rate') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Reciprocal Exchange Rate') }}</th>
                            <th v-if="canUpdate || canDelete" class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(rate, index) in rates.data" :key="rate.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-muted">{{ rates.from + index }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ rate.date_formatted }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ rate.from_currency }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ rate.to_currency }}</td>
                            <td class="px-4 py-3 cvr-num">{{ rate.exchange_rate_formatted }}</td>
                            <td class="px-4 py-3 cvr-num">{{ rate.reciprocal_exchange_rate_formatted }}</td>
                            <td v-if="canUpdate || canDelete" class="px-4 py-3">
                                <div v-if="rate.is_editable" class="flex items-center gap-2">
                                    <RecordLogButton subject="ForeignExchangeRate" :id="rate.id" :company-id="company.id" />
                                    <a :href="rate.edit_url" class="cvr-action-btn" :title="$t('Edit')">✏️</a>
                                    <button @click="confirmDelete(rate)" class="cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rates.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No exchange rates found for this currency.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="rates.last_page > 1" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                <p class="text-xs cvr-text-muted">
                    Showing {{ rates.from }}–{{ rates.to }} of {{ rates.total }} rates
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="(link, i) in rates.links"
                        :key="i"
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                        v-html="link.label"
                    ></button>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Delete this exchange rate?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
