/**
 * Dashboard live SENTRA (FR-07): WebSocket Laravel Reverb dengan cadangan
 * polling tiap 3 detik.
 *
 * Siaran hanya berisi sinyal "ada perubahan". Setiap komponen Livewire yang
 * mendengarkan 'sentra-refresh' mengambil ulang datanya sendiri sesuai hak
 * akses pengguna, jadi data satuan lain tidak pernah lewat WebSocket.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const POLL_MS = 3000;
let mode = 'polling';
let pending = null;

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.content || '';
}

function refresh() {
    if (window.Livewire) {
        window.Livewire.dispatch('sentra-refresh');
    }
}

// Satukan rentetan sinyal (mis. saat pemindaian) menjadi satu pembaruan per 500 ms.
function refreshSoon() {
    if (pending) return;
    pending = setTimeout(() => {
        pending = null;
        refresh();
    }, 500);
}

function setMode(next) {
    mode = next;
    document.querySelectorAll('[data-sentra-live]').forEach((el) => {
        el.dataset.mode = next;
        el.textContent = next === 'live' ? 'Live' : 'Polling';
        el.title = next === 'live'
            ? 'Pembaruan langsung lewat WebSocket Laravel Reverb'
            : 'WebSocket tidak tersambung; dashboard diperbarui tiap 3 detik';
    });
}

function disconnect() {
    if (window.Echo) {
        window.Echo.disconnect();
        window.Echo = undefined;
    }
    setMode('polling');
}

function connect() {
    const key = meta('reverb-key');
    if (!key || !meta('sentra-user')) {
        // Halaman tamu (login) atau setelah logout: jangan ada koneksi.
        disconnect();
        return;
    }
    if (window.Echo) {
        return;
    }
    const https = window.location.protocol === 'https:';
    const port = Number(window.location.port) || (https ? 443 : 80);
    try {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: https,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });
        window.Echo.private('sentra.dashboard').listen('.perubahan', refreshSoon);
        window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
            setMode(current === 'connected' ? 'live' : 'polling');
            if (current === 'connected') refreshSoon();
        });
    } catch (e) {
        console.warn('SENTRA: WebSocket tidak tersedia, memakai polling 3 detik.', e);
        setMode('polling');
    }
}

document.addEventListener('DOMContentLoaded', () => setMode(mode));
// Login/logout dan pindah halaman memakai navigasi SPA Livewire (wire:navigate):
// modul ini tidak dijalankan ulang, jadi koneksi dicek setiap selesai navigasi.
document.addEventListener('livewire:navigated', () => {
    connect();
    setMode(mode);
});
setInterval(() => {
    if (mode !== 'live') refresh();
}, POLL_MS);
connect();
