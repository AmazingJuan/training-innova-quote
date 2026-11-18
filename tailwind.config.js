import forms from '@tailwindcss/forms';


/**
 * Tokens copied from the Training Corporation site (training/docs/BRAND.md) with
 * the same names, so both apps can later share one design system.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: ['./resources/views/**/*.blade.php', './resources/js/**/*.tsx', './resources/js/**/*.ts'],

    theme: {
        extend: {
            fontFamily: {
                display: ['Montserrat', '"Segoe UI"', 'system-ui', 'sans-serif'],
                sans: ['"Source Sans 3"', '"Source Sans Pro"', '"Segoe UI"', 'system-ui', 'sans-serif'],
            },
            colors: {
                azul: { DEFAULT: '#0072af', hondo: '#005a8c', vivo: '#1391da' }, // logo blue · hover · ring strokes / large type only
                cielo: '#7fd0f5', // accents on noche
                noche: '#0b2c47', // deep brand navy
                tinta: '#102a3f', // body text
                pizarra: '#4a5a67', // secondary text
                'gris-marca': '#8f9392', // logo grey, graphics only
                niebla: '#a9c6dc', // secondary text on noche
                bruma: '#93abc0', // tertiary text on noche
                nube: '#eef4f8', // alternate ground
                linea: '#d5e2eb', // rules and field borders
                error: '#b3261e',
            },
            borderRadius: {
                control: '6px',
            },
            letterSpacing: {
                tight: '-0.03em',
                snug: '-0.015em',
            },
            maxWidth: {
                page: '90rem',
                measure: '40rem',
            },
            transitionTimingFunction: {
                out: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
        },
    },

    plugins: [forms],
};
