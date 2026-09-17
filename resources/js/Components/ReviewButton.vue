<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * ReviewButton
 * ==================================================================
 * زرار حالة المراجعة لحركة مالية واحدة ، و البوب اب بتاعه .
 *
 * حطه في عمود الإجراءات في أي صفحة حركات :
 *
 *   <ReviewButton
 *       movement="money-received"
 *       :id="row.id"
 *       :company-id="company.id"
 *       :state="row.review"
 *       :can-review="canReview" />
 *
 * `movement` هو المفتاح اللي في MovementReviewController::MOVEMENTS —
 * الراوت واحد لكل الأنواع ، فالصفحة مش محتاجة تعرف أي كونترولر
 * بيتعامل مع النوع ده و لا أي صلاحية بتحكمه .
 *
 * لو المستخدم مالوش صلاحية المراجعة بيشوف الحالة كعلامة من غير زرار :
 * إن الحركة اتراجعت معلومة تهمّ الكل ، لكن مين يقرر ده دور لوحده .
 */
const props = defineProps({
    movement: { type: String, required: true },
    id: { type: [Number, String], required: true },
    companyId: { type: [Number, String], required: true },
    /** { is_reviewed, reviewed_by_name, reviewed_at, review_comment } */
    state: { type: Object, default: () => ({}) },
    canReview: { type: Boolean, default: false },
});

const open = ref(false);
const comment = ref('');
const saving = ref(false);

const isReviewed = computed(() => Boolean(props.state?.is_reviewed));

/* لما تكون مراجَعة الضغطة معناها "افك المراجعة" و العكس */
const nextState = computed(() => ! isReviewed.value);

function openDialog() {
    if (! props.canReview) {
        return;
    }

    /* التعليق بيبدأ فاضي كل مرة : تعليق المراجعة السابقة سببه غير سبب
       دي ، و ملاه سلفا بيخلي الناس تسيبه زي ما هو من غير ما تقراه */
    comment.value = '';
    open.value = true;
}

function submit() {
    saving.value = true;

    router.patch(
        `/${props.companyId}/movement-review/${props.movement}/${props.id}`,
        { reviewed: nextState.value, comment: comment.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <span class="inline-flex items-center">
        <!-- اللي معاه صلاحية بيضغط ؛ اللي مالوش بيشوف الحالة بس -->
        <!-- نفس شكل باقي أزرار الإجراءات في الصف (cvr-action-btn) —
             أيقونة بحجم موحّد و الشرح في الـ tooltip ، مش زرار بنص و
             حدود وسط أيقونات -->
        <button
            v-if="canReview"
            type="button"
            @click="openDialog"
            class="cvr-action-btn"
            :title="isReviewed
                ? $t('Reviewed by :name', { name: state.reviewed_by_name || '' })
                : $t('Not reviewed yet')"
        >{{ isReviewed ? '✅' : '☑️' }}</button>

        <!-- اللي مالوش صلاحية بيشوف الحالة بس ، بنفس المقاس عشان
             الأعمدة ما تتزحلقش -->
        <span
            v-else
            class="cvr-action-btn cursor-default"
            :title="isReviewed ? $t('Reviewed by :name', { name: state.reviewed_by_name || '' }) : $t('Not reviewed yet')"
        >{{ isReviewed ? '✅' : '—' }}</span>

        <!-- البوب اب -->
        <teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 bg-black/60 flex items-center justify-center z-[70] p-4"
                @click.self="open = false"
            >
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">
                        {{ nextState ? $t('Mark as reviewed') : $t('Remove the review') }}
                    </h2>

                    <!-- اللي هيحصل بعد الحفظ ، مكتوب صراحة : القفل ده هو
                         نص الميزة و مش المفروض يبقى مفاجأة -->
                    <p class="text-sm cvr-text-muted mb-4">
                        {{ nextState
                            ? $t('Once reviewed, this movement can no longer be edited or deleted until the review is removed.')
                            : $t('Removing the review makes this movement editable and deletable again.') }}
                    </p>

                    <!-- حالة المراجعة الحالية ، لو فيه واحدة -->
                    <div v-if="isReviewed" class="cvr-card-bg cvr-border border rounded p-3 mb-4 text-sm">
                        <p class="cvr-text-secondary">
                            {{ $t('Reviewed by :name', { name: state.reviewed_by_name || '—' }) }}
                            <span v-if="state.reviewed_at" class="cvr-text-muted"> · {{ state.reviewed_at }}</span>
                        </p>
                        <p v-if="state.review_comment" class="cvr-text-muted mt-1">
                            {{ $t('Comment') }}: {{ state.review_comment }}
                        </p>
                    </div>

                    <label class="cvr-form-label">{{ $t('Comment') }} <span class="cvr-text-muted">({{ $t('optional') }})</span></label>
                    <textarea
                        v-model="comment"
                        rows="3"
                        maxlength="1000"
                        class="cvr-input w-full px-3 py-2 rounded mb-1"
                        :placeholder="$t('Why are you reviewing this?')"
                    ></textarea>
                    <p class="text-xs cvr-text-muted mb-4">{{ $t('This is recorded in the record log.') }}</p>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="open = false"
                            class="cvr-btn-secondary px-4 py-2 rounded border text-sm"
                        >{{ $t('Cancel') }}</button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="saving"
                            class="px-4 py-2 rounded text-sm"
                            :class="[nextState ? 'cvr-btn-primary' : 'cvr-btn-danger', saving ? 'opacity-50 cursor-not-allowed' : '']"
                        >{{ nextState ? $t('Mark as reviewed') : $t('Remove the review') }}</button>
                    </div>
                </div>
            </div>
        </teleport>
    </span>
</template>
