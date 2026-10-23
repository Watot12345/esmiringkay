/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
    './resources/js/**/*.ts',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        bg: 'var(--bg)',
        surface: 'var(--surface)',
        'surface-2': 'var(--surface-2)',
        'text-base': 'var(--text)',
        'text-muted': 'var(--text-muted)',
        primary: 'var(--primary)',
        coral: 'var(--accent-coral)',
        mint: 'var(--accent-mint)',
        gold: 'var(--accent-gold)',
        danger: 'var(--danger)',
        border: 'var(--border)',
      },
      borderRadius: {
        card: '20px',
        pill: '999px',
        input: '12px',
      },
      fontFamily: {
        display: ['Sora', 'Poppins', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
      },
      boxShadow: {
        glow: '0 8px 30px rgba(139, 124, 255, 0.25)',
        'glow-coral': '0 8px 30px rgba(255, 138, 122, 0.25)',
        'glow-mint': '0 8px 30px rgba(94, 234, 212, 0.25)',
      },
      transitionTimingFunction: {
        calm: 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
    },
  },
  plugins: [],
}
