import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['IBM Plex Sans', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
                display: ['Outfit', 'system-ui', 'sans-serif'],
            },
            colors: {
                brand: {
                    DEFAULT: '#e11d48',
                    soft: '#fff1f2',
                    mid: '#fb7185',
                    dark: '#be123c',
                },
                sale: {
                    DEFAULT: '#0f766e',
                    dark: '#0d5f59',
                    soft: '#ccfbf1',
                    ink: '#0b1220',
                    mist: '#f1f5f9',
                    line: '#e2e8f0',
                },
            },
        },
    },

    plugins: [forms],
};
