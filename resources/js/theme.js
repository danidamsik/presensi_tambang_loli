import { computed, ref } from 'vue';

const THEME_STORAGE_KEY = 'theme';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';

const theme = ref(THEME_LIGHT);
let initialized = false;
let mediaListenerRegistered = false;

const isValidTheme = (value) => value === THEME_DARK || value === THEME_LIGHT;

const getStoredTheme = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.localStorage.getItem(THEME_STORAGE_KEY);
};

const getSystemTheme = () => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return THEME_LIGHT;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? THEME_DARK : THEME_LIGHT;
};

const applyThemeToDocument = (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = value === THEME_DARK;
    document.documentElement.classList.toggle(THEME_DARK, isDark);
    document.documentElement.classList.toggle(THEME_LIGHT, !isDark);
    document.documentElement.style.colorScheme = isDark ? THEME_DARK : THEME_LIGHT;
};

const persistTheme = (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(THEME_STORAGE_KEY, value);
};

const registerSystemThemeListener = () => {
    if (mediaListenerRegistered || typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return;
    }

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (event) => {
        const storedTheme = getStoredTheme();

        if (isValidTheme(storedTheme)) {
            return;
        }

        theme.value = event.matches ? THEME_DARK : THEME_LIGHT;
        applyThemeToDocument(theme.value);
    };

    if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', handler);
    } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(handler);
    }

    mediaListenerRegistered = true;
};

const initializeTheme = () => {
    if (initialized) {
        return theme.value;
    }

    const storedTheme = getStoredTheme();
    theme.value = isValidTheme(storedTheme) ? storedTheme : getSystemTheme();
    applyThemeToDocument(theme.value);
    registerSystemThemeListener();
    initialized = true;

    return theme.value;
};

const setTheme = (value) => {
    if (!isValidTheme(value)) {
        return;
    }

    theme.value = value;
    persistTheme(value);
    applyThemeToDocument(value);
};

const toggleTheme = () => {
    setTheme(theme.value === THEME_DARK ? THEME_LIGHT : THEME_DARK);
};

const useTheme = () => {
    initializeTheme();

    return {
        theme,
        isDark: computed(() => theme.value === THEME_DARK),
        setTheme,
        toggleTheme,
        initializeTheme,
    };
};

export { useTheme, initializeTheme, THEME_DARK, THEME_LIGHT };
