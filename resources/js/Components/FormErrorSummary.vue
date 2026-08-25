<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * FormErrorSummary
 * ==================================================================
 * Lists what actually failed validation.
 *
 * ⚠️ Replaces a banner that read "Please fix the highlighted field(s)
 * below before saving" and highlighted nothing. Thirty forms carried
 * it, and none of them rendered per-field messages for more than a
 * couple of inputs — so a failure on any other field produced a
 * warning with no visible cause and no way to find it. Reported on the
 * LC Issuance form, where validation raises nine keys and only three
 * are displayed anywhere.
 *
 * Some keys can never map to a field at all: rule sets here use
 * synthetic names (`lc_facility_room`, `medium_term_loan_room`,
 * `net_balance_rules`) to attach a cross-field check. Those are exactly
 * the errors a per-field-only design can never show, which is why this
 * lists messages rather than trying harder to highlight inputs.
 */
const props = defineProps({
    /**
     * Field name → readable label, for the keys worth naming. Anything
     * missing falls back to a humanised form of the key, and synthetic
     * keys are shown without a label at all.
     */
    labels: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Please fix the following before saving:' },
    /**
     * Keys this form already shows in a dedicated banner of its own.
     * Listing them here keeps the same failure from being reported
     * twice — Cash Expense does this for its balance error.
     */
    except: { type: Array, default: () => [] },
});

const page = usePage();

/**
 * Laravel sends `{ field: "message" }` through Inertia, but a nested
 * rule can send an array. Flatten either shape to one line per message.
 */
const items = computed(() => {
    const errors = page.props.errors || {};

    return Object.entries(errors)
        .filter(([field]) => !props.except.includes(field))
        .flatMap(([field, message]) => {
        const messages = Array.isArray(message) ? message : [message];

        return messages.filter(Boolean).map((text) => ({
            key: `${field}:${text}`,
            label: labelFor(field, text),
            text,
        }));
    });
});

function labelFor(field, text) {
    if (props.labels[field]) return props.labels[field];

    /**
     * A synthetic key gets no label. Laravel builds its default message
     * from the attribute name, so a rule keyed `lc_facility_room`
     * already says what it means in the message itself — prefixing it
     * with "Lc Facility Room" would only add noise.
     */
    const humanised = field
        .replace(/\.\d+/g, '')
        .replace(/_id$/, '')
        .replace(/[._]/g, ' ')
        .trim();

    // If the message already opens with the humanised field name,
    // showing it again as a label just repeats it.
    if (text && text.toLowerCase().startsWith(humanised.toLowerCase())) return null;

    return humanised.replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
    <div v-if="items.length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
        <p class="font-semibold mb-1">{{ $t(title) }}</p>
        <ul class="list-disc ps-5 space-y-0.5">
            <li v-for="item in items" :key="item.key">
                <span v-if="item.label" class="font-medium">{{ item.label }}:</span>
                {{ item.text }}
            </li>
        </ul>
    </div>
</template>
