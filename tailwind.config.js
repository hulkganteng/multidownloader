/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.jsx",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                // Electric blue accent palette
                electric: {
                    50: '#eef4ff',
                    100: '#dfeaff',
                    200: '#c5d9ff',
                    300: '#a1c0ff',
                    400: '#7a9dff',
                    500: '#4f7bff',
                    600: '#2f5bff',
                    700: '#1e4bf0',
                    800: '#1c3fd4',
                    900: '#1d3aac',
                },
                // Dark navy text / footer
                navy: {
                    50: '#f4f7fb',
                    100: '#e6ecf5',
                    500: '#3b4a63',
                    700: '#16233b',
                    800: '#0f1a2e',
                    900: '#0a1224',
                    950: '#060b1a',
                },
            },
            borderRadius: {
                '2xl': '1.1rem',
                '3xl': '1.5rem',
            },
            boxShadow: {
                soft: '0 10px 40px -12px rgba(15, 40, 120, 0.12)',
                'soft-lg': '0 24px 60px -20px rgba(15, 40, 120, 0.18)',
                card: '0 4px 24px -8px rgba(15, 40, 120, 0.10)',
                'glow-blue': '0 12px 40px -12px rgba(47, 91, 255, 0.5)',
            },
        },
    },
    plugins: [],
}
