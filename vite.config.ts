import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            // The workbench app's public path points here (WorkbenchServiceProvider),
            // so the Vite manifest and hot file are served from the package itself.
            publicDirectory: 'workbench/public',
            refresh: true,
        }),
        inertia(),
        react(),
        tailwindcss(),
    ],
});
