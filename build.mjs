/**
 * Schrijft de .min-siblings naast de leesbare bronnen in dist/.
 *
 * De bestanden worden meegeleverd in de package: een applicatie publiceert ze
 * met `vendor:publish --tag=turnstile-assets`, en een minifier aan die kant
 * slaat vendor-mappen doorgaans over.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { minify } from 'terser';
import CleanCSS from 'clean-css';

const js = ['dist/turnstile.js', 'dist/turnstile-bridge.js'];
const css = ['dist/turnstile.css'];

for (const bestand of js) {
    const bron = await readFile(bestand, 'utf8');
    const { code } = await minify(bron, { format: { comments: false } });
    await writeFile(bestand.replace(/\.js$/, '.min.js'), code, 'utf8');
    console.log(`${bestand} -> ${bestand.replace(/\.js$/, '.min.js')}`);
}

for (const bestand of css) {
    const bron = await readFile(bestand, 'utf8');
    const { styles } = new CleanCSS({ level: 1 }).minify(bron);
    await writeFile(bestand.replace(/\.css$/, '.min.css'), styles, 'utf8');
    console.log(`${bestand} -> ${bestand.replace(/\.css$/, '.min.css')}`);
}
