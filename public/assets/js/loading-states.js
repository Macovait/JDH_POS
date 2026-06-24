/**
 * Loading States Manager
 * Provides visual feedback during AJAX operations
 * @version 1.0.0
 */

class LoadingManager {
    constructor() {
        this.overlays = new Map();
        this.spinnerHTML = `
            <div class="loading-overlay" style="
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(11, 17, 32, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                backdrop-filter: blur(4px);
            ">
                <div class="loading-content" style="text-align: center;">
                    <div class="spinner" style="
                        width: 48px;
                        height: 48px;
                        border: 3px solid rgba(251, 191, 36, 0.3);
                        border-top-color: #fbbf24;
                        border-radius: 50%;
                        animation: spin 0.8s linear infinite;
                        margin: 0 auto 16px;
                    "></div>
                    <div class="loading-text" style="
                        color: #fbbf24;
                        font-size: 14px;
                        font-weight: 500;
                    ">Loading...</div>
                </div>
            </div>
        `;
        
        // Add animation styles
        if (!document.getElementById('loading-styles')) {
            const style = document.createElement('style');
            style.id = 'loading-styles';
            style.textContent = `
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                .loading-overlay {
                    transition: opacity 0.3s ease;
                }
                .skeleton {
                    background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
                    background-size: 200% 100%;
                    animation: shimmer 1.5s infinite;
                    border-radius: 4px;
                }
                @keyframes shimmer {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
                .btn-loading {
                    position: relative;
                    color: transparent !important;
                    pointer-events: none;
                }
                .btn-loading::after {
                    content: '';
                    position: absolute;
                    width: 16px;
                    height: 16px;
                    top: 50%;
                    left: 50%;
                    margin-left: -8px;
                    margin-top: -8px;
                    border: 2px solid transparent;
                    border-top-color: currentColor;
                    border-radius: 50%;
                    animation: spin 0.6s linear infinite;
                    color: inherit;
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    /**
     * Show loading overlay on an element
     */
    show(elementOrSelector, text = 'Loading...') {
        const element = typeof elementOrSelector === 'string' 
            ? document.querySelector(elementOrSelector) 
            : elementOrSelector;
            
        if (!element) return;
        
        // Ensure element has relative positioning
        const currentPosition = window.getComputedStyle(element).position;
        if (currentPosition === 'static') {
            element.style.position = 'relative';
        }
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.innerHTML = this.spinnerHTML.replace('Loading...', text);
        const overlayElement = overlay.firstElementChild;
        
        element.appendChild(overlayElement);
        this.overlays.set(element, overlayElement);
        
        return overlayElement;
    }
    
    /**
     * Hide loading overlay
     */
    hide(elementOrSelector) {
        const element = typeof elementOrSelector === 'string' 
            ? document.querySelector(elementOrSelector) 
            : elementOrSelector;
            
        if (!element) return;
        
        const overlay = this.overlays.get(element);
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                this.overlays.delete(element);
            }, 300);
        }
    }
    
    /**
     * Show loading state on a button
     */
    buttonLoading(buttonOrSelector, loadingText = null) {
        const button = typeof buttonOrSelector === 'string'
            ? document.querySelector(buttonOrSelector)
            : buttonOrSelector;
            
        if (!button) return;
        
        // Store original text
        button.dataset.originalText = button.innerHTML;
        button.classList.add('btn-loading');
        
        if (loadingText) {
            button.innerHTML = loadingText;
        }
        
        button.disabled = true;
    }
    
    /**
     * Restore button to normal state
     */
    buttonReset(buttonOrSelector) {
        const button = typeof buttonOrSelector === 'string'
            ? document.querySelector(buttonOrSelector)
            : buttonOrSelector;
            
        if (!button) return;
        
        button.classList.remove('btn-loading');
        button.disabled = false;
        
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }
    
    /**
     * Create skeleton loading placeholder
     */
    skeleton(width = '100%', height = '20px', count = 1) {
        const container = document.createElement('div');
        
        for (let i = 0; i < count; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton';
            skeleton.style.width = width;
            skeleton.style.height = height;
            skeleton.style.marginBottom = '8px';
            container.appendChild(skeleton);
        }
        
        return container;
    }
    
    /**
     * Show skeleton loading in a container
     */
    showSkeleton(containerSelector, itemCount = 3) {
        const container = document.querySelector(containerSelector);
        if (!container) return;
        
        container.dataset.originalContent = container.innerHTML;
        container.innerHTML = '';
        
        const skeletonContainer = this.skeleton('100%', '48px', itemCount);
        container.appendChild(skeletonContainer);
    }
    
    /**
     * Hide skeleton and restore original content
     */
    hideSkeleton(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;
        
        if (container.dataset.originalContent) {
            container.innerHTML = container.dataset.originalContent;
            delete container.dataset.originalContent;
        }
    }
    
    /**
     * Full page loading overlay
     */
    fullPage(text = 'Loading...') {
        const overlay = document.createElement('div');
        overlay.id = 'fullPageLoading';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #0b1120;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            transition: opacity 0.3s ease;
        `;
        overlay.innerHTML = `
            <div style="text-align: center;">
                <div style="
                    width: 64px;
                    height: 64px;
                    border: 4px solid rgba(251, 191, 36, 0.2);
                    border-top-color: #fbbf24;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    margin: 0 auto 24px;
                "></div>
                <div style="color: #fbbf24; font-size: 18px; font-weight: 600;">${text}</div>
                <div style="color: #64748b; font-size: 14px; margin-top: 8px;">Please wait...</div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        return overlay;
    }
    
    /**
     * Hide full page loading
     */
    hideFullPage() {
        const overlay = document.getElementById('fullPageLoading');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 300);
        }
    }
}

// Create global instance
const loadingManager = new LoadingManager();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { LoadingManager, loadingManager };
}
