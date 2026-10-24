if (!window.userMenuReady) {
    window.userMenuReady = true;

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('#user-toggle');
        const dropdown = document.getElementById('user-dropdown');
        if (!dropdown) return;

        if (toggle) {
            // Clic sur l'icône : ouvrir / fermer
            dropdown.classList.toggle('show');
            toggle.setAttribute('aria-expanded', dropdown.classList.contains('show'));
        } else if (!e.target.closest('#user-dropdown')) {
            // Clic ailleurs : fermer
            dropdown.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const dropdown = document.getElementById('user-dropdown');
            if (dropdown) dropdown.classList.remove('show');
        }
    });
}