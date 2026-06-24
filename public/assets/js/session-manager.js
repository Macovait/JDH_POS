/**
 * Session Manager
 * Handles session timeout warnings and auto-logout
 * @version 1.0.0
 */

class SessionManager {
    constructor(options = {}) {
        // Use server-provided config or sensible defaults
        const config = (typeof SECURITY_CONFIG !== 'undefined' && SECURITY_CONFIG) ? SECURITY_CONFIG : {};
        const sessionConfig = config.session || {};
        
        // Session timeout from config or default 30 minutes
        this.sessionTimeout = options.sessionTimeout || sessionConfig.timeout || 1800;
        // Warning shown before timeout (default 5 minutes)
        this.warningTime = options.warningTime || sessionConfig.warning_time || 300;
        // Check interval from config or default 10 seconds
        this.checkInterval = options.checkInterval || sessionConfig.check_interval || 10;
        
        this.warningShown = false;
        this.countdownInterval = null;
        this.activityTimer = null;
        this.lastActivity = Date.now();
        
        this.init();
    }
    
    init() {
        // Monitor user activity
        this.bindActivityEvents();
        
        // Start monitoring
        this.startMonitoring();
        
        // Show remaining time in console
        console.log(`SessionManager: Timeout in ${this.sessionTimeout}s, warning at ${this.warningTime}s remaining`);
    }
    
    bindActivityEvents() {
        const events = ['click', 'keypress', 'mousemove', 'scroll', 'touchstart'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateActivity();
            }, { passive: true });
        });
    }
    
    updateActivity() {
        this.lastActivity = Date.now();
        
        // Reset warning if shown
        if (this.warningShown) {
            this.hideWarning();
            this.warningShown = false;
        }
    }
    
    startMonitoring() {
        // Check at configured interval (default 10 seconds)
        const intervalMs = (this.checkInterval || 10) * 1000;
        setInterval(() => {
            this.checkSessionStatus();
        }, intervalMs);
    }
    
    checkSessionStatus() {
        const elapsed = Math.floor((Date.now() - this.lastActivity) / 1000);
        const remaining = this.sessionTimeout - elapsed;
        
        if (remaining <= this.warningTime && remaining > 0 && !this.warningShown) {
            this.showWarning(remaining);
        }
        
        if (remaining <= 0) {
            this.handleTimeout();
        }
    }
    
    showWarning(remainingSeconds) {
        this.warningShown = true;
        
        // Create warning modal
        const modal = document.createElement('div');
        modal.id = 'sessionWarningModal';
        modal.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: system-ui, -apple-system, sans-serif;
            ">
                <div style="
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 16px;
                    padding: 32px;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
                ">
                    <div style="font-size: 48px; margin-bottom: 16px;">⏳</div>
                    <h3 style="color: #fbbf24; margin: 0 0 12px 0; font-size: 20px;">Session Expiring Soon</h3>
                    <p style="color: #94a3b8; margin: 0 0 24px 0; line-height: 1.5;">
                        Your session will expire in <span id="countdownTimer" style="color: #fbbf24; font-weight: bold;">${remainingSeconds}</span> seconds due to inactivity.
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button onclick="sessionManager.extendSession()" style="
                            background: #fbbf24;
                            color: #0f172a;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            font-weight: 600;
                            cursor: pointer;
                            font-size: 14px;
                        ">Stay Logged In</button>
                        <button onclick="sessionManager.logout()" style="
                            background: transparent;
                            color: #94a3b8;
                            border: 1px solid #334155;
                            padding: 12px 24px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 14px;
                        ">Logout Now</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Start countdown
        this.startCountdown(remainingSeconds);
        
        // Ping server to keep session alive while modal is open
        this.pingInterval = setInterval(() => {
            this.pingServer();
        }, 30000);
    }
    
    startCountdown(seconds) {
        const timerElement = document.getElementById('countdownTimer');
        if (!timerElement) return;
        
        let remaining = seconds;
        
        this.countdownInterval = setInterval(() => {
            remaining--;
            if (timerElement) {
                timerElement.textContent = remaining;
            }
            
            if (remaining <= 0) {
                clearInterval(this.countdownInterval);
                this.handleTimeout();
            }
        }, 1000);
    }
    
    hideWarning() {
        const modal = document.getElementById('sessionWarningModal');
        if (modal) {
            modal.remove();
        }
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
        }
    }
    
    extendSession() {
        this.hideWarning();
        this.warningShown = false;
        this.lastActivity = Date.now();
        
        // Ping server to reset session
        this.pingServer();
        
        // Show toast notification
        this.showToast('Session extended successfully', 'success');
    }
    
    async pingServer() {
        try {
            const response = await fetch('ajax/get_csrf_token.php');
            if (response.ok) {
                console.log('Session pinged successfully');
            }
        } catch (error) {
            console.warn('Session ping failed:', error);
        }
    }
    
    handleTimeout() {
        this.hideWarning();
        
        // Show timeout message
        const modal = document.createElement('div');
        modal.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.8);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: system-ui, -apple-system, sans-serif;
            ">
                <div style="
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 16px;
                    padding: 32px;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                ">
                    <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
                    <h3 style="color: #ef4444; margin: 0 0 12px 0;">Session Expired</h3>
                    <p style="color: #94a3b8; margin: 0 0 24px 0;">
                        Your session has expired due to inactivity. Please log in again to continue.
                    </p>
                    <a href="auth/login.php" style="
                        background: #fbbf24;
                        color: #0f172a;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-weight: 600;
                        text-decoration: none;
                        display: inline-block;
                        font-size: 14px;
                    ">Login Again</a>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Redirect after 3 seconds
        setTimeout(() => {
            window.location.href = '/JDH_POS/public/auth/login.php?error=session_expired';
        }, 3000);
    }

    logout() {
        window.location.href = '/JDH_POS/public/auth/logout.php';
    }
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#059669' : '#3b82f6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// Initialize session manager when DOM is ready
let sessionManager;
document.addEventListener('DOMContentLoaded', () => {
    // SECURITY_CONFIG is loaded from server via get_security_config.php
    const config = (typeof SECURITY_CONFIG !== 'undefined' && SECURITY_CONFIG) ? SECURITY_CONFIG : {};
    const sessionConfig = config.session || {};
    
    sessionManager = new SessionManager({
        sessionTimeout: sessionConfig.timeout || 1800,
        warningTime: sessionConfig.warning_time || 300,
        checkInterval: sessionConfig.check_interval || 10
    });
});

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
