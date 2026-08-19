/** @type {import('tailwindcss').Config} */
export default {
  // Scan all PHP pages and all src JSX components for class usage.
  content: [
    './*.php',
    './admin/*.php',
    './includes/*.php',
    './src/**/*.{js,jsx,ts,tsx}',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans:    ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Playfair Display', 'Georgia', 'serif'],
      },
      colors: {
        brand: {
          50:  '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#f97316',
          600: '#ea580c',
          700: '#c2410c',
          800: '#9a3412',
          900: '#7c2d12',
        },
      },
      keyframes: {
        fadeIn: {
          from: { opacity: '0', transform: 'translateY(-8px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        slideDown: {
          from: { opacity: '0', maxHeight: '0' },
          to:   { opacity: '1', maxHeight: '400px' },
        },
        shimmer: {
          '0%':   { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition:  '200% 0' },
        },
      },
      animation: {
        fadeIn:    'fadeIn 0.25s ease-out',
        slideDown: 'slideDown 0.3s ease-out',
        shimmer:   'shimmer 1.8s infinite linear',
      },
    },
  },

  plugins: [],
};
