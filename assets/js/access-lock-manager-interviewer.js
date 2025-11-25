(function (window, document) {
    'use strict';

    const DEFAULT_GUARDS = ['.view-donor-btn', '.view-btn', '.edit-btn', '.collect-btn', '.view-donor'];
    const DEFAULT_ENDPOINT = window.ACCESS_LOCK_ENDPOINT_INTERVIEWER || '../../assets/php_func/access_lock_manager-interviewer.php';

    function ensureModal() {
        if (document.getElementById('accessLockGuardModalInterviewer')) {
            return;
        }
        const modal = document.createElement('div');
        modal.id = 'accessLockGuardModalInterviewer';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="access-lock-backdrop"></div>
            <div class="access-lock-dialog">
                <div class="access-lock-header">
                    <span class="access-lock-title">Access Locked</span>
                    <button type="button" class="access-lock-close" aria-label="Close">&times;</button>
                </div>
                <div class="access-lock-body">
                    <p id="accessLockGuardMessageInterviewer">This donor data is being processed by an admin account.</p>
                </div>
                <div class="access-lock-footer">
                    <button type="button" class="access-lock-dismiss btn btn-danger btn-sm">Okay</button>
                </div>
            </div>`;
        document.body.appendChild(modal);

        const closeModal = () => hideModal();
        modal.querySelector('.access-lock-close').addEventListener('click', closeModal);
        modal.querySelector('.access-lock-dismiss').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        if (!document.getElementById('accessLockGuardStyleInterviewer')) {
            const style = document.createElement('style');
            style.id = 'accessLockGuardStyleInterviewer';
            style.textContent = `
                #accessLockGuardModalInterviewer {
                    position: fixed;
                    inset: 0;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 11000;
                }
                #accessLockGuardModalInterviewer.show { display: flex; }
                #accessLockGuardModalInterviewer .access-lock-backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.55);
                }
                #accessLockGuardModalInterviewer .access-lock-dialog {
                    position: relative;
                    z-index: 2;
                    width: min(420px, 90vw);
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
                    overflow: hidden;
                    animation: accessLockFade 0.25s ease-out;
                }
                #accessLockGuardModalInterviewer .access-lock-header {
                    padding: 14px 18px;
                    background: linear-gradient(135deg, #b22222, #8b0000);
                    color: #fff;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                #accessLockGuardModalInterviewer .access-lock-title {
                    font-weight: 600;
                }
                #accessLockGuardModalInterviewer .access-lock-close {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 1.3rem;
                    line-height: 1;
                    cursor: pointer;
                }
                #accessLockGuardModalInterviewer .access-lock-body {
                    padding: 18px;
                    color: #333;
                }
                #accessLockGuardModalInterviewer .access-lock-footer {
                    padding: 12px 18px 20px;
                    display: flex;
                    justify-content: flex-end;
                }
                .access-lock-disabled-interviewer {
                    pointer-events: none !important;
                    opacity: 0.6 !important;
                }
                @keyframes accessLockFade {
                    from { transform: translateY(-10px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    function showModal(message) {
        ensureModal();
        const modal = document.getElementById('accessLockGuardModalInterviewer');
        const text = modal.querySelector('#accessLockGuardMessageInterviewer');
        text.textContent = message || 'This donor data is being processed by an admin account.';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function hideModal() {
        const modal = document.getElementById('accessLockGuardModalInterviewer');
        if (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    const AccessLockManagerInterviewer = {
        initialized: false,
        scopes: [],
        guardSelectors: [],
        endpoint: DEFAULT_ENDPOINT,
        role: 'staff',
        accessValue: 1,
        active: false,
        pollTimer: null,
        pollMs: 25000,
        blocked: false,
        currentLock: null,
        releaseSent: false,
        autoClaim: true,
        currentContext: null,

        init(config = {}) {
            if (this.initialized) {
                return;
            }
            this.scopes = Array.isArray(config.scopes) ? config.scopes : [];
            this.guardSelectors = Array.isArray(config.guardSelectors) && config.guardSelectors.length
                ? config.guardSelectors
                : DEFAULT_GUARDS;
            this.endpoint = config.endpoint || this.endpoint;
            this.role = config.role === 'admin' ? 'admin' : 'staff';
            this.accessValue = this.role === 'admin' ? 2 : 1;
            this.autoClaim = config.autoClaim !== false;
            if (typeof config.pollMs === 'number' && config.pollMs >= 5000) {
                this.pollMs = config.pollMs;
            }

            if (!this.scopes.length) {
                console.warn('[AccessLockManagerInterviewer] No scopes supplied. Initialization skipped.');
                return;
            }

            this.attachGuards();
            if (this.autoClaim) {
                this.activate();
            }
            this.startPolling();
            const releaseHandler = () => this.releaseOnce();
            window.addEventListener('beforeunload', releaseHandler);
            window.addEventListener('pagehide', releaseHandler);
            this.initialized = true;
        },

        activate(context, accessOverride) {
            if (typeof accessOverride === 'number') {
                this.accessValue = accessOverride;
            }
            if (context) {
                this.currentContext = Object.assign({}, context);
            }
            if (this.active) {
                return;
            }
            this.claimLock();
        },

        deactivate() {
            this.currentContext = null;
            this.releaseLock();
        },

        claimLock() {
            this.sendRequest({ action: 'claim', access: this.accessValue })
                .then(() => {
                    this.active = true;
                    this.releaseSent = false;
                })
                .catch((err) => {
                    console.error('[AccessLockManagerInterviewer] Claim failed:', err);
                    this.active = false;
                })
                .finally(() => this.fetchStatus());
        },

        releaseLock() {
            if (!this.active) {
                return;
            }
            this.sendRequest({ action: 'release' })
                .catch((err) => {
                    console.error('[AccessLockManagerInterviewer] Release failed:', err);
                })
                .finally(() => {
                    this.active = false;
                    this.releaseSent = true;
                });
        },

        releaseOnce() {
            if (this.releaseSent || !this.active) {
                return;
            }
            this.releaseSent = true;
            const payload = JSON.stringify({ action: 'release', scopes: this.scopes });
            if (navigator.sendBeacon) {
                try {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon(this.endpoint, blob);
                } catch (err) {
                    console.error('[AccessLockManagerInterviewer] Beacon release failed:', err);
                }
            } else {
                this.sendRequest({ action: 'release' }).catch(() => {});
            }
        },

        startPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
            this.pollTimer = window.setInterval(() => this.fetchStatus(), this.pollMs);
        },

        fetchStatus() {
            this.sendRequest({ action: 'status' })
                .then((data) => {
                    if (data && data.states) {
                        this.evaluateState(data.states);
                    }
                })
                .catch((err) => {
                    console.error('[AccessLockManagerInterviewer] Status check failed:', err);
                });
        },

        evaluateState(states) {
            let blocked = false;
            let offendingValue = null;
            this.scopes.forEach((scopeKey) => {
                const key = scopeKey.toLowerCase();
                const rawValue = states && Object.prototype.hasOwnProperty.call(states, key) ? states[key] : null;
                const value = (typeof rawValue === 'number')
                    ? rawValue
                    : (rawValue !== null && rawValue !== undefined && rawValue !== '')
                        ? parseInt(rawValue, 10)
                        : 0;
                if (Number.isFinite(value) && value !== 0 && value !== this.accessValue) {
                    blocked = true;
                    offendingValue = value;
                }
            });
            this.currentLock = offendingValue;
            this.toggleBlocked(blocked);
        },

        toggleBlocked(shouldBlock) {
            if (this.blocked === shouldBlock) {
                return;
            }
            this.blocked = shouldBlock;
            this.updateGuardStyles();
            if (shouldBlock) {
                this.notifyBlocked();
            }
        },

        notifyBlocked(customMessage) {
            showModal(customMessage || 'This donor data is being processed by an admin account.');
        },

        buildRecordsPayload(explicitRecords) {
            if (Array.isArray(explicitRecords) && explicitRecords.length) {
                return explicitRecords;
            }
            if (!this.currentContext || !this.scopes.length) {
                return null;
            }
            return this.scopes.map((scope) => Object.assign({ scope }, this.currentContext));
        },

        sendRequest(payload = {}) {
            const body = Object.assign({}, payload, { scopes: this.scopes });
            const recordsPayload = this.buildRecordsPayload(payload.records);
            if (recordsPayload) {
                body.records = recordsPayload;
            }
            return fetch(this.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(async (response) => {
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || 'Request failed');
                }
                return response.json();
            });
        },

        attachGuards() {
            document.addEventListener('click', (event) => {
                if (!this.blocked) {
                    return;
                }
                if (this.matchesGuard(event.target)) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.notifyBlocked();
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                if (!this.blocked) {
                    return;
                }
                if ((event.key === 'Enter' || event.key === ' ') && this.matchesGuard(event.target)) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.notifyBlocked();
                }
            }, true);
        },

        matchesGuard(target) {
            if (!target) {
                return false;
            }
            return this.guardSelectors.some((selector) => {
                try {
                    return target.closest(selector);
                } catch (err) {
                    return false;
                }
            });
        },

        updateGuardStyles() {
            this.guardSelectors.forEach((selector) => {
                document.querySelectorAll(selector).forEach((element) => {
                    if (this.blocked) {
                        element.classList.add('access-lock-disabled-interviewer');
                        element.setAttribute('aria-disabled', 'true');
                    } else {
                        element.classList.remove('access-lock-disabled-interviewer');
                        element.removeAttribute('aria-disabled');
                    }
                });
            });
        }
    };

    const AccessLockAPIInterviewer = {
        call({ action, scopes, access, records, endpoint = DEFAULT_ENDPOINT }) {
            if (!Array.isArray(scopes) || !scopes.length) {
                return Promise.reject(new Error('Scopes are required for access lock API'));
            }
            const body = {
                action: action || 'status',
                scopes
            };
            if (typeof access === 'number') {
                body.access = access;
            }
            if (Array.isArray(records) && records.length) {
                body.records = records;
            }
            return fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(async (response) => {
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || 'Request failed');
                }
                return response.json();
            });
        },
        claim({ scopes, access = 1, records, endpoint }) {
            return this.call({ action: 'claim', scopes, access, records, endpoint });
        },
        release({ scopes, records, endpoint }) {
            return this.call({ action: 'release', scopes, records, endpoint });
        },
        status({ scopes, records, endpoint }) {
            return this.call({ action: 'status', scopes, records, endpoint });
        }
    };

    window.AccessLockManagerInterviewer = AccessLockManagerInterviewer;
    window.AccessLockAPIInterviewer = AccessLockAPIInterviewer;
})(window, document);

