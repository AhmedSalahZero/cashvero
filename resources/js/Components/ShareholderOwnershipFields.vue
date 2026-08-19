<script setup>
import { computed } from 'vue';

/**
 * ShareholderOwnershipFields
 * ==================================================================
 * The "who owns this account?" control, shared by the four instrument
 * forms that can belong to a shareholder personally rather than to the
 * company: Current Account, Time Deposit, Certificate of Deposit and
 * Medium Term Loan.
 *
 * See docs/shareholder-accounts.md.
 *
 *  • Renders NOTHING at all when the user lacks
 *    `shareholder_account.view` (decision D6) — the backend prohibits
 *    the fields for those users too, so hiding here is presentation,
 *    not the guarantee.
 *  • Picking "Shareholder" reveals the owner select; switching back to
 *    "Company" clears the owner id, so an unticked account can never
 *    keep a stale owner (the backend normalises the same way).
 *
 * Bound with v-model:isShareholderAccount / v-model:shareholderPartnerId
 * so each form keeps its own form-state object.
 */
const props = defineProps({
    /** From the controller — ShareholderAccountAccess::formProps() */
    canManage: { type: Boolean, default: false },
    shareholders: { type: Array, default: () => [] },

    isShareholderAccount: { type: Boolean, default: false },
    shareholderPartnerId: { type: [Number, String, null], default: null },

    /** Server-side validation messages, if the parent has them handy. */
    ownerError: { type: String, default: null },

    /** Shown under the owner select — MTL passes its D1 caveat here. */
    hint: { type: String, default: null },

    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:isShareholderAccount', 'update:shareholderPartnerId']);

const ownerValue = computed(() => (props.isShareholderAccount ? 'shareholder' : 'company'));

function onOwnerChange(event) {
    const isShareholder = event.target.value === 'shareholder';
    emit('update:isShareholderAccount', isShareholder);

    // Company accounts never carry an owner id.
    if (!isShareholder) {
        emit('update:shareholderPartnerId', null);
    }
}

function onShareholderChange(event) {
    const raw = event.target.value;
    emit('update:shareholderPartnerId', raw === '' ? null : Number(raw));
}

const hasNoShareholders = computed(() => props.shareholders.length === 0);
</script>

<template>
    <template v-if="canManage">
        <div>
            <label class="cvr-form-label">Account Owner *</label>
            <select
                :value="ownerValue"
                :disabled="disabled"
                @change="onOwnerChange"
                class="cvr-select w-full px-2 py-1.5 rounded text-sm"
            >
                <option value="company">Company</option>
                <option value="shareholder">Shareholder</option>
            </select>
        </div>

        <div v-if="isShareholderAccount">
            <label class="cvr-form-label">Shareholder *</label>
            <select
                :value="shareholderPartnerId ?? ''"
                :disabled="disabled || hasNoShareholders"
                @change="onShareholderChange"
                class="cvr-select w-full px-2 py-1.5 rounded text-sm"
                :class="{ 'border-2': ownerError }"
                :style="ownerError ? { borderColor: 'var(--cvr-danger)' } : {}"
            >
                <option value="" disabled>Select</option>
                <option v-for="shareholder in shareholders" :key="shareholder.id" :value="shareholder.id">
                    {{ shareholder.name }}
                </option>
            </select>
            <p v-if="hasNoShareholders" class="text-xs mt-1 cvr-text-muted">
                No shareholders exist for this company yet — add one from the Partners screen first.
            </p>
            <p v-else-if="ownerError" class="text-xs mt-1" style="color: var(--cvr-danger-text);">
                {{ ownerError }}
            </p>
            <p v-else-if="hint" class="text-xs mt-1 cvr-text-muted">{{ hint }}</p>
        </div>
    </template>
</template>
