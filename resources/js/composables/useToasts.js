import { ref } from 'vue';

/**
 * قائمة التوستات مشتركة على مستوى الموديول ، مش جوه كل نسخة من AppLayout.
 *
 * AppLayout مش layout ثابت في إينرشيا — كل صفحة بتعمل <AppLayout> بتاعها ،
 * يعني كل تنقّل بيهدّ نسخة ويبني نسخة جديدة. وحدث `flash` بيتنفّذ وقت ما
 * الرد بيتطبّق ، وده ممكن يكون قبل ما النسخة الجديدة تتولد أصلاً. لما كانت
 * القائمة جوه الكومبوننت ، التوست كان بيتحط في النسخة اللي بتتهد وبيروح
 * معاها — وده نص المشكلة اللي المستخدم شافها: "الرسالة مش بتظهر إلا لما
 * أعمل reload".
 *
 * بالموديول سكوب ، أي نسخة شغالة على الشاشة بتعرض نفس القائمة ، والتوكن
 * اللي اتعالج مرة ما بيتعرضش تاني من النسخة اللي بعدها.
 */
const toasts = ref([]);
let toastSeed = 0;
let lastHandledToken = null;

export function useToasts() {
    function pushToast(type, message, duration = 5000) {
        const id = ++toastSeed;
        toasts.value.push({ id, type, message, duration });
        setTimeout(() => dismissToast(id), duration);
    }

    function dismissToast(id) {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }

    /**
     * بيتنده من قناتين (حدث `flash` بتاع إينرشيا و prop اسمه flash) ،
     * فالتوكن هو اللي بيمنع إن الرسالة الواحدة تتعرض مرتين.
     */
    function handleFlashPayload(flash) {
        if (!flash) return;
        if (!flash.success && !flash.error) return;

        const token = flash.token || `${flash.success || ''}|${flash.error || ''}`;
        if (token && token === lastHandledToken) return;
        lastHandledToken = token;

        if (flash.success) pushToast('success', flash.success);
        if (flash.error) pushToast('error', flash.error);
    }

    return { toasts, pushToast, dismissToast, handleFlashPayload };
}
