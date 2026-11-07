/**
 * Grafik SENTRA (Chart.js). Warna mengikuti token di app.css (gaya olive drab). Seri tunggal tanpa
 * legend; judul panel menamai serinya. Warna batang #9E8E58 (khaki gelap) lolos validator palet
 * dataviz terhadap permukaan panel #181D10 (L 0,48-0,67, kontras >= 3:1).
 * Teks memakai warna teks, bukan warna seri.
 */
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const SERIES = '#9E8E58';
const SERIES_HOVER = '#C9B57A';
const TEXT = '#CEC6A6';
const TEXT_MUTED = '#A69F80';
const GRID = '#343C26';
const SURFACE = '#181D10';
const FONT_MONO = '"Fira Code", ui-monospace, monospace';
const FONT_SANS = '"Saira Condensed", "Arial Narrow", ui-sans-serif, sans-serif';

Chart.defaults.font.family = FONT_SANS;

// Label selektif: hanya nilai puncak yang ditulis di atas batangnya; sisanya lewat sumbu, tooltip, tabel.
const labelPuncak = {
    id: 'labelPuncak',
    afterDatasetsDraw(chart) {
        const data = chart.data.datasets[0]?.data ?? [];
        const max = Math.max(0, ...data);
        if (max <= 0) return;
        const i = data.lastIndexOf(max);
        const bar = chart.getDatasetMeta(0).data[i];
        if (!bar) return;
        const { ctx } = chart;
        ctx.save();
        ctx.font = `600 12px ${FONT_MONO}`;
        ctx.fillStyle = TEXT;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.fillText(String(max), bar.x, bar.y - 4);
        ctx.restore();
    },
};

document.addEventListener('alpine:init', () => {
    // Objek Chart disimpan di closure, bukan di state Alpine: Proxy reaktif Alpine
    // membuat Chart.js berulang tanpa henti (Maximum call stack) saat data diperbarui.
    // Data dari Livewire juga disalin dulu agar tidak membawa Proxy.
    const salin = (v) => JSON.parse(JSON.stringify(v));

    window.Alpine.data('grafikEvent', (series) => {
        let chart = null;

        return {
        init() {
            const awal = salin(series);
            chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: { labels: awal.labels, datasets: [{ data: awal.counts }] },
                plugins: [labelPuncak],
                options: {
                    locale: 'id-ID',
                    layout: { padding: { top: 18 } },
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    datasets: {
                        bar: {
                            backgroundColor: SERIES,
                            hoverBackgroundColor: SERIES_HOVER,
                            maxBarThickness: 24,
                            borderRadius: { topLeft: 4, topRight: 4 },
                            borderSkipped: 'bottom',
                            borderColor: SURFACE,
                            borderWidth: { left: 1, right: 1 },
                            categoryPercentage: 0.9,
                            barPercentage: 1,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: GRID },
                            ticks: { color: TEXT_MUTED, maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 11, family: FONT_MONO } },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: GRID },
                            border: { display: false },
                            ticks: { color: TEXT_MUTED, precision: 0, maxTicksLimit: 5, font: { size: 11, family: FONT_MONO } },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#20271A',
                            borderColor: '#4A5134',
                            borderWidth: 1,
                            cornerRadius: 3,
                            padding: 10,
                            titleColor: TEXT_MUTED,
                            titleFont: { weight: '500', size: 12 },
                            bodyColor: '#ECE4C8',
                            bodyFont: { weight: '600', size: 14 },
                            displayColors: false,
                            callbacks: {
                                title: (items) => `Menit ${items[0].label} WIB`,
                                label: (item) => `${item.formattedValue} event`,
                            },
                        },
                    },
                },
            });
            // Livewire memperbarui data saat ada sinyal perubahan.
            this.$wire.$watch('series', (value) => {
                if (!chart) return;
                const baru = salin(value);
                chart.data.labels = baru.labels;
                chart.data.datasets[0].data = baru.counts;
                chart.update('none');
            });
        },
        destroy() {
            chart?.destroy();
            chart = null;
        },
        };
    });
});
