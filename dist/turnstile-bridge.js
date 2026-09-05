/**
 * De WebView-kant van turnstile::bridge.
 *
 * Een native app kan zelf geen Turnstile-token maken, dus laadt ze deze pagina
 * onzichtbaar in een WebView. Dit script rendert het widget en meldt via de
 * WebView-brug wat er gebeurt, zodat de app het token kan meesturen en de
 * WebView alleen zichtbaar hoeft te maken wanneer Cloudflare echt iets vraagt.
 *
 * Staat er geen ReactNativeWebView-brug (bijvoorbeeld wanneer iemand de pagina
 * in een gewone browser opent), dan gebeurt er verder niets; de berichten gaan
 * dan naar de console.
 */
(function () {
    'use strict';

    var container = document.querySelector('[data-turnstile-container]');

    function post(payload) {
        var bericht = JSON.stringify(payload);

        if (window.ReactNativeWebView && typeof window.ReactNativeWebView.postMessage === 'function') {
            window.ReactNativeWebView.postMessage(bericht);

            return;
        }

        // Geen brug: laat het bericht wel achter, anders is deze pagina in een
        // gewone browser volledig stil en lijkt ze stuk.
        if (window.console && window.console.log) {
            window.console.log('[turnstile-bridge]', bericht);
        }
    }

    function render() {
        if (!container || !window.turnstile) return;

        window.turnstile.render(container, {
            sitekey: container.getAttribute('data-sitekey') || '',
            theme: container.getAttribute('data-theme') || 'light',
            size: container.getAttribute('data-size') || 'flexible',
            callback: function (token) {
                post({ status: 'ok', token: token });
            },
            'error-callback': function () {
                post({ status: 'error' });
            },
            'expired-callback': function () {
                post({ status: 'error' });
            },
            'before-interactive-callback': function () {
                post({ status: 'interactive' });
            },
            'after-interactive-callback': function () {
                post({ status: 'resolved' });
            },
        });
    }

    if (window.turnstile) {
        render();

        return;
    }

    var script = document.querySelector('script[src*="turnstile/v0/api.js"]');

    if (!script) {
        post({ status: 'error' });

        return;
    }

    script.addEventListener('load', render);
    script.addEventListener('error', function () {
        post({ status: 'error' });
    });

    // Het script kan al klaar zijn voor we de listener hingen.
    if (window.turnstile) render();
})();
