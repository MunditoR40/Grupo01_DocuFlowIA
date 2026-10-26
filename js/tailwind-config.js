/* ==================================================================
   DocuFlow AI — Configuración de Tailwind CSS
   IMPORTANTE: debe cargarse DESPUÉS del CDN de Tailwind.
   ================================================================== */
tailwind.config = {
    darkMode: 'class', // ← Requisito: Modo oscuro activado mediante clase .dark
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
                navy: {
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                }
            },
            animation: {
                'fade-in': 'fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                'float': 'float 5s ease-in-out infinite',
                'float-delay': 'float 5s ease-in-out 2.5s infinite',
                'pulse-glow': 'pulseGlow 2s ease-in-out infinite',
                'scanner': 'scanner 2.2s cubic-bezier(0.4, 0, 0.2, 1) infinite',
                'toast-in': 'toastIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                'toast-out': 'toastOut 0.3s ease-in forwards',
                'shimmer': 'shimmer 1.8s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-14px)' },
                },
                pulseGlow: {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(99, 102, 241, 0.7)' },
                    '50%': { boxShadow: '0 0 0 16px rgba(99, 102, 241, 0)' },
                },
                scanner: {
                    '0%': { top: '0%', opacity: '0.9' },
                    '50%': { top: '96%', opacity: '1' },
                    '100%': { top: '0%', opacity: '0.9' },
                },
                toastIn: {
                    '0%': { opacity: '0', transform: 'translateX(100%) scale(0.92)' },
                    '100%': { opacity: '1', transform: 'translateX(0) scale(1)' },
                },
                toastOut: {
                    '0%': { opacity: '1', transform: 'translateX(0) scale(1)' },
                    '100%': { opacity: '0', transform: 'translateX(100%) scale(0.92)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
            },
        }
    }
};
