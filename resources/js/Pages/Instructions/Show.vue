<script setup>
/**
 * Instructions/Show.vue
 * ------------------------------------------------------------------
 * Renders any screen's written guide. The content is data, supplied by
 * App\Support\Instructions\PageInstructions, so this one page serves
 * every guide and a new one needs no new component.
 *
 * Every string arrives as an English phrase and is rendered through
 * $t(), the same as the rest of the app, so the Arabic comes from
 * resources/lang/ar.json and the guide follows the language toggle.
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    company: Object,
    pageKey: String,
    title: String,
    summary: String,
    // [{ heading, body: [string], fields: [{label, text}], example, notes: [string] }]
    sections: { type: Array, default: () => [] },
    backUrl: String,
});
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-4xl">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                <span aria-hidden="true">{{ $i18n.locale === 'ar' ? '→' : '←' }}</span> {{ $t('Back') }}
            </Link>

            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t(title) }}</h1>
            <p class="text-sm cvr-text-secondary mb-6">{{ $t(summary) }}</p>

            <div v-for="(section, index) in sections" :key="index" class="cvr-card-bg cvr-border border rounded-lg p-4 mb-4">
                <h2 class="text-sm font-semibold cvr-text-primary uppercase tracking-wide mb-3">{{ $t(section.heading) }}</h2>

                <p v-for="(paragraph, p) in section.body || []" :key="'p' + p" class="text-sm cvr-text-secondary mb-3">
                    {{ $t(paragraph) }}
                </p>

                <!-- Field-by-field: what it is on the left, what it does on the right. -->
                <dl v-if="section.fields?.length" class="mb-3">
                    <div v-for="(field, f) in section.fields" :key="'f' + f" class="py-2 border-t cvr-border first:border-t-0">
                        <dt class="text-sm font-medium cvr-text-primary">{{ $t(field.label) }}</dt>
                        <dd class="text-sm cvr-text-secondary mt-0.5">{{ $t(field.text) }}</dd>
                        <dd v-if="field.example" class="text-xs cvr-text-muted mt-1">{{ $t(field.example) }}</dd>
                    </div>
                </dl>

                <!-- A worked example with real numbers, set apart so it is
                     findable when someone is stuck on a specific case. -->
                <div v-if="section.example" class="cvr-card-bg cvr-border border rounded p-3 mb-1">
                    <p class="text-xs font-semibold cvr-text-secondary uppercase tracking-wide mb-1">{{ $t('Example') }}</p>
                    <p class="text-sm cvr-text-secondary">{{ $t(section.example) }}</p>
                </div>

                <ul v-if="section.notes?.length" class="mt-2 space-y-2">
                    <li v-for="(note, n) in section.notes" :key="'n' + n" class="text-sm cvr-text-secondary flex gap-2">
                        <span class="cvr-text-muted shrink-0">•</span>
                        <span>{{ $t(note) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
