import russian from '../lang/ru.json';
import cyrillic from '../lang/uz_Cyrl.json';
export const t = text => ({ ru: russian, 'uz-Cyrl': cyrillic }[document.documentElement.lang]?.[text] ?? text);
