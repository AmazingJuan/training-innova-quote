import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    {
        ignores: ['vendor', 'node_modules', 'public', 'storage', 'bootstrap', 'tailwind.config.js', 'load-tests', 'manual-admin'],
    },
    js.configs.recommended,
    ...typescript.configs.recommended,
    // Type-aware rules (they read tsconfig.json) for the application's code.
    ...typescript.configs.recommendedTypeChecked.map((config) => ({ ...config, files: ['resources/js/**/*.{ts,tsx}'] })),
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname },
        },
        rules: {
            // Inertia's router and visits are fire-and-forget by design.
            '@typescript-eslint/no-floating-promises': 'off',
            '@typescript-eslint/no-misused-promises': ['error', { checksVoidReturn: { attributes: false } }],
            // Inertia's useForm returns plain closures (post, setData…), not methods bound to `this`.
            '@typescript-eslint/unbound-method': 'off',
        },
    },
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // React 17+ JSX transform
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
            '@typescript-eslint/no-unused-vars': ['error', { ignoreRestSiblings: true, argsIgnorePattern: '^_' }],
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    jsxA11y.flatConfigs.recommended,
    {
        rules: {
            // The form controls of the auth pages are wrapped in their own components.
            'jsx-a11y/label-has-associated-control': ['error', { controlComponents: ['Checkbox', 'TextInput'], depth: 3 }],
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'error',
        },
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
