import forms from '@tailwindcss/forms';

// Token warna didefinisikan sekali sebagai variabel CSS (resources/css/app.css)
// dan dipetakan di sini, sehingga modifier opasitas Tailwind tetap jalan
// (mis. bg-awas/15). Rincian dan alasan: docs/design-system.md.
const token = (nama) => `rgb(var(--${nama}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    // Mode gelap permanen lewat class="dark" pada <html>.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        // Kelas warna Tingkat Siaga/severity didefinisikan di enum PHP; lencana
        // status dan sorotan kata di beberapa komponen Livewire.
        './app/Enums/**/*.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                // 'canvas', bukan 'base': nama 'base' bentrok dengan utilitas ukuran huruf text-base.
                canvas: token('base'),
                panel: token('panel'),
                raised: token('raised'),
                inset: token('inset'),
                line: { DEFAULT: token('line'), strong: token('line-strong'), control: token('line-control') },
                ink: { DEFAULT: token('ink'), 2: token('ink-2'), 3: token('ink-3') },
                accent: { DEFAULT: token('accent'), ink: token('accent-ink'), strong: token('accent-strong') },
                // Tingkat Siaga satuan: Aman, Waspada, Siaga, Awas. Severity: Rendah (pasir), Sedang (= waspada),
                // Tinggi (= siaga), Kritis (= awas).
                pasir: token('pasir'),
                aman: token('aman'),
                waspada: token('waspada'),
                siaga: token('siaga'),
                awas: token('awas'),
                // Netral Tailwind dipetakan ke netral olive/krem SENTRA, agar sisa kelas gray ikut serasi
                // dan teks redup tetap lolos WCAG AA.
                gray: {
                    50: '#F6F1E1', 100: '#ECE4C8', 200: '#DED6B9', 300: '#CEC6A6', 400: '#B8B191',
                    500: '#A69F80', 600: '#6E6A50', 700: '#4A5134', 800: '#343C26', 900: '#181D10', 950: '#0E110A',
                },
            },
            fontFamily: {
                // Dibundel lokal (fontsource, OFL-1.1): tidak ada unduhan font dari internet.
                // sans = isi (Saira Condensed), display = judul/angka (Saira Condensed tebal),
                // stensil = judul militer (Saira Stencil One), mono = data teknis (Fira Code).
                sans: ['"Saira Condensed"', '"Arial Narrow"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"Saira Condensed"', '"Arial Narrow"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                stensil: ['"Saira Stencil One"', '"Saira Condensed"', 'Impact', 'sans-serif'],
                mono: ['"Fira Code"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
            },
            borderRadius: {
                // Militer: sudut nyaris tajam. Dog-tag tetap membulat.
                chip: '2px',
                control: '3px',
                panel: '4px',
            },
            boxShadow: {
                // Hanya untuk lapisan melayang (dropdown, dialog). Panel cukup bergaris.
                overlay: '0 10px 30px -8px rgb(0 0 0 / 0.6), 0 0 0 1px rgb(var(--line-strong) / 1)',
            },
            transitionTimingFunction: {
                sentra: 'cubic-bezier(.2, .8, .2, 1)',
            },
            keyframes: {
                'denyut': {
                    '0%': { boxShadow: '0 0 0 0 rgb(var(--denyut, var(--aman)) / 0.55)' },
                    '70%': { boxShadow: '0 0 0 6px rgb(var(--denyut, var(--aman)) / 0)' },
                    '100%': { boxShadow: '0 0 0 0 rgb(var(--denyut, var(--aman)) / 0)' },
                },
                'masuk': {
                    '0%': { opacity: '0', transform: 'translateY(-4px)', backgroundColor: 'rgb(var(--accent) / 0.16)' },
                    '40%': { opacity: '1', transform: 'translateY(0)' },
                    '100%': { backgroundColor: 'rgb(var(--accent) / 0)' },
                },
                'sorot': {
                    '0%': { color: 'rgb(var(--accent))' },
                    '100%': { color: 'inherit' },
                },
                'kilau': {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'sapu': {
                    '0%': { transform: 'rotate(0deg)' },
                    '100%': { transform: 'rotate(360deg)' },
                },
                'serbu': {
                    '0%': { strokeDashoffset: '24' },
                    '100%': { strokeDashoffset: '0' },
                },
            },
            animation: {
                denyut: 'denyut 1.8s cubic-bezier(.2,.8,.2,1) infinite',
                masuk: 'masuk 1.4s cubic-bezier(.2,.8,.2,1) both',
                sorot: 'sorot 1.2s ease-out',
                kilau: 'kilau 1.6s linear infinite',
                sapu: 'sapu 9s linear infinite',
                serbu: 'serbu 1.2s linear infinite',
            },
        },
    },

    plugins: [forms],
};
