/**
 * Gedragstests voor de gate in dist/turnstile.js.
 *
 * Draait met `node --test tests/js`.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { bouw, tick } from './harness.mjs';

const STAGE = `
    <div data-turnstile-stage="idle" data-turnstile-for="contact">
        <div data-turnstile-container data-sitekey="abc"></div>
        <button type="button" data-turnstile-cancel>Annuleren</button>
    </div>`;

const FORMULIER = `
    <form data-turnstile-form="contact">
        <input type="hidden" name="turnstile_token" data-turnstile-field>
        <button type="submit">Versturen</button>
    </form>${STAGE}`;

test('token() levert het token dat Cloudflare teruggeeft', async () => {
    const { window, widgets } = await bouw(STAGE);
    const gate = window.turnstileGate.create({ stage: window.document.querySelector('[data-turnstile-stage]') });

    const belofte = gate.token();
    await tick();

    widgets[0].opties.callback('token-123');

    assert.equal(await belofte, 'token-123');
    assert.equal(widgets[0].uitgevoerd, 1);
});

test('een verlopen token laat de belofte niet hangen', async () => {
    const { window, widgets } = await bouw(STAGE);
    const gate = window.turnstileGate.create({ stage: window.document.querySelector('[data-turnstile-stage]') });

    const belofte = gate.token();
    await tick();

    widgets[0].opties['expired-callback']();

    await assert.rejects(belofte, (fout) => {
        assert.equal(fout.message, 'turnstile-expired');
        assert.equal(gate.isExpired(fout), true);
        assert.equal(gate.isCancelled(fout), false);

        return true;
    });
});

test('annuleren wordt onderscheiden van een storing', async () => {
    const { window, document } = await bouw(STAGE);
    const gate = window.turnstileGate.create({ stage: document.querySelector('[data-turnstile-stage]') });

    const belofte = gate.token();
    await tick();

    document.querySelector('[data-turnstile-cancel]').click();

    await assert.rejects(belofte, (fout) => gate.isCancelled(fout));
});

test('het vlak komt naar voren bij interactie en verdwijnt erna', async () => {
    const { window, document, widgets } = await bouw(STAGE);
    const stage = document.querySelector('[data-turnstile-stage]');
    const gate = window.turnstileGate.create({ stage });

    assert.equal(stage.inert, true);

    const belofte = gate.token();
    await tick();

    widgets[0].opties['before-interactive-callback']();
    assert.equal(stage.dataset.turnstileStage, 'challenge');
    assert.equal(stage.inert, false);

    widgets[0].opties.callback('token-123');
    await belofte;

    assert.equal(stage.dataset.turnstileStage, 'idle');
    assert.equal(stage.inert, true);
});

test('destroy() ruimt de keydown-listener op', async () => {
    const { window, document } = await bouw(STAGE);
    const stage = document.querySelector('[data-turnstile-stage]');
    const gate = window.turnstileGate.create({ stage });

    const belofte = gate.token();
    await tick();
    gate.destroy();
    await assert.rejects(belofte);

    // Na destroy mag Escape niets meer doen met dit vlak.
    stage.dataset.turnstileStage = 'challenge';
    const event = new window.KeyboardEvent('keydown', { key: 'Escape', cancelable: true, bubbles: true });
    document.dispatchEvent(event);

    assert.equal(event.defaultPrevented, false);
});

test('een tweede token() sluit de eerste af in plaats van hem te laten hangen', async () => {
    const { window, widgets } = await bouw(STAGE);
    const gate = window.turnstileGate.create({ stage: window.document.querySelector('[data-turnstile-stage]') });

    const eerste = gate.token();
    const eersteFout = eerste.then(() => null, (fout) => fout);
    await tick();

    const tweede = gate.token();
    await tick();

    assert.equal((await eersteFout).message, 'turnstile-superseded');

    widgets[0].opties.callback('token-456');
    assert.equal(await tweede, 'token-456');
});

test('auto-bedrading vult het veld en verstuurt daarna alsnog', async () => {
    const { window, document, widgets } = await bouw(FORMULIER);
    const form = document.querySelector('form');

    let verstuurd = 0;
    form.requestSubmit = () => {
        verstuurd++;
    };

    form.dispatchEvent(new window.Event('submit', { cancelable: true, bubbles: true }));
    await tick();

    widgets[0].opties.callback('token-abc');
    await tick();

    assert.equal(document.querySelector('[data-turnstile-field]').value, 'token-abc');
    assert.equal(verstuurd, 1);
});

test('een eigen submit-handler vuurt maar een keer', async () => {
    const { window, document, widgets } = await bouw(FORMULIER);
    const form = document.querySelector('form');

    let eigenHandler = 0;
    form.addEventListener('submit', (event) => {
        eigenHandler++;
        event.preventDefault();
    });

    form.requestSubmit = () => {
        form.dispatchEvent(new window.Event('submit', { cancelable: true, bubbles: true }));
    };

    form.dispatchEvent(new window.Event('submit', { cancelable: true, bubbles: true }));
    await tick();

    // Eerste poging: onderschept voor de eigen handler, dus die zag niets.
    assert.equal(eigenHandler, 0);

    widgets[0].opties.callback('token-abc');
    await tick();

    // Tweede poging: het token staat er, de eigen handler komt precies een keer.
    assert.equal(eigenHandler, 1);
});

test('zichtbare modus gebruikt een al aangevinkt antwoord', async () => {
    const { window, widgets } = await bouw(STAGE);
    const gate = window.turnstileGate.create({
        stage: window.document.querySelector('[data-turnstile-stage]'),
        visible: true,
    });

    const eerste = gate.token();
    await tick();
    widgets[0].opties.callback('token-vooraf');
    await eerste;

    widgets[0].respons = 'token-vooraf';

    assert.equal(await gate.token(), 'token-vooraf');
    assert.equal(widgets[0].opties.appearance, 'always');
    assert.equal(widgets[0].opties.execution, 'render');
});

test('render() zet een zichtbaar widget klaar zonder token te vragen', async () => {
    const { window, widgets } = await bouw(STAGE);
    const gate = window.turnstileGate.create({
        stage: window.document.querySelector('[data-turnstile-stage]'),
        visible: true,
    });

    assert.equal(gate.rendered, false);
    assert.equal(await gate.render(), true);
    assert.equal(gate.rendered, true);
    assert.equal(widgets[0].uitgevoerd, 0, 'render() mag geen uitdaging starten');
});
