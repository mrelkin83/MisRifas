/**
 * MisRifas WebSocket Client
 * Gestiona la conexion WebSocket para funcionalidades en tiempo real.
 *
 * Requiere: shared.js (para window.log)
 *
 * Uso:
 *   const ws = new MisRifasWS('tapazo_abc123');
 *   ws.on('player_joined', (data) => { ... });
 *   ws.send('join', { nombre: 'Juan' });
 */

class MisRifasWS {
    constructor(tapazoId) {
        this.tapazoId = tapazoId;
        this.ws = null;
        this.listeners = {};
        this.reconnectDelay = 1000;
        this.maxReconnectDelay = 30000;
        this.maxReconnectAttempts = 20;
        this.reconnectAttempts = 0;
        this.pingInterval = null;
        this.pollingInterval = null;
        this.connect();
    }

    connect() {
        const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = location.hostname;
        const port = document.querySelector('meta[name="ws-port"]')?.content || '8081';
        const url = protocol + '//' + host + ':' + port + '/?tapazo_id=' + this.tapazoId;

        try {
            this.ws = new WebSocket(url);
        } catch (e) {
            if (typeof log === 'function') log('[WS] WebSocket no disponible, usando polling fallback');
            this.startPolling();
            return;
        }

        this.ws.onopen = () => {
            if (typeof log === 'function') log('[WS] Conectado a tapazo ' + this.tapazoId);
            this.reconnectDelay = 1000;
            this.reconnectAttempts = 0;
            this.emit('connected', { tapazo_id: this.tapazoId });
            this.stopPolling();
            this.pingInterval = setInterval(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(JSON.stringify({ event: 'ping' }));
                }
            }, 30000);
        };

        this.ws.onmessage = (e) => {
            try {
                const msg = JSON.parse(e.data);
                if (msg.event && msg.event \!== 'pong') {
                    if (typeof log === 'function') log('[WS] Evento:', msg.event, msg.data);
                }
                this.emit(msg.event, msg.data);
            } catch (err) {
                if (typeof log === 'function') log('[WS] Mensaje invalido:', e.data);
            }
        };

        this.ws.onclose = () => {
            if (typeof log === 'function') log('[WS] Desconectado. Reconectando...');
            clearInterval(this.pingInterval);
            this.emit('disconnected', {});
            this.scheduleReconnect();
        };

        this.ws.onerror = () => {
            if (typeof log === 'function') log('[WS] Error de conexion');
        };
    }

    startPolling() {
        if (this.pollingInterval) return;
        if (typeof log === 'function') log('[WS] Iniciando polling cada 3s para tapazo ' + this.tapazoId);
        this.pollingInterval = setInterval(async () => {
            try {
                const response = await fetch('/api/tapazo/status.php?tapazo_id=' + this.tapazoId);
                const data = await response.json();
                if (data && data.event) {
                    this.emit(data.event, data.data);
                } else if (data) {
                    this.emit('status_update', data);
                }
            } catch (err) {
                if (typeof log === 'function') log('[WS] Error en polling:', err);
            }
        }, 3000);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    scheduleReconnect() {
        this.reconnectAttempts++;
        if (this.reconnectAttempts > this.maxReconnectAttempts) {
            if (typeof log === 'function') log('[WS] Maximo de reintentos alcanzado. Usando polling.');
            this.startPolling();
            return;
        }
        setTimeout(() => {
            this.reconnectDelay = Math.min(this.reconnectDelay * 1.5, this.maxReconnectDelay);
            this.connect();
        }, this.reconnectDelay);
    }

    on(event, callback) {
        if (\!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(callback);
    }

    off(event, callback) {
        if (\!this.listeners[event]) return;
        this.listeners[event] = this.listeners[event].filter(cb => cb \!== callback);
    }

    emit(event, data) {
        if (\!this.listeners[event]) return;
        this.listeners[event].forEach(cb => {
            try { cb(data); } catch (e) { console.error('[WS] Error en listener:', e); }
        });
    }

    send(event, data = {}) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({ event, data }));
        }
    }

    disconnect() {
        clearInterval(this.pingInterval);
        this.stopPolling();
        this.reconnectAttempts = this.maxReconnectAttempts + 1;
        if (this.ws) { this.ws.close(); this.ws = null; }
    }
}
