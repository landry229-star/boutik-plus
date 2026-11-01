(() => {
    const installButtons = document.querySelectorAll('[data-pwa-install]');
    let installPrompt = null;

    if ('serviceWorker' in navigator) {
        const serviceWorkerUrl = document.querySelector('meta[name="service-worker"]')?.content;

        navigator.serviceWorker.register(serviceWorkerUrl || '/service-worker.js')
            .catch((error) => console.error('Impossible d’activer le mode hors connexion Boutik Plus.', error));
    }

    const isInstalled = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    installButtons.forEach((button) => {
        button.hidden = isInstalled;
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;

        installButtons.forEach((button) => {
            button.hidden = false;
        });
    });

    installButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (!installPrompt) {
                window.alert('Pour installer Boutik Plus, ouvrez le menu de votre navigateur et choisissez « Installer l’application » ou « Ajouter à l’écran d’accueil ».');

                return;
            }

            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
        });
    });

    window.addEventListener('appinstalled', () => {
        installButtons.forEach((button) => {
            button.hidden = true;
        });
    });
})();
