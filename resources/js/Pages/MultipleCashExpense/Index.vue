<script setup>
/**
 * Multiple Cash Expenses — list.
 *
 * The ℹ️ button opens the breakdown of the lines that made up the payment,
 * because the row itself can only show the total. The lines travel with the
 * row (there are only a few per payment and they are eager loaded), so
 * opening the popup costs no extra request.
 */
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    filters: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    createUrl: String,
    indexUrl: String,
});

const search = ref(props.filters.search || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

const breakdownTarget = ref(null);
const deleteTarget = ref(null);

function go(overrides = {}) {
    router.get(props.indexUrl, {
        search: search.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        ...overrides,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => go({ page: 1 }), 350);
});

function confirmDelete(row) {
    deleteTarget.value = row;
}

function performDelete() {
    router.delete(deleteTarget.value.delete_url, {
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout :title="$t('Multiple Cash Expenses')">
        <div class="p-4 md:p-6">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                <h1 class="text-lg font-medium cvr-text-primary">{{ $t('Multiple Cash Expenses') }}</h1>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm">
                    + {{ $t('Create') }}
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4 flex items-end gap-3 flex-wrap">
                <div class="flex-1 min-w-[12rem]">
                    <label class="cvr-form-label">{{ $t('Search') }}</label>
                    <input v-model="search" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('From') }}</label>
                    <input v-model="from" type="date" @change="go({ page: 1 })" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('To') }}</label>
                    <input v-model="to" type="date" @change="go({ page: 1 })" class="cvr-input px-3 py-2 rounded" />
                </div>
            </div>

            <div v-if="rows.length === 0" class="py-10 text-center cvr-text-muted">
                {{ $t('No Data Found') }}
            </div>

            <div v-else class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="cvr-table-head">
                            <th class="px-3 py-2 text-start">{{ $t('Payment Date') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Type') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Supplier Name') }}</th>
                            <th class="px-3 py-2 text-end">{{ $t('Expenses') }}</th>
                            <th class="px-3 py-2 text-end">{{ $t('Total') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-2 whitespace-nowrap">{{ row.payment_date }}</td>
                            <td class="px-3 py-2">{{ row.type }}</td>
                            <td class="px-3 py-2">{{ row.supplier_name || '-' }}</td>
                            <td class="px-3 py-2 text-end">{{ row.items_count }}</td>
                            <td class="px-3 py-2 text-end">{{ row.total }} {{ row.currency }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <!-- The breakdown of the lines behind this total -->
                                    <button @click="breakdownTarget = row" class="cvr-action-btn" :title="$t('Expenses Breakdown')">ℹ️</button>
                                    <Link v-if="row.edit_url" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✏️</Link>
                                    <button v-if="row.delete_url" @click="confirmDelete(row)" class="cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-3 flex-wrap mt-4">
                <p class="text-sm cvr-text-muted">
                    {{ $t('Showing') }} {{ pagination.from }}–{{ pagination.to }} {{ $t('of') }} {{ pagination.total }}
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <Link
                        v-for="(link, i) in pagination.links"
                        :key="i"
                        :href="link.url || ''"
                        :class="['px-2.5 py-1 rounded text-sm border', link.active ? 'cvr-btn-copper' : 'cvr-btn-secondary', !link.url ? 'opacity-40 pointer-events-none' : '']"
                        preserve-scroll
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Breakdown popup -->
        <teleport to="body">
            <div v-if="breakdownTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="breakdownTarget = null">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">{{ $t('Expenses Breakdown') }}</h2>
                        <button @click="breakdownTarget = null" class="cvr-action-btn">✖️</button>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="cvr-table-head">
                                <th class="px-3 py-2 text-start">{{ $t('Expense Category') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Expense Name') }}</th>
                                <th class="px-3 py-2 text-end">{{ $t('Paid Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in breakdownTarget.items" :key="i" class="cvr-table-row">
                                <td class="px-3 py-2">{{ item.category }}</td>
                                <td class="px-3 py-2">{{ item.name }}</td>
                                <td class="px-3 py-2 text-end">{{ item.amount }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="cvr-table-head">
                                <th class="px-3 py-2 text-end" colspan="2">{{ $t('Total') }}</th>
                                <th class="px-3 py-2 text-end">{{ breakdownTarget.total }} {{ breakdownTarget.currency }}</th>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="mt-3 text-xs cvr-text-muted">
                        {{ $t('Each line above appears on the statement on its own.') }}
                    </p>

                    <div class="flex justify-end mt-4">
                        <button @click="breakdownTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Delete confirmation -->
        <teleport to="body">
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="deleteTarget = null">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md mx-4">
                    <p class="cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="performDelete" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </teleport>
    </AppLayout>
</template>
