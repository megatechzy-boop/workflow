{{-- Installable app (Add to Home Screen) + iPhone full-screen --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0A7A6C">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="MegaTechzy">
<script>
    // Theme: "system" | "light" | "dark", kept per device; applied before paint so there is no white flash
    (() => {
        const media = matchMedia('(prefers-color-scheme: dark)');
        window.themeGet = () => { try { return localStorage.getItem('theme') || 'system'; } catch (e) { return 'system'; } };
        window.themeApply = () => {
            const t = themeGet(), dark = t === 'dark' || (t === 'system' && media.matches);
            document.documentElement.classList.toggle('dark', dark);
            document.querySelector('meta[name=theme-color]')?.setAttribute('content', dark ? '#18212d' : '#0A7A6C');
        };
        window.themeSet = (t) => { try { localStorage.setItem('theme', t); } catch (e) {} themeApply(); };
        media.addEventListener('change', themeApply);
        themeApply();
        document.addEventListener('DOMContentLoaded', themeApply); // theme-color meta exists by then
    })();

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
