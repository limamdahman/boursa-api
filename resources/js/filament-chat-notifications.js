// Notifications chat en temps réel pour Filament
document.addEventListener('DOMContentLoaded', () => {
    let originalTitle = document.title;
    let hasUnread = false;
    let audioContext = null;
    
    // Créer l'audio context pour le son
    function playNotificationSound() {
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Audio play error:', e));
        } catch (e) {
            console.log('Audio not supported');
        }
    }
    
    // Mettre à jour le badge dans la navigation
    function updateNavigationBadge(count) {
        // Chercher le badge existant
        const navItem = document.querySelector('a[href*="messages"]');
        if (!navItem) return;
        
        let badge = navItem.querySelector('.fi-sidebar-item-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'fi-sidebar-item-badge flex items-center justify-center gap-x-1 rounded-md bg-danger-600 px-2 py-0.5 text-xs font-medium text-white';
                navItem.querySelector('.fi-sidebar-item-label').after(badge);
            }
            badge.textContent = count > 9 ? '9+' : count;
            badge.style.display = 'flex';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }
    
    // Mettre à jour le titre de l'onglet
    function updateTitle(count) {
        if (count > 0 && document.hidden) {
            if (!hasUnread) {
                document.title = `(${count}) ${originalTitle}`;
                hasUnread = true;
                playNotificationSound();
            } else {
                document.title = `(${count}) ${originalTitle}`;
            }
        } else if (count === 0) {
            document.title = originalTitle;
            hasUnread = false;
        }
    }
    
    // Écouter les événements Livewire
    window.addEventListener('update-unread-badge', (event) => {
        const count = event.detail.count;
        updateNavigationBadge(count);
        updateTitle(count);
    });
    
    window.addEventListener('play-notification-sound', () => {
        if (document.hidden) {
            playNotificationSound();
        }
    });
    
    // Polling toutes les 5 secondes pour les nouveaux messages
    let lastMessageCount = 0;
    setInterval(async () => {
        try {
            const response = await fetch('/filament/api/unread-messages-count');
            if (response.ok) {
                const data = await response.json();
                if (data.count !== lastMessageCount && data.count > lastMessageCount && document.hidden) {
                    playNotificationSound();
                }
                lastMessageCount = data.count;
                updateNavigationBadge(data.count);
                updateTitle(data.count);
            }
        } catch (e) {
            console.log('Polling error:', e);
        }
    }, 5000);
});

// Écouter la visibilité de l'onglet
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        // Reset title when tab becomes visible
        document.title = originalTitle;
        hasUnread = false;
    }
});
