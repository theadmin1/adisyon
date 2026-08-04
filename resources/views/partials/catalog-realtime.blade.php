<script>
(() => {
    const versionUrl = {{ Illuminate\Support\Js::from(route('catalog.version')) }};
    const targets = {{ Illuminate\Support\Js::from($catalogTargets) }};
    const intervalMs = 3000;
    let currentVersion = null;
    let checking = false;
    let refreshing = false;

    async function refreshCatalog() {
        if (refreshing) return;
        refreshing = true;

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('catalog_refresh', Date.now().toString());
            const response = await fetch(url, {
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store',
            });
            if (!response.ok) return;

            const freshDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            let replaced = false;
            targets.forEach(selector => {
                const current = document.querySelector(selector);
                const fresh = freshDocument.querySelector(selector);
                if (current && fresh) {
                    current.innerHTML = fresh.innerHTML;
                    replaced = true;
                }
            });

            if (replaced) {
                window.dispatchEvent(new CustomEvent('catalog:refreshed'));
            }
        } catch (error) {
            // Bağlantı geçici olarak koparsa mevcut ürün kutuları kullanılmaya devam eder.
        } finally {
            refreshing = false;
        }
    }

    async function checkVersion() {
        if (checking || document.hidden) return;
        checking = true;
        try {
            const response = await fetch(versionUrl, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
            });
            if (!response.ok) return;
            const version = Number((await response.json()).version || 1);
            if (currentVersion !== null && version !== currentVersion) {
                await refreshCatalog();
            }
            currentVersion = version;
        } catch (error) {
            // Sessiz geri kazanım: sonraki aralıkta yeniden denenir.
        } finally {
            checking = false;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) checkVersion();
    });
    window.setInterval(checkVersion, intervalMs);
    checkVersion();
})();
</script>
