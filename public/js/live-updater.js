/*
 * createLiveUpdater - WebSocket-based live update client for real-time UI refresh.
 *
 * Usage:
 *   window.createLiveUpdater({
 *     wsUrl: window.WS_URL || (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname,
 *     statusSelector: '#status',
 *     onData: function (msg) { ... },
 *   });
 *
 * statusLabels and statusColors are customizable.
 */
(function () {
    function createLiveUpdater(options) {
        const cfg = Object.assign(
            {
                wsUrl: window.WS_URL || (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname,
                statusSelector: null,
                statusLabels: {
                    live: '● Live',
                    offline: '● Offline',
                    sleeping: '● Sleeping',
                },
                statusColors: {
                    live: '#10b981',
                    offline: '#ef4444',
                    sleeping: '#f59e0b',
                },
                onData: null,
                onError: null,
            },
            options || {}
        );

        function getStatusEl() {
            if (!cfg.statusSelector) {
                return null;
            }
            return document.querySelector(cfg.statusSelector);
        }

        function setStatus(kind) {
            const el = getStatusEl();
            if (!el) {
                return;
            }
            if (cfg.statusLabels[kind]) {
                el.textContent = cfg.statusLabels[kind];
            }
            if (cfg.statusColors[kind]) {
                el.style.color = cfg.statusColors[kind];
            }
        }

        let ws = null;
        let reconnectTimer = null;
        let isSleeping = false;

        function connect() {
            ws = new WebSocket(cfg.wsUrl);
            ws.onopen = function () {
                setStatus('live');
            };
            ws.onclose = function () {
                setStatus('offline');
                scheduleReconnect();
            };
            ws.onerror = function (err) {
                setStatus('offline');
                if (typeof cfg.onError === 'function') {
                    cfg.onError(err);
                }
            };
            ws.onmessage = function (event) {
                let msg = event.data;
                try {
                    let parsed = JSON.parse(msg);
                    if (parsed && parsed.type === 'order_update' && typeof cfg.onOrderUpdate === 'function') {
                        cfg.onOrderUpdate(parsed.data);
                        return;
                    }
                } catch (e) {
                    // Not JSON, fallback to onData
                }
                if (typeof cfg.onData === 'function') {
                    cfg.onData(msg);
                }
            };
        }

        function scheduleReconnect() {
            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
            }
            reconnectTimer = setTimeout(() => {
                connect();
            }, 5000);
        }

        function enterSleepMode() {
            isSleeping = true;
            setStatus('sleeping');
            if (ws) {
                ws.close();
            }
        }

        function wakeClient() {
            if (!isSleeping) {
                return;
            }
            isSleeping = false;
            connect();
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                enterSleepMode();
            } else {
                wakeClient();
            }
        });

        connect();

        return {
            wake: wakeClient,
            sleep: enterSleepMode,
            stop: function () {
                if (ws) {
                    ws.close();
                }
                if (reconnectTimer) {
                    clearTimeout(reconnectTimer);
                }
                document.removeEventListener('visibilitychange', function () {});
            },
        };
    }

    window.createLiveUpdater = createLiveUpdater;
})();
