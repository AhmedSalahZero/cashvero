<script setup>
/**
 * Other Dues — non-invoice amounts owed either way with a partner.
 *
 * Shaped like the opening balance repeaters it sits beside: the whole
 * list is submitted at once, and rows that are removed are gone.
 *
 * Two behaviours are deliberate and easy to "helpfully" break later:
 *   - rows for the same partner are NEVER merged; each due keeps its own
 *     comment, which is the reason for recording them separately;
 *   - the partner list is fetched per row from the partner type, so two
 *     rows can be on different types at the same time.
 */
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    openingBalanceDate: String,
    rows: Array,
    directions: Array,
    partnerTypes: Array,
    currencies: Array,
    mainCurrency: String,
    partnersUrl: String,
    submitUrl: String,
    backUrl: String,
});

let nextRowId = 1;

function blankRow() {
    return {
        _rowId: nextRowId++,
        id: 0,
        direction: '',
        partner_type: '',
        partner_id: '',
        amount: '',
        currency: props.mainCurrency || '',
        exchange_rate: '',
        comment: '',
    };
}

const rows = ref(
    (props.rows || []).map(r => ({ ...blankRow(), ...r, _rowId: nextRowId++ }))
);

/* Partner options are cached per partner type, so switching a row back
   and forth does not refetch the same list. */
const partnersByType = ref({});
const loadingType = ref({});

async function loadPartners(type) {
    if (!type || partnersByType.value[type] || loadingType.value[type]) return;
    loadingType.value[type] = true;
    try {
        const res = await fetch(`${props.partnersUrl}?partner_type=${encodeURIComponent(type)}`, {
            headers: { Accept: 'application/json' },
        });
        partnersByType.value[type] = res.ok ? await res.json() : [];
    } catch {
        partnersByType.value[type] = [];
    } finally {
        loadingType.value[type] = false;
    }
}

// Pre-load the types already in use so saved rows show their name at once.
rows.value.forEach(r => loadPartners(r.partner_type));

function partnersFor(type) {
    return partnersByType.value[type] || [];
}

function onTypeChange(row) {
    // The chosen partner belongs to the previous type, so it cannot stay.
    row.partner_id = '';
    loadPartners(row.partner_type);
}

/* The rate is only meaningful away from the main currency; clearing it
   when the row returns to the main currency stops a stale rate being
   submitted with a row it no longer applies to. */
function needsRate(row) {
    return !!row.currency && row.currency !== props.mainCurrency;
}

watch(rows, (list) => {
    list.forEach(row => { if (!needsRate(row)) row.exchange_rate = ''; });
}, { deep: true });

function addRow() {
    rows.value.push(blankRow());
}

function removeRow(index) {
    rows.value.splice(index, 1);
}

const errors = ref({});
const processing = ref(false);

function errorFor(index, field) {
    return errors.value[`rows.${index}.${field}`];
}

const total = computed(() => {
    // Per currency, because amounts in different currencies must never be
    // added together.
    const byCurrency = {};
    rows.value.forEach(r => {
        const amount = parseFloat(r.amount);
        if (!r.currency || Number.isNaN(amount)) return;
        const sign = r.direction === 'due_to' ? -1 : 1;
        byCurrency[r.currency] = (byCurrency[r.currency] || 0) + sign * amount;
    });
    return byCurrency;
});

function submit() {
    processing.value = true;
    router.post(props.submitUrl, {
        rows: rows.value.map(r => ({
            direction: r.direction,
            partner_type: r.partner_type,
            partner_id: r.partner_id,
            amount: r.amount,
            currency: r.currency,
            exchange_rate: needsRate(r) ? r.exchange_rate : null,
            comment: r.comment,
        })),
    }, {
        preserveScroll: true,
        onError: (e) => { errors.value = e; },
        onSuccess: () => { errors.value = {}; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                <span aria-hidden="true">{{ $i18n.locale === 'ar' ? '→' : '←' }}</span> {{ $t('Back') }}
            </Link>

            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Other Dues') }}</h1>
            <p class="text-sm cvr-text-muted mb-4">
                {{ $t('Amounts owed either way that are not invoices. All of them are dated on the opening balance date:') }}
                <span class="font-semibold">{{ openingBalanceDate }}</span>
            </p>

            <div class="cvr-card rounded-lg p-4">
                <div v-if="!rows.length" class="text-center py-8 cvr-text-muted text-sm">
                    {{ $t('No other dues recorded yet.') }}
                </div>

                <div v-for="(row, index) in rows" :key="row._rowId" class="border rounded p-3 mb-3" style="border-color: var(--cvr-border);">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Direction') }}</label>
                            <select v-model="row.direction" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="d in directions" :key="d.value" :value="d.value">{{ d.label }}</option>
                            </select>
                            <p v-if="errorFor(index, 'direction')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'direction') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Partner Type') }}</label>
                            <select v-model="row.partner_type" @change="onTypeChange(row)" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="p in partnerTypes" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                            <p v-if="errorFor(index, 'partner_type')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'partner_type') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Name') }}</label>
                            <!-- The same searchable select the statements
                                 screens use: partner lists run to hundreds of
                                 names, so typing to filter is the only
                                 workable way to pick one. -->
                            <SearchableSelect
                                v-model="row.partner_id"
                                :options="partnersFor(row.partner_type)"
                                :disabled="!row.partner_type"
                                :placeholder="loadingType[row.partner_type] ? $t('Loading…') : $t('Search by name…')"
                            />
                            <p v-if="errorFor(index, 'partner_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'partner_id') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }}</label>
                            <input v-model="row.amount" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor(index, 'amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'amount') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }}</label>
                            <select v-model="row.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <p v-if="errorFor(index, 'currency')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'currency') }}</p>
                        </div>

                        <div v-if="needsRate(row)">
                            <label class="cvr-form-label">{{ $t('Exchange Rate') }}</label>
                            <input v-model="row.exchange_rate" type="number" step="0.000001" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor(index, 'exchange_rate')" class="text-xs mt-1 cvr-num-red">{{ errorFor(index, 'exchange_rate') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-end gap-3">
                        <div class="flex-1">
                            <label class="cvr-form-label">{{ $t('Comment') }}</label>
                            <input v-model="row.comment" type="text" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('Why this amount is owed — shown on the partner statement')" />
                        </div>
                        <button @click="removeRow(index)" class="cvr-btn-danger px-3 py-2 rounded text-sm">{{ $t('Remove') }}</button>
                    </div>
                </div>

                <button @click="addRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('+ Add Row') }}</button>

                <div v-if="Object.keys(total).length" class="mt-4 text-sm cvr-text-secondary">
                    <span class="font-semibold">{{ $t('Net by currency') }}:</span>
                    <span v-for="(amount, currency) in total" :key="currency" class="ms-3">
                        {{ currency }} {{ amount.toLocaleString() }}
                    </span>
                </div>

                <div class="mt-4">
                    <button @click="submit" :disabled="processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm">
                        {{ processing ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
