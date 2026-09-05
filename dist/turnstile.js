/**
 * Cloudflare Turnstile die alleen opvalt wanneer het moet.
 *
 * Het widget draait standaard in appearance:'interaction-only' en
 * execution:'execute': er gebeurt niets tot een script om een token vraagt, en
 * Cloudflare toont alleen iets wanneer het echt twijfelt. Gebeurt dat, dan
 * schuift het vlak dat het widget draagt naar voren als modaal blok, in plaats
 * van ergens in de paginastroom op te duiken waar de bezoeker het niet ziet.
 *
 * Programmatisch gebruik:
 *
 *   const gate = window.turnstileGate.create({
 *       container: '#mijn-container',                     // of een Element
 *       stage: document.querySelector('[data-turnstile-stage]'),
 *   });
 *
 *   gate.token().then(function (token) { ... });
 *   gate.isCancelled(error);   // afgebroken door de bezoeker, of echt stuk?
 *
 * Of zonder ook maar een regel eigen JavaScript, via de markup:
 *
 *   <form data-turnstile-form="retour"> <x-turnstile::field /> ... </form>
 *   <x-turnstile::stage for="retour" fixed> ... </x-turnstile::stage>
 *
 * Dan onderschept dit script het versturen, haalt een token, vult het verborgen
 * veld en verstuurt alsnog.
 *
 * Er is bewust geen onload-parameter op api.js. Cloudflare roept maar een
 * globale aan, dus twee widgets op dezelfde pagina vechten daarom; het
 * load-event van de scripttag doet hetzelfde werk zonder die botsing.
 */
