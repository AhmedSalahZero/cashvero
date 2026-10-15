<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    companies: Array, // [{id, name, image_url, go_url}]
});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ $t('Select a Company') }}</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a
                    v-for="company in companies"
                    :key="company.id"
                    :href="company.go_url"
                    class="cvr-card flex items-center gap-4 hover:opacity-90 transition"
                >
                    <img
                        v-if="company.image_url"
                        :src="company.image_url"
                        alt=""
                        class="w-16 h-16 object-cover rounded"
                    />
                    <span class="text-lg font-medium cvr-text-primary">{{ company.name }}</span>
                </a>
                <p v-if="companies.length === 0" class="cvr-text-muted">{{ $t('No companies found.') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
