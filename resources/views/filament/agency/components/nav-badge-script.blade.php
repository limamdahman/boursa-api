<script>
(function () {
    let prevCount = 0;

    function playNotif() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.frequency.value = 520; g.gain.value = 0.08;
            o.start(); o.stop(ctx.currentTime + 0.12);
        } catch (e) {}
    }

    function applyBadge(count) {
        // Cherche le lien Messages dans la nav
        document.querySelectorAll('.fi-sidebar-item-btn').forEach(btn => {
            if (!btn.href || !btn.href.includes('/agence/messages')) return;

            btn.style.position = 'relative';

            // Supprime ancien badge custom
            const old = btn.querySelector('.boursa-nav-badge');
            if (old) old.remove();

            if (count > 0) {
                const badge = document.createElement('span');
                badge.className = 'boursa-nav-badge';
                badge.textContent = count > 9 ? '9+' : count;
                badge.style.cssText = [
                    'position:absolute',
                    'top:-4px',
                    'right:-4px',
                    'background:#EF4444',
                    'color:white',
                    'border-radius:9999px',
                    'font-size:10px',
                    'font-weight:700',
                    'padding:2px 5px',
                    'min-width:16px',
                    'height:16px',
                    'display:flex',
                    'align-items:center',
                    'justify-content:center',
                    'border:2px solid white',
                    'z-index:50',
                    'pointer-events:none',
                ].join(';');
                btn.appendChild(badge);
            }
        });
    }

    async function pollUnread() {
        try {
            const res = await fetch(window.location.origin + '/agence/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const count = data.count || 0;
            if (count > prevCount) playNotif();
            prevCount = count;
            applyBadge(count);
        } catch (e) {}
    }

    // Attend que le DOM soit prêt (Alpine peut être lent)
    function init() {
        pollUnread();
        setInterval(pollUnread, 5000);
        document.addEventListener('livewire:updated', () => applyBadge(prevCount));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Petit délai pour laisser Alpine initialiser la nav
        setTimeout(init, 500);
    }
})();
</script>
