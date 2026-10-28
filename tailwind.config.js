import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Brand palette comes from CSS variables in resources/css/app.css (change colours there only)
const brand = Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((s) => [s, `rgb(var(--brand-${s}) / <alpha-value>)`]),
);
const accent = Object.fromEntries(
    [50, 100, 400, 500, 600].map((s) => [s, `rgb(var(--accent-${s}) / <alpha-value>)`]),
);
// Gray also from variables: dark mode just swaps them (html.dark in app.css)
const gray = Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((s) => [s, `rgb(var(--gray-${s}) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Enums/*.php', // status badge colors
        './app/Http/Controllers/*.php', // status colors built in controllers
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: { brand, accent, gray },
        },
    },

    plugins: [forms],
};
