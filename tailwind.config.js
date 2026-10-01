import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', 'sans-serif'],
                cairo: ['Cairo', 'sans-serif'],
                inter: ['Inter', 'sans-serif'],
            },
            colors: {
                gray: {
                    450: '#9ca3af',
                    550: '#6b7280',
                    850: '#1f2937',
                },
                surface: {
                    abyss: 'var(--surface-abyss)',
                    dim: 'var(--surface-dim)',
                    DEFAULT: 'var(--surface)',
                    bright: 'var(--surface-bright)',
                    high: 'var(--surface-high)',
                },
                brand: {
                    purple: '#441188',
                    'purple-dark': '#350d6b',
                    'purple-light': '#8844dd',
                    'purple-medium': '#5a1aa8',
                    'purple-deep': '#2d0b5e',
                    orange: '#ff6600',
                    'orange-light': '#ff9955',
                    emerald: '#10b981',
                    blue: '#3b82f6',
                    red: '#ef4444',
                    amber: '#f59e0b',
                },
                'brand-text': 'var(--brand-text)',
                'brand-muted': 'var(--brand-muted)',
                'brand-border': 'var(--brand-border)',
                glow: {
                    purple: 'var(--brand-purple-glow)',
                    orange: 'var(--brand-orange-glow)',
                    emerald: 'var(--brand-emerald-glow)',
                    blue: 'var(--brand-blue-glow)',
                },
            },
            borderRadius: {
                sm: '6px',
                md: '10px',
                lg: '14px',
                xl: '16px',
            },
            animation: {
                'spin-slow': 'spin 3s linear infinite',
            },
            fontSize: {
                '2xs': ['0.625rem', { lineHeight: '0.875rem' }],
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
