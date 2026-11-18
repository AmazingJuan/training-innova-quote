import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Unit tests of the frontend's pure logic (no browser, no Laravel plugin).
export default defineConfig({
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
