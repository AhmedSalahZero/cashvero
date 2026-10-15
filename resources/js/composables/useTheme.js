// resources/js/composables/useTheme.js
import { ref, onMounted } from 'vue';

const THEME_KEY = 'cashvero-theme';

export function useTheme() {
    const theme = ref('dark'); // dark is CashVero's default

    function applyTheme(value) {
        document.documentElement.setAttribute('data-theme', value === 'light' ? 'light' : 'dark');
        localStorage.setItem(THEME_KEY, value);
        theme.value = value;
    }

    function toggleTheme() {
        applyTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    onMounted(() => {
        const saved = localStorage.getItem(THEME_KEY);
        applyTheme(saved || 'dark');
    });

    return { theme, toggleTheme, applyTheme };
}