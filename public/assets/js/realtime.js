/**
 * Real-time Updates Client
 * Uses Server-Sent Events (SSE) for live updates
 */

class RealtimeClient {
    constructor(options = {}) {
        this.url = options.url || '/api/realtime/events.php';
        this.reconnectDelay = options.reconnectDelay || 3000;
        this.eventSource = null;
        this.listeners = {};
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
        this.isConnected = false;
        
        this.init();
    }
    
    init() {
        if (!window.EventSource) {
            console.warn('SSE not supported in this browser');
            this.fallbackToPolling();
            return;
        }
        
        this.connect();
    }
    
    connect() {
        try {
            this.eventSource = new EventSource(this.url);
            
            this.eventSource.onopen = () => {
                console.log('Realtime connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.emit('connected');
            };
            
            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.emit('message', data);
                } catch (e) {
                    this.emit('message', event.data);
                }
            };
            
            this.eventSource.onerror = (error) => {
                console.error('Realtime error:', error);
                this.isConnected = false;
                this.emit('error', error);
                
                this.eventSource.close();
                this.scheduleReconnect();
            };
            
            // Listen for specific events
            this.eventSource.addEventListener('sale', (e) => {
                const data = JSON.parse(e.data);
                this.emit('sale', data);
                this.showNotification('New Sale', `Receipt #${data.receipt_number} - $${data.final_amount}`);
            });
            
            this.eventSource.addEventListener('notification', (e) => {
                const data = JSON.parse(e.data);
                this.emit('notification', data);
            });
            
            this.eventSource.addEventListener('ping', (e) => {
                const data = JSON.parse(e.data);
                this.emit('ping', data);
            });
            
            this.eventSource.addEventListener('connected', (e) => {
                const data = JSON.parse(e.data);
                console.log('Connected as tenant:', data.tenant_id);
            });

            this.eventSource.addEventListener('inventory_alert', (e) => {
                const data = JSON.parse(e.data);
                this.emit('inventory_alert', data);
                this.showInventoryAlert(data);
            });

            this.eventSource.addEventListener('metrics', (e) => {
                const data = JSON.parse(e.data);
                this.emit('metrics', data);
            });
            
        } catch (error) {
            console.error('Failed to connect:', error);
            this.scheduleReconnect();
        }
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnect attempts reached');
            this.emit('disconnected');
            return;
        }
        
        this.reconnectAttempts++;
        console.log(`Reconnecting in ${this.reconnectDelay}ms (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => this.connect(), this.reconnectDelay);
    }
    
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
        return this;
    }
    
    off(event, callback) {
        if (!this.listeners[event]) return;
        const index = this.listeners[event].indexOf(callback);
        if (index > -1) {
            this.listeners[event].splice(index, 1);
        }
        return this;
    }
    
    emit(event, data) {
        if (!this.listeners[event]) return;
        this.listeners[event].forEach(callback => {
            try {
                callback(data);
            } catch (e) {
                console.error('Event listener error:', e);
            }
        });
    }
    
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.isConnected = false;
        }
    }
    
    showNotification(title, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body: message });
        }
    }

    showInventoryAlert(data) {
        if (data.low_stock && data.low_stock.length > 0) {
            const count = data.low_stock.length;
            this.showNotification(
                'Low Stock Alert',
                `${count} product${count > 1 ? 's' : ''} running low on stock`
            );
        }

        if (data.out_of_stock && data.out_of_stock.length > 0) {
            const count = data.out_of_stock.length;
            this.showNotification(
                'Out of Stock Alert',
                `${count} product${count > 1 ? 's' : ''} out of stock`
            );
        }
    }
    
    fallbackToPolling() {
        console.log('Using polling fallback');
        // Poll every 30 seconds
        setInterval(() => {
            fetch('/ajax/get_today_sales.php')
                .then(r => r.json())
                .then(data => this.emit('poll', data))
                .catch(e => console.error('Poll error:', e));
        }, 30000);
    }
}

// Browser notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

// Global instance
window.realtime = new RealtimeClient();

// Usage examples:
// window.realtime.on('sale', (sale) => console.log('New sale:', sale));
// window.realtime.on('connected', () => console.log('Connected!'));
