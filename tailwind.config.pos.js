/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/pos/pos.php",
    "./public/pos/pos-stock.css",
    "./public/pos/pos-safelist.html",
    // Raw strings inject opacity-modifier classes that the file scanner strips
    { raw: [
      // bg with opacity
      'bg-amber-500/10 bg-amber-500/20 bg-amber-500/30',
      'bg-blue-500/10 bg-blue-500/20',
      'bg-emerald-500/10 bg-emerald-500/20 bg-emerald-500/40',
      'bg-red-500/10 bg-red-500/20 bg-red-500/30 bg-red-500/40 bg-red-500/80',
      'bg-slate-700/30 bg-slate-700/40 bg-slate-700/50 bg-slate-700/60',
      'bg-slate-800/40 bg-slate-800/60 bg-slate-900/60',
      'bg-black/30 bg-black/40 bg-black/50 bg-black/60 bg-black/70 bg-black/80 bg-black/90',
      'bg-white/5 bg-white/10 bg-white/20',
      // border with opacity
      'border-amber-500/20 border-amber-500/30 border-amber-500/40 border-amber-500/50',
      'border-blue-500/30 border-emerald-500/30 border-red-500/30 border-violet-500/30',
      'border-slate-600/50 border-slate-600/60 border-slate-700/40 border-slate-700/60',
      'border-white/5 border-white/10 border-white/20',
      // text with opacity
      'text-amber-400/70 text-amber-500/60 text-white/50 text-white/60 text-white/70 text-white/80',
      // gradient/shadow with opacity
      'from-amber-500/15 via-slate-900/10 via-slate-900/60 shadow-amber-500/20 shadow-amber-500/30',
      // hover with opacity
      'hover:bg-amber-500/20 hover:bg-red-500/20 hover:bg-emerald-500/20 hover:bg-slate-700/60',
      'hover:border-amber-500/40 hover:shadow-amber-500/20',
    ].join(' ') },
  ],
  theme: {
    extend: {
      screens: { 'xs': '400px' },
      spacing: { '13': '3.25rem' },
      colors: {
        accent: '#fbbf24',
        'bg-dark': '#0f172a',
      },
      animation: {
        'fade-in':    'fadeIn 0.3s ease-out',
        'slide-in':   'slideIn 0.3s ease-out',
        'pulse-slow': 'pulse 2s ease-in-out infinite',
        'spin-slow':  'spin 1s linear infinite',
      },
      keyframes: {
        fadeIn: {
          '0%':   { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideIn: {
          '0%':   { transform: 'translateX(100%)', opacity: '0' },
          '100%': { transform: 'translateX(0)',    opacity: '1' },
        },
      },
    },
  },
  // safelist with pattern+variants generates the /opacity modifier classes
  // even though the content scanner cannot extract them from files
  safelist: [
    // bg-{color}/{opacity}
    { pattern: /^bg-(amber|blue|emerald|red|violet)-(500|400|300)(\/10|\/20|\/30|\/40|\/80)?$/, variants: ['hover'] },
    { pattern: /^bg-slate-(700|800|900)(\/30|\/40|\/50|\/60)?$/, variants: ['hover'] },
    { pattern: /^bg-black\/(30|40|50|60|70|80|90)$/ },
    { pattern: /^bg-white\/(5|10|20)$/ },
    // border-{color}/{opacity}
    { pattern: /^border-(amber|blue|emerald|red|violet|slate)-(500|600|700)(\/20|\/30|\/40|\/50|\/60)?$/, variants: ['hover', 'focus'] },
    { pattern: /^border-white\/(5|10|20)$/ },
    // text-{color}/{opacity}
    { pattern: /^text-(amber|white)-(400|500)\/(50|60|70|80)?$/ },
    { pattern: /^text-white\/(50|60|70|80)$/ },
    // from/via/shadow with opacity
    { pattern: /^from-(amber|slate)-(500|900)\/(10|15|60)?$/ },
    { pattern: /^via-slate-900\/(10|60)$/ },
    { pattern: /^shadow-(amber)-500\/(20|30)$/ },
    // standalone utilities
    'h-13', 'w-13',
    'saturate-0', 'saturate-100',
    'blur', 'blur-sm',
    'grayscale', 'grayscale-0',
    'aspect-square',
    'pointer-events-none', 'pointer-events-auto',
    'select-none', 'select-text',
    'touch-manipulation',
    'resize-none',
    'appearance-none',
    'min-h-screen', 'h-screen', 'w-screen',
    'opacity-0', 'opacity-5', 'opacity-10', 'opacity-20', 'opacity-30',
    'opacity-40', 'opacity-50', 'opacity-60', 'opacity-70', 'opacity-80',
    'opacity-90', 'opacity-100',
    'active:scale-95', 'active:scale-90', 'active:opacity-80',
    'group-hover:opacity-100', 'group-hover:scale-105', 'group-hover:text-amber-400',
    'hover:opacity-100', 'hover:scale-105',
    'focus:outline-none', 'focus:ring-1', 'focus:ring-2',
    'focus:ring-amber-400', 'focus:ring-amber-500',
    'disabled:opacity-50', 'disabled:cursor-not-allowed',
  ],
  plugins: [],
}