(function () {
    'use strict';

    var API_BASE = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    var apiPromise = null;

    /** Alle gates, op naam, zodat een pagina-script er een kan terugvinden. */
    var gates = [];

    /** Formulieren die via data-turnstile-form bediend worden. */
    var gekoppeld = [];

    function element(value, wortel) {
        if (!value) return null;
        if (typeof value === 'string') return (wortel || document).querySelector(value);

        return value;
    }

    /** Het script dat de pagina zelf al meebracht, of anders er zelf een hangen. */
    function ensureApi(src) {
        if (window.turnstile) return Promise.resolve();
        if (apiPromise) return apiPromise;

        apiPromise = new Promise(function (resolve, reject) {
            var script = document.querySelector('script[src*="turnstile/v0/api.js"]');

            if (!script) {
                script = document.createElement('script');
                script.src = (src || API_BASE) + '?render=explicit';
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }

            script.addEventListener('load', function () {
                resolve();
            });

            script.addEventListener('error', function () {
                apiPromise = null;
                reject(new Error('turnstile-script'));
            });

            // Het script kan al uitgevoerd zijn voor we de listener hingen; dan
            // vuurt load niet meer.
            if (window.turnstile) resolve();
        });

        return apiPromise;
    }

    function create(options) {
        var settings = options || {};

        var stage = element(settings.stage);
        var container = element(settings.container)
            || (stage ? stage.querySelector('[data-turnstile-container]') : null);

        if (!container) {
            throw new Error('turnstile-no-container');
        }

        var sitekey = settings.sitekey || container.getAttribute('data-sitekey') || '';
        var zichtbaar = settings.visible === true;
        var cancelButton = stage ? stage.querySelector('[data-turnstile-cancel]') : null;

        var widgetId = null;
        var pending = null;
        var challengeTimer = null;
        var vorigeFocus = null;
        var vernietigd = false;

        if (stage) {
            // inert houdt de annuleerknop buiten de tabvolgorde en buiten bereik
            // van een schermlezer zolang er niets te zien is.
            stage.inert = true;
        }

        function inChallenge() {
            return !!stage && stage.dataset.turnstileStage === 'challenge';
        }

        function toon() {
            if (!stage || inChallenge()) return;

            vorigeFocus = document.activeElement;
            stage.dataset.turnstileStage = 'challenge';
            stage.inert = false;

            if (cancelButton) cancelButton.focus();
        }

        function verberg() {
            if (!inChallenge()) return;

            stage.dataset.turnstileStage = 'idle';
            stage.inert = true;

            // Focus terug naar waar de bezoeker vandaan kwam; anders staat hij
            // na het sluiten nergens en begint tabben weer bovenaan de pagina.
            if (vorigeFocus && typeof vorigeFocus.focus === 'function' && document.contains(vorigeFocus)) {
                vorigeFocus.focus();
            }

            vorigeFocus = null;
        }

        function stopTimer() {
            if (challengeTimer) {
                window.clearTimeout(challengeTimer);
                challengeTimer = null;
            }
        }

        function settle(actie, waarde) {
            stopTimer();
            verberg();

            if (!pending) return;

            var wachtende = pending;
            pending = null;
            wachtende[actie](waarde);
        }

        function render() {
            if (widgetId !== null || !window.turnstile || vernietigd) return;

            widgetId = window.turnstile.render(container, {
                sitekey: sitekey,
                theme: settings.theme || container.getAttribute('data-theme') || 'auto',
                size: settings.size || container.getAttribute('data-size') || 'flexible',
                appearance: settings.appearance || (zichtbaar ? 'always' : 'interaction-only'),
                execution: settings.execution || (zichtbaar ? 'render' : 'execute'),
                callback: function (token) {
                    settle('resolve', token);
                },
                'error-callback': function () {
                    settle('reject', new Error('turnstile-error'));
                },
                // Zonder dit blijft een belofte hangen wanneer het token
                // verloopt terwijl de uitdaging openstaat: de verstuurknop blijft
                // dan dood en de bezoeker snapt niet waarom.
                'expired-callback': function () {
                    try {
                        window.turnstile.reset(widgetId);
                    } catch (_) { /* al opgeruimd */ }

                    settle('reject', new Error('turnstile-expired'));
                },
                // Cloudflare wil interactie: naar voren halen in plaats van laten
                // opduiken in de paginastroom, waar het widget geen hoogte heeft.
                'before-interactive-callback': toon,
                'after-interactive-callback': verberg,
            });
        }

        function annuleer() {
            try {
                window.turnstile.reset(widgetId);
            } catch (_) { /* nog niets te resetten */ }

            settle('reject', new Error('turnstile-cancelled'));
        }

        function onKeydown(event) {
            if (!inChallenge()) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                annuleer();

                return;
            }

            if (event.key !== 'Tab') return;

            // Het vlak is modaal: de focus hoort er niet uit te wandelen zolang
            // de uitdaging openstaat.
            var items = Array.prototype.filter.call(
                stage.querySelectorAll('button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])'),
                function (node) {
                    return !node.disabled && node.offsetParent !== null;
                }
            );

            if (!items.length) return;

            var eerste = items[0];
            var laatste = items[items.length - 1];

            if (event.shiftKey && document.activeElement === eerste) {
                event.preventDefault();
                laatste.focus();
            } else if (!event.shiftKey && document.activeElement === laatste) {
                event.preventDefault();
                eerste.focus();
            }
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', annuleer);
        }

        if (stage) {
            document.addEventListener('keydown', onKeydown);
        }

        /** Een vers, ongebruikt token. Elke oproep doet een nieuwe uitdaging. */
        function token() {
            return ensureApi(settings.apiSrc).then(function () {
                render();

                return new Promise(function (resolve, reject) {
                    if (widgetId === null) {
                        reject(new Error('turnstile-not-ready'));

                        return;
                    }

                    // Een lopende aanvraag afsluiten voor we een nieuwe starten,
                    // anders blijft de eerste belofte voorgoed hangen.
                    if (pending) {
                        settle('reject', new Error('turnstile-superseded'));
                    }

                    pending = { resolve: resolve, reject: reject };

                    if (zichtbaar) {
                        // Zichtbare modus: de bezoeker heeft het vakje mogelijk
                        // al aangevinkt voor hij verstuurt.
                        var bestaand = '';

                        try {
                            bestaand = window.turnstile.getResponse(widgetId) || '';
                        } catch (_) { /* nog niet klaar */ }

                        if (bestaand) settle('resolve', bestaand);

                        return;
                    }

                    try {
                        window.turnstile.reset(widgetId);
                        window.turnstile.execute(widgetId);

                        // Vangnet: mocht before-interactive-callback niet vuren,
                        // dan zit de bezoeker naar een onzichtbare uitdaging te
                        // kijken. Duurt het te lang, dan tonen we het vlak alsnog.
                        stopTimer();
                        challengeTimer = window.setTimeout(function () {
                            if (pending) toon();
                        }, settings.challengeDelay || 3000);
                    } catch (error) {
                        stopTimer();
                        pending = null;
                        reject(error);
                    }
                });
            });
        }

        var gate = {
            name: settings.name || null,
            container: container,
            stage: stage,

            token: token,

            /**
             * Zet het widget klaar zonder al een token te vragen. Nodig in
             * zichtbare modus: de bezoeker moet het vakje zien voor hij
             * verstuurt. Roep dit aan zodra de container echt zichtbaar is; een
             * widget dat rendert in een gesloten dialoog blijft leeg.
             */
            render: function () {
                return ensureApi(settings.apiSrc).then(function () {
                    render();

                    return widgetId !== null;
                });
            },

            /** Onderscheidt een bewust afgebroken uitdaging van een echte storing. */
            isCancelled: function (error) {
                return !!error && error.message === 'turnstile-cancelled';
            },

            isExpired: function (error) {
                return !!error && error.message === 'turnstile-expired';
            },

            reset: function () {
                try {
                    window.turnstile.reset(widgetId);
                } catch (_) { /* nog niets te resetten */ }
            },

            /** Alles losmaken. Nodig zodra een dialoog uit de DOM verdwijnt. */
            destroy: function () {
                vernietigd = true;
                settle('reject', new Error('turnstile-cancelled'));

                if (cancelButton) cancelButton.removeEventListener('click', annuleer);
                if (stage) document.removeEventListener('keydown', onKeydown);

                try {
                    window.turnstile.remove(widgetId);
                } catch (_) { /* al weg */ }

                widgetId = null;

                var i = gates.indexOf(gate);
                if (i !== -1) gates.splice(i, 1);
            },

            get rendered() {
                return widgetId !== null;
            },
        };

        gates.push(gate);

        // Standaard pas renderen bij de eerste token(): een widget dat in een
        // gesloten dialoog rendert, meet zich aan een vlak van nul pixels.
        if (settings.eager === true) {
            ensureApi(settings.apiSrc).then(render, function () { /* stil; token() meldt het wel */ });
        }

        return gate;
    }

    /**
     * Bedraadt elk formulier met data-turnstile-form, zodat pagina's zonder
     * eigen JavaScript toch een token meesturen.
     *
     * De listener hangt in de capture-fase aan document, niet aan het formulier:
     * zo komt hij voor elke andere submit-handler aan de beurt en kan hij die
     * met stopImmediatePropagation overslaan zolang er nog geen token is.
     * Anders zou een pagina-script dat zelf op submit luistert twee keer vuren:
     * een keer op de afgebroken poging en een keer op de echte.
     */
    function autowire(wortel) {
        var formulieren = (wortel || document).querySelectorAll('form[data-turnstile-form]');

        Array.prototype.forEach.call(formulieren, function (form) {
            if (form.dataset.turnstileBound === 'true') return;
            if (!form.querySelector('[data-turnstile-field]')) return;

            var naam = form.getAttribute('data-turnstile-form') || '';

            var stage = naam
                ? document.querySelector('[data-turnstile-stage][data-turnstile-for="' + naam + '"]')
                : document.querySelector('[data-turnstile-stage]:not([data-turnstile-for])');

            var container = (stage && stage.querySelector('[data-turnstile-container]'))
                || form.querySelector('[data-turnstile-container]');

            if (!container) return;

            form.dataset.turnstileBound = 'true';

            gekoppeld.push({
                form: form,
                gate: create({
                    name: naam,
                    stage: stage,
                    container: container,
                    visible: container.hasAttribute('data-turnstile-visible'),
                }),
            });
        });
    }

    function gekoppeldeGate(form) {
        for (var i = 0; i < gekoppeld.length; i++) {
            if (gekoppeld[i].form === form) return gekoppeld[i].gate;
        }

        return null;
    }

    function onSubmitCapture(event) {
        var form = event.target;

        if (!form || form.dataset === undefined || form.dataset.turnstileBound !== 'true') return;

        // Tweede ronde: het token staat er al, dus alles mag zijn gang gaan.
        if (form.dataset.turnstileReady === 'true') {
            form.dataset.turnstileReady = '';

            return;
        }

        var gate = gekoppeldeGate(form);
        var veld = form.querySelector('[data-turnstile-field]');

        if (!gate || !veld) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        var submitter = event.submitter || null;
        if (submitter) submitter.disabled = true;

        gate.token().then(function (token) {
            veld.value = token;
            form.dataset.turnstileReady = 'true';

            if (submitter) submitter.disabled = false;

            // requestSubmit behoudt de submitter en de gekozen bestanden.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
        }, function (error) {
            if (submitter) submitter.disabled = false;

            form.dispatchEvent(new CustomEvent('turnstile:failed', {
                bubbles: true,
                detail: { error: error, cancelled: gate.isCancelled(error) },
            }));
        });
    }

    document.addEventListener('submit', onSubmitCapture, true);

    window.turnstileGate = {
        create: create,
        autowire: autowire,
        find: function (naam) {
            for (var i = 0; i < gates.length; i++) {
                if (gates[i].name === naam) return gates[i];
            }

            return null;
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            autowire(document);
        });
    } else {
        autowire(document);
    }
})();
