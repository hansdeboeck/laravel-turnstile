/**
 * Minimale omgeving om dist/turnstile.js te draaien zonder browser.
 *
 * Cloudflare's api.js wordt vervangen door een stub die de callbacks bewaart,
 * zodat een test kan bepalen wanneer een token binnenkomt, verloopt of faalt.
 */
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

export async function bouw(html) {
    const dom = new JSDOM(`<!doctype html><html><body>${html}</body></html>`, {
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });

    const { window } = dom;

    const widgets = [];

    window.turnstile = {
        render(container, opties) {
            widgets.push({ container, opties, uitgevoerd: 0, gereset: 0, respons: '' });

            return widgets.length - 1;
        },
        execute(id) {
            widgets[id].uitgevoerd++;
        },
        reset(id) {
            widgets[id].gereset++;
            widgets[id].respons = '';
        },
        remove(id) {
            widgets[id].verwijderd = true;
        },
        getResponse(id) {
            return widgets[id].respons;
        },
    };

    // offsetParent bestaat niet in jsdom; de focus-trap filtert erop.
    Object.defineProperty(window.HTMLElement.prototype, 'offsetParent', {
        get() {
            return this.ownerDocument.body;
        },
        configurable: true,
    });

    const bron = readFileSync(new URL('../../dist/turnstile.js', import.meta.url), 'utf8');
    window.eval(bron);

    // Een defer-script draait voor DOMContentLoaded; jsdom staat op dat moment
    // nog op 'loading'. Wachten tot het document klaar is, zodat de
    // auto-bedrading gelopen heeft voor een test iets verstuurt.
    if (window.document.readyState !== 'complete') {
        await new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));
    }

    return { dom, window, document: window.document, widgets };
}

export function tick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}
