// Notifications chat en temps réel pour Filament
document.addEventListener('DOMContentLoaded', () => {
    let originalTitle = document.title;
    let hasUnread = false;
    
    // Fonction pour jouer le son
    function playNotificationSound() {
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Audio play error:', e));
        } catch (e) {
            console.log('Audio not supported');
        }
    }
    
    // Fonction pour mettre à jour le badge dans la navigation
    function updateNavigationBadge(count) {
        // Chercher l'élément de navigation messages
        const navItems = document.querySelectorAll('.fi-sidebar-item');
        let messagesNav = null;
        
        navItems.forEach(item => {
            const link = item.querySelector('a');
            if (link && link.getAttribute('href') && link.getAttribute('href').includes('messages')) {
                messagesNav = item;
            }
        });
        
        if (!messagesNav) return;
        
        let badge = messagesNav.querySelector('.fi-sidebar-item-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'fi-sidebar-item-badge flex items-center justify-center gap-x-1 rounded-md bg-danger-600 px-2 py-0.5 text-xs font-medium text-white';
                const label = messagesNav.querySelector('.fi-sidebar-item-label');
                if (label) {
                    label.after(badge);
                } else {
                    messagesNav.appendChild(badge);
                }
            }
            badge.textContent = count > 9 ? '9+' : count;
            badge.style.display = 'inline-flex';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }
    
    // Fonction pour mettre à jour le titre
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
    if (window.Livewire) {
        window.Livewire.on('update-unread-badge', (data) => {
            const count = data.count;
            updateNavigationBadge(count);
            updateTitle(count);
        });
        
        window.Livewire.on('play-notification-sound', () => {
            if (document.hidden) {
                playNotificationSound();
            }
        });
    }
    
    // Polling toutes les 5 secondes pour les nouveaux messages
    let lastMessageCount = 0;
    let pollingInterval = setInterval(async () => {
        try {
            const response = await fetch('/api/v1/unread-messages-count', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.count !== lastMessageCount && data.count > lastMessageCount && document.hidden) {
                    playNotificationSound();
                }
                lastMessageCount = data.count;
                updateNavigationBadge(data.count);
                
                // Mettre à jour le titre seulement si l'onglet est caché
                if (document.hidden) {
                    updateTitle(data.count);
                }
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
        setTimeout(() => {
            const titleWithoutCount = document.title.replace(/^\(\d+\)\s/, '');
            document.title = titleWithoutCount;
        }, 100);
    }
});
