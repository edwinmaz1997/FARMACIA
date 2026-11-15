/** Configuración de Tailwind. El CSS se compila en GitHub Actions (y localmente con: npx tailwindcss@3 -i assets/src/app.css -o assets/app.css --minify) */
const colores = 'brand|slate|indigo|emerald|rose|amber|sky|red';
module.exports = {
  content: ['./*.php', './*.html', './app/**/*.php'],
  safelist: [
    { pattern: new RegExp(`^bg-(${colores})-(50|100|500|600)$`) },
    { pattern: new RegExp(`^text-(${colores})-(600|700)$`) },
    { pattern: new RegExp(`^ring-(${colores})-600/15$`) },
    { pattern: new RegExp(`^border-(${colores})-300$`), variants: ['hover'] },
    { pattern: /^grid-cols-[1-4]$/, variants: ['xl'] },
  ],
  theme: {
    extend: {
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      colors: {
        brand: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' },
      },
      boxShadow: { soft: '0 1px 2px rgba(15,23,42,.04), 0 4px 16px -4px rgba(15,23,42,.08)' },
    },
  },
  plugins: [],
};
