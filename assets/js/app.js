/**
 * ELINA — Main Application JavaScript
 */

// ---- Dark Mode (runs immediately to prevent flash) ----
(function () {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();

function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateDarkModeUI(newTheme);
}

function updateDarkModeUI(theme) {
    const icon = document.getElementById('darkModeIcon');
    const label = document.getElementById('darkModeLabel');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    if (label) {
        label.textContent = theme === 'dark' ? 'Mode Terang' : 'Mode Gelap';
    }
}

// Update UI on load
document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateDarkModeUI(currentTheme);
});

// ---- Live Clock ----
function updateClock() {
    const now = new Date();
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}
setInterval(updateClock, 1000);
updateClock();

// ---- Sidebar Toggle ----
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    }
}

// ---- Auto-dismiss Flash Alert ----
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashAlert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(() => flash.remove(), 300);
        }, 4000);
    }
});

// ---- Modal Helpers ----
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// ---- Geolocation Helper ----
function getCurrentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation tidak didukung browser ini.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                accuracy: pos.coords.accuracy
            }),
            (err) => {
                let msg = 'Gagal mendapatkan lokasi.';
                switch (err.code) {
                    case 1: msg = 'Akses lokasi ditolak. Izinkan di pengaturan browser.'; break;
                    case 2: msg = 'Lokasi tidak tersedia. Pastikan GPS aktif.'; break;
                    case 3: msg = 'Pengambilan lokasi timeout.'; break;
                }
                reject(new Error(msg));
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });
}

// ---- Haversine Distance (meters) ----
function haversineDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLng / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

// ---- Fetch Helper ----
async function apiFetch(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            ...options
        });
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: error.message };
    }
}

// ---- Confirm Dialog ----
function confirmAction(message) {
    return confirm(message);
}

// ---- Format Number ----
function formatDistance(meters) {
    if (meters < 1000) return Math.round(meters) + ' m';
    return (meters / 1000).toFixed(1) + ' km';
}

// ---- Register Service Worker (PWA) ----
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/ELINA/sw.js')
            .then(registration => {
                console.log('ServiceWorker registered with scope:', registration.scope);
            })
            .catch(error => {
                console.error('ServiceWorker registration failed:', error);
            });
    });
}

// ---- PWA Install Prompt ----
let pwaPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pwaPrompt = e;
    // Show the install button if exists
    const btn = document.getElementById('pwaInstallBtn');
    if (btn) btn.style.display = 'flex';
});

async function installPWA() {
    if (!pwaPrompt) return;
    pwaPrompt.prompt();
    const { outcome } = await pwaPrompt.userChoice;
    if (outcome === 'accepted') {
        const btn = document.getElementById('pwaInstallBtn');
        if (btn) btn.style.display = 'none';
    }
    pwaPrompt = null;
}

// ---- Live Notifications (WebAuth & API Polling) ----
async function initLiveNotifications() {
    // Only run if logged in (we assume logged in if sidebar exists)
    if (!document.getElementById('sidebar')) return;

    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Initialize tracking ID if missing
    if (!localStorage.getItem('elina_last_notif_id')) {
        try {
            const initRes = await apiFetch(`/ELINA/api/check_notif.php?init=1`);
            if (initRes.success && initRes.max_id !== undefined) {
                localStorage.setItem('elina_last_notif_id', initRes.max_id.toString());
            } else {
                localStorage.setItem('elina_last_notif_id', "0");
            }
        } catch (e) {
            localStorage.setItem('elina_last_notif_id', "0");
        }
    }

    setInterval(async () => {
        const lastId = localStorage.getItem('elina_last_notif_id') || 0;
        try {
            const res = await apiFetch(`/ELINA/api/check_notif.php?last_id=${lastId}`);
            if (res.success && res.data && res.data.length > 0) {
                let maxId = parseInt(lastId);
                res.data.forEach(notif => {
                    if (parseInt(notif.id) > maxId) maxId = parseInt(notif.id);
                    
                    // Show Native Notification
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Pemberitahuan ELINA', {
                            body: notif.pesan,
                            icon: '/ELINA/assets/img/logo2.png'
                        });
                    }
                });
                localStorage.setItem('elina_last_notif_id', maxId.toString());

                if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            }
        } catch (e) {
            // Silently ignore
        }
    }, 15000);
}

document.addEventListener('DOMContentLoaded', initLiveNotifications);
