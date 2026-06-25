/**
 * Tailwind for Black Bean Shop public templates (storefront, products, cart).
 *
 * @type {import('tailwindcss').Config}
 */
module.exports = {
	content: [
		'./templates/**/*.php',
		'./includes/frontend-ui.php',
		'./includes/shop.php',
		'./src/shop-front.css',
	],
	corePlugins: {
		preflight: false,
	},
	darkMode: 'class',
	theme: {
		extend: {
			fontFamily: {
				sans: [ 'var(--font-sans)', 'ui-monospace', 'monospace' ],
				display: [ 'var(--font-display)', 'var(--font-sans)', 'ui-monospace', 'monospace' ],
				mono: [ 'var(--font-mono)', 'ui-monospace', 'monospace' ],
			},
			colors: {
				brand: {
					50: '#f5f3ff',
					100: '#ede9fe',
					200: '#ddd6fe',
					300: '#c4b5fd',
					400: '#a78bfa',
					500: '#8b5cf6',
					600: '#7c3aed',
					700: '#6d28d9',
					800: '#5b21b6',
					900: '#4c1d95',
					950: '#2e1065',
				},
			},
			boxShadow: {
				card: '0 4px 24px -4px rgb(15 23 42 / 0.08)',
				'card-hover': '0 20px 40px -12px rgb(15 23 42 / 0.18)',
				'glow-sm': '0 0 24px -6px rgb(124 58 237 / 0.35)',
			},
			animation: {
				'fade-up': 'fadeUp 0.65s cubic-bezier(0.22, 1, 0.36, 1) forwards',
			},
			keyframes: {
				fadeUp: {
					'0%': { opacity: '0', transform: 'translateY(18px)' },
					'100%': { opacity: '1', transform: 'translateY(0)' },
				},
			},
		},
	},
	plugins: [ require( '@tailwindcss/typography' ) ],
};
