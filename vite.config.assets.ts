import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

/**
 * The v1 shipping build (docs/ARCHITECTURE.md → "Frontend delivery").
 *
 * Compiles the package's React runtime into hashless, stable-named assets at
 * the package's `public/` root — `refilament.js` + `refilament.css` — so the
 * existing `vendor:publish --tag=refilament-assets` (which maps the package
 * `public/` dir onto the consumer's `public/vendor/refilament/`) ships a
 * ready-to-serve bundle. Consumers load it through the package's own root
 * view (`refilament::app`) with plain <link>/<script> tags and need zero npm
 * setup — the same delivery story as Filament.
 *
 * Distinct from `vite.config.ts` (the workbench dev/build loop, which uses
 * laravel-vite-plugin + a manifest under workbench/public). Run with:
 *
 *     npm run build:assets
 */
export default defineConfig({
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    // Nothing to copy from a public dir — the package's public/ IS the output.
    publicDir: false,
    build: {
        outDir: 'public',
        // NOT emptyOutDir — the package's public/ is shared (it may hold
        // .gitkeep or future hand-placed files). The two stable filenames
        // (refilament.js / refilament.css) overwrite cleanly on each build;
        // only stale extra assets would linger, and there are none today.
        emptyOutDir: false,
        cssCodeSplit: false,
        sourcemap: false,
        rollupOptions: {
            input: 'resources/js/refilament.tsx',
            output: {
                entryFileNames: 'refilament.js',
                // Only the single bundled stylesheet gets the stable name —
                // anything else (fonts, images) keeps a hashed name so two
                // assets never collide. There are none today.
                assetFileNames: (assetInfo) =>
                    assetInfo.name?.endsWith('.css') ? 'refilament.css' : 'refilament-[name]-[hash][extname]',
            },
        },
    },
});
