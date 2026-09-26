import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Frontend unit tests (`npm run test:js`). Kept apart from vite.config.ts on
 * purpose: these tests read catalogs and source files as data, so they need
 * the `@` alias and nothing else (no Laravel, Inertia or Wayfinder plugins,
 * no browser).
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
