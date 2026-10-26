import { createI18n } from 'vue-i18n';
import en from './en';
import bn from './bn';

export const i18n = createI18n({
    legacy: false,
    locale: localStorage.getItem('locale') || 'bn',
    fallbackLocale: 'en',
    messages: { en, bn },
});
