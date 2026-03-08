(function () {
    function createLivePoller(options) {
        const cfg = Object.assign(
            {
                endpoint: '?fetch_view=1',
                pollIntervalMs: 5000,
                backgroundPollMultiplier: 3,
                sleepAfterIdleMs: 24 * 60 * 60 * 1000,
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
                activityEvents: ['pointerdown', 'keydown', 'touchstart', 'focus'],
                onData: null,
                onTick: null,
                onError: null,
            },
            options || {}
        );

        if (typeof cfg.onData !== 'function') {
            throw new Error('createLivePoller requires an onData callback.');
        }

        let lastPayload = '';
        let lastChangeAt = Date.now();
        let isLoading = false;
        let isSleeping = false;
        let pollTimer = null;

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

        function clearPollTimer() {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
        }

        function enterSleepMode() {
            if (isSleeping) {
                return;
            }
            isSleeping = true;
            clearPollTimer();
            setStatus('sleeping');
        }

        function scheduleNextRefresh() {
            if (isSleeping) {
                return;
            }

            const multiplier = document.hidden ? cfg.backgroundPollMultiplier : 1;
            const delay = cfg.pollIntervalMs * multiplier;
            clearPollTimer();
            pollTimer = setTimeout(() => {
                void tick();
            }, delay);
        }

        async function tick(force) {
            if (isLoading) {
                return;
            }
            if (isSleeping && !force) {
                return;
            }

            isLoading = true;
            try {
                const response = await fetch(cfg.endpoint, { cache: 'no-store' });
                const payload = await response.text();
                const changed = payload !== lastPayload;

                if (changed) {
                    cfg.onData(payload, { lastPayload });
                    lastPayload = payload;
                    lastChangeAt = Date.now();
                }

                if (typeof cfg.onTick === 'function') {
                    cfg.onTick({
                        changed,
                        payload,
                        lastPayload,
                        lastChangeAt,
                        isSleeping,
                    });
                }

                if (Date.now() - lastChangeAt >= cfg.sleepAfterIdleMs) {
                    enterSleepMode();
                } else {
                    setStatus('live');
                }
            } catch (err) {
                setStatus('offline');
                if (typeof cfg.onError === 'function') {
                    cfg.onError(err);
                } else {
                    console.error('Live poller failed', err);
                }
            } finally {
                isLoading = false;
                scheduleNextRefresh();
            }
        }

        function wakeClient() {
            if (!isSleeping) {
                return;
            }

            isSleeping = false;
            lastChangeAt = Date.now();
            setStatus('live');
            void tick(true);
        }

        function onVisibilityChange() {
            if (document.hidden) {
                return;
            }

            if (isSleeping) {
                wakeClient();
                return;
            }

            void tick(true);
        }

        document.addEventListener('visibilitychange', onVisibilityChange);
        cfg.activityEvents.forEach((eventName) => {
            window.addEventListener(eventName, wakeClient);
        });

        void tick(true);

        return {
            wake: wakeClient,
            sleep: enterSleepMode,
            tick: () => tick(true),
            stop: function () {
                clearPollTimer();
                document.removeEventListener('visibilitychange', onVisibilityChange);
                cfg.activityEvents.forEach((eventName) => {
                    window.removeEventListener(eventName, wakeClient);
                });
            },
        };
    }

    window.createLivePoller = createLivePoller;
})();
