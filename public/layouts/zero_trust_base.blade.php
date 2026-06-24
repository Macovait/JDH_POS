<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page_title }} | {{ app_name }}</title>
    <meta name="description" content="{{ meta_description }}">
    <meta name="keywords" content="{{ meta_keywords }}">
    
    <meta property="og:title" content="{{ og_title }}">
    <meta property="og:description" content="{{ og_description }}">
    <meta property="og:type" content="website">
    
    <link rel="icon" type="image/png" href="{{ favicon_url }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    
    <style>
        :root {
            --brand-primary: {{ brand_primary }};
            --brand-primary-dark: {{ brand_primary_dark }};
            --brand-primary-light: {{ brand_primary_light }};
            --brand-accent: {{ brand_accent }};
            --brand-accent-dark: {{ brand_accent_dark }};
        }
        .bg-primary { background-color: var(--brand-primary) !important; }
        .text-primary { color: var(--brand-primary) !important; }
        .bg-amber-500 { background-color: var(--brand-accent) !important; }
        .text-amber-400 { color: var(--brand-accent) !important; }
    </style>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .glass-panel {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .card-base {
            background: rgba(17, 24, 39, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 0.75rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-base:hover {
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.3);
        }
        
        .card-interactive {
            cursor: pointer;
        }
        
        .card-interactive:active {
            transform: scale(0.98);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-glow {
            position: relative;
            overflow: hidden;
        }
        
        .btn-glow::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s;
        }
        
        .btn-glow:hover::before {
            transform: translateX(100%);
        }
        
        .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 {
            position: relative;
            overflow: hidden;
        }
        
        .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, currentColor 0%, transparent 70%);
            opacity: 0.05;
            transform: translate(30%, -30%);
        }
        
        .input-dark {
            background: rgba(31, 41, 55, 0.5);
            border: 1px solid rgba(55, 65, 81, 0.5);
            transition: all 0.2s;
        }
        
        .input-dark:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        .input-dark::placeholder {
            color: #6B7280;
        }
        
        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        .table-header-cell {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9CA3AF;
        }
        
        .table-body-cell {
            font-size: 0.875rem;
            color: #E5E7EB;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.3);
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(75, 85, 99, 0.5);
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(75, 85, 99, 0.7);
        }
        
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .animate-pulse-slow {
            animation: pulse-slow 3s ease-in-out infinite;
        }
        
        .skeleton {
            background: linear-gradient(90deg, rgba(55, 65, 81, 0.3) 25%, rgba(75, 85, 99, 0.3) 50%, rgba(55, 65, 81, 0.3) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
    
    {{ custom_css }}
</head>
<body class="bg-surface-900 text-surface-100 min-h-screen">
    <div id="app" class="flex min-h-screen">
        {{ sidebar }}
        
        <main class="flex-1 ml-0 md:ml-72 transition-all duration-300">
            {{ header }}
            
            <div class="p-4 md:p-6 lg:p-8">
                {{ content }}
            </div>
        </main>
    </div>
    
    {{ modals }}
    
    <script>
        const app = {
            config: {
                baseUrl: '{{ base_url }}',
                csrfToken: '{{ csrf_token }}',
                locale: '{{ locale }}',
            },
            
            init() {
                this.setupEventListeners();
                this.initializeComponents();
            },
            
            setupEventListeners() {
                document.addEventListener('click', (e) => {
                    if (e.target.closest('[data-dropdown]')) {
                        this.toggleDropdown(e.target.closest('[data-dropdown]'));
                    }
                });
                
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.closeAllDropdowns();
                    }
                });
            },
            
            initializeComponents() {
                this.initDropdowns();
                this.initModals();
                this.initTooltips();
            },
            
            initDropdowns() {
                document.querySelectorAll('[data-dropdown-content]').forEach(el => {
                    el.classList.add('hidden');
                });
            },
            
            toggleDropdown(button) {
                const content = button.querySelector('[data-dropdown-content]');
                const isHidden = content.classList.contains('hidden');
                
                this.closeAllDropdowns();
                
                if (isHidden) {
                    content.classList.remove('hidden');
                    content.classList.add('');
                }
            },
            
            closeAllDropdowns() {
                document.querySelectorAll('[data-dropdown-content]').forEach(el => {
                    el.classList.add('hidden');
                });
            },
            
            initModals() {
                document.querySelectorAll('[data-modal]').forEach(modal => {
                    const id = modal.dataset.modal;
                    const closeBtn = modal.querySelector('[data-modal-close]');
                    
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => this.closeModal(id));
                    }
                    
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) {
                            this.closeModal(id);
                        }
                    });
                });
            },
            
            openModal(id) {
                const modal = document.querySelector(`[data-modal="${id}"]`);
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                }
            },
            
            closeModal(id) {
                const modal = document.querySelector(`[data-modal="${id}"]`);
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                }
            },
            
            initTooltips() {
                document.querySelectorAll('[data-tooltip]').forEach(el => {
                    el.addEventListener('mouseenter', (e) => {
                        this.showTooltip(e.target);
                    });
                    el.addEventListener('mouseleave', () => {
                        this.hideTooltip();
                    });
                });
            },
            
            showTooltip(element) {
                const text = element.dataset.tooltip;
                const tooltip = document.createElement('div');
                tooltip.className = 'absolute z-50 px-2 py-1 text-xs bg-surface-700 text-white rounded shadow-lg';
                tooltip.textContent = text;
                document.body.appendChild(tooltip);
                
                const rect = element.getBoundingClientRect();
                tooltip.style.top = `${rect.top - tooltip.offsetHeight - 8}px`;
                tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;
            },
            
            hideTooltip() {
                document.querySelectorAll('[data-tooltip]').forEach(t => {
                    const tooltip = document.querySelector('.tooltip');
                    if (tooltip) tooltip.remove();
                });
            },
            
            async request(url, options = {}) {
                const defaultOptions = {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.config.csrfToken,
                    },
                };
                
                const response = await fetch(url, { ...defaultOptions, ...options });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.json();
            },
            
            notify(message, type = 'info') {
                const colors = {
                    success: 'bg-green-600',
                    error: 'bg-red-600',
                    warning: 'bg-yellow-600',
                    info: 'bg-blue-600',
                };
                
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 `;
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            },
        };
        
        document.addEventListener('DOMContentLoaded', () => app.init());
    </script>
    
    {{ custom_js }}
</body>
</html>