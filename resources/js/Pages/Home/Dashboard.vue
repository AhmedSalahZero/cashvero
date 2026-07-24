<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    company: Object,
    hasCashvero: Boolean,
    uploadCustomerInvoicesUrl: String,
    uploadSupplierInvoicesUrl: String,
    goToCashVeroUrl: String,
});

const expanded = ref(true);
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">Where You Want To Go:</h1>

            <div v-if="hasCashvero" class="cvr-card">
                <button
                    type="button"
                    @click="expanded = !expanded"
                    class="w-full flex items-center justify-between text-left"
                >
                    <span class="font-semibold cvr-text-primary">Cash Vero</span>
                    <span class="cvr-text-muted">{{ expanded ? '▾' : '▸' }}</span>
                </button>

                <div v-if="expanded" class="mt-4 space-y-2 pl-2">
                    <Link :href="uploadCustomerInvoicesUrl" class="cvr-btn-secondary inline-flex items-center px-3 py-1.5 rounded border text-sm w-full">
                        Upload Customer Invoices
                    </Link>
                    <Link :href="uploadSupplierInvoicesUrl" class="cvr-btn-secondary inline-flex items-center px-3 py-1.5 rounded border text-sm w-full">
                        Upload Supplier Invoices
                    </Link>
                    <Link :href="goToCashVeroUrl" class="cvr-btn-primary inline-flex items-center px-3 py-1.5 rounded text-sm w-full">
                        Go To Cash Vero
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
