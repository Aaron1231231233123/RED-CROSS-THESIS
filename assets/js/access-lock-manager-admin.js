(function (window, document) {
    'use strict';

    const DEFAULT_GUARDS = ['.view-donor', '.edit-donor', 'tr.donor-row'];
    const DEFAULT_ENDPOINT = window.ACCESS_LOCK_ENDPOINT_ADMIN || '../../assets/php_func/access_lock_manager_admin.php';

    function createModal() {
        if (document.getElementById('accessLockGuardModalAdmin')) {
            return;
        }
        const modal = document.createElement('div');
        modal.id = 'accessLockGuardModalAdmin';
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
                    <p id="accessLockGuardMessageAdmin">This donor is currently being processed by a staff account.</p>
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

        if (!document.getElementById('accessLockGuardStyleAdmin')) {
            const style = document.createElement('style');
            style.id = 'accessLockGuardStyleAdmin';
            style.textContent = `
                #accessLockGuardModalAdmin {
                    position: fixed;
                    inset: 0;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 11000;
                }
                #accessLockGuardModalAdmin.show { display: flex; }
                #accessLockGuardModalAdmin .access-lock-backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.55);
                }
                #accessLockGuardModalAdmin .access-lock-dialog {
                    position: relative;
                    z-index: 2;
                    width: min(420px, 90vw);
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
                    overflow: hidden;
                    animation: accessLockFade 0.25s ease-out;
                }
                #accessLockGuardModalAdmin .access-lock-header {
                    padding: 14px 18px;
                    background: linear-gradient(135deg, #b22222, #8b0000);
                    color: #fff;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                #accessLockGuardModalAdmin .access-lock-title {
                    font-weight: 600;
                }
                #accessLockGuardModalAdmin .access-lock-close {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 1.3rem;
                    line-height: 1;
                    cursor: pointer;
                }
                #accessLockGuardModalAdmin .access-lock-body {
                    padding: 18px;
                    color: #333;
                }
                #accessLockGuardModalAdmin .access-lock-footer {
                    padding: 12px 18px 20px;
                    display: flex;
                    justify-content: flex-end;
                }
                .access-lock-disabled-admin {
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
        createModal();
        const modal = document.getElementById('accessLockGuardModalAdmin');
        const text = modal.querySelector('#accessLockGuardMessageAdmin');
        text.textContent = message || 'This donor is currently being processed by a staff account.';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function hideModal() {
        const modal = document.getElementById('accessLockGuardModalAdmin');
        if (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    const AccessLockManagerAdmin = {
        initialized: false,
        scopes: [],
        guardSelectors: [],
        endpoint: DEFAULT_ENDPOINT,
        accessValue: 2,
        active: false,
        pollTimer: null,
        pollMs: 25000,
        blocked: false,
        currentLock: null,
        releaseSent: false,
        autoClaim: false,
        currentContext: null,

        init(config = {}) {
            if (this.initialized) {
                console.log('[AccessLockManagerAdmin] Already initialized, skipping');
                return;
            }
            this.scopes = Array.isArray(config.scopes) ? config.scopes : [];
            this.guardSelectors = Array.isArray(config.guardSelectors) && config.guardSelectors.length
                ? config.guardSelectors
                : DEFAULT_GUARDS;
            this.endpoint = config.endpoint || this.endpoint;
            this.autoClaim = config.autoClaim === true;
            if (typeof config.pollMs === 'number' && config.pollMs >= 5000) {
                this.pollMs = config.pollMs;
            }

            if (!this.scopes.length) {
                console.warn('[AccessLockManagerAdmin] No scopes supplied. Initialization skipped.');
                return;
            }

            console.log('[AccessLockManagerAdmin] Initializing with config:', {
                scopes: this.scopes,
                endpoint: this.endpoint,
                guardSelectors: this.guardSelectors,
                autoClaim: this.autoClaim
            });

            this.attachGuards();
            if (this.autoClaim) {
                this.activate();
            }
            this.startPolling();
            const releaseHandler = () => this.releaseOnce();
            window.addEventListener('beforeunload', releaseHandler);
            window.addEventListener('pagehide', releaseHandler);
            this.initialized = true;
            console.log('[AccessLockManagerAdmin] Initialization complete');
        },

        activate(context) {
            if (!this.initialized) {
                console.warn('[AccessLockManagerAdmin] Cannot activate: manager not initialized. Call init() first.');
                return;
            }
            if (!context || !context.donor_id) {
                console.warn('[AccessLockManagerAdmin] Activate called without valid context (donor_id required):', context);
                return;
            }
            // If already active for a different donor, deactivate first
            if (this.active && this.currentContext && this.currentContext.donor_id !== context.donor_id) {
                console.log('[AccessLockManagerAdmin] Switching donors, deactivating previous lock');
                this.releaseLock();
            }
            this.currentContext = Object.assign({}, context);
            console.log('[AccessLockManagerAdmin] Activating with context:', this.currentContext);
            if (this.active && this.currentContext.donor_id === context.donor_id) {
                console.log('[AccessLockManagerAdmin] Already active for this donor, skipping claim');
                return;
            }
            this.claimLock();
        },

        deactivate() {
            if (!this.active) {
                this.currentContext = null;
                return;
            }
            const contextBackup = this.currentContext ? Object.assign({}, this.currentContext) : null;
            if (contextBackup) {
                this.currentContext = contextBackup;
            }
            this.releaseLock();
            this.currentContext = null;
        },

        claimLock() {
            const recordsPayload = this.buildRecordsPayload();
            console.log('[AccessLockManagerAdmin] Claiming lock with records:', recordsPayload);
            if (!recordsPayload || recordsPayload.length === 0) {
                console.error('[AccessLockManagerAdmin] Cannot claim: no records payload built. Current context:', this.currentContext);
                return;
            }
            this.sendRequest({ action: 'claim', access: this.accessValue, records: recordsPayload })
                .then((response) => {
                    console.log('[AccessLockManagerAdmin] Claim successful:', response);
                    this.active = true;
                    this.releaseSent = false;
                })
                .catch((err) => {
                    console.error('[AccessLockManagerAdmin] Claim failed:', err);
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
                    console.error('[AccessLockManagerAdmin] Release failed:', err);
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
            const payload = JSON.stringify({ action: 'release', scopes: this.scopes, records: this.buildRecordsPayload() || [] });
            if (navigator.sendBeacon) {
                try {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon(this.endpoint, blob);
                } catch (err) {
                    console.error('[AccessLockManagerAdmin] Beacon release failed:', err);
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
                    console.error('[AccessLockManagerAdmin] Status check failed:', err);
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

        notifyBlocked() {
            showModal('This donor is currently being processed by a staff account.');
        },

        buildRecordsPayload(explicitRecords) {
            if (Array.isArray(explicitRecords) && explicitRecords.length) {
                return explicitRecords;
            }
            if (!this.currentContext || !this.scopes.length) {
                console.warn('[AccessLockManagerAdmin] Cannot build records payload:', {
                    hasContext: !!this.currentContext,
                    context: this.currentContext,
                    scopesCount: this.scopes.length
                });
                return null;
            }
            const records = this.scopes.map((scope) => {
                const record = { scope: scope };
                // Copy all properties from currentContext (including donor_id)
                Object.assign(record, this.currentContext);
                return record;
            });
            console.log('[AccessLockManagerAdmin] Built records payload:', records);
            return records;
        },

        sendRequest(payload = {}) {
            const body = Object.assign({}, payload, { scopes: this.scopes });
            // Use explicit records from payload if provided, otherwise build from currentContext
            const recordsPayload = payload.records || this.buildRecordsPayload();
            if (recordsPayload && recordsPayload.length > 0) {
                body.records = recordsPayload;
            }
            console.log('[AccessLockManagerAdmin] Sending request:', {
                endpoint: this.endpoint,
                action: body.action,
                scopes: body.scopes,
                records: body.records,
                access: body.access
            });
            return fetch(this.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(async (response) => {
                const responseText = await response.text();
                if (!response.ok) {
                    console.error('[AccessLockManagerAdmin] Request failed:', {
                        status: response.status,
                        statusText: response.statusText,
                        body: responseText
                    });
                    throw new Error(responseText || 'Request failed');
                }
                try {
                    const json = JSON.parse(responseText);
                    console.log('[AccessLockManagerAdmin] Request successful:', json);
                    return json;
                } catch (e) {
                    console.error('[AccessLockManagerAdmin] Failed to parse response:', responseText);
                    throw new Error('Invalid JSON response');
                }
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
                        element.classList.add('access-lock-disabled-admin');
                        element.setAttribute('aria-disabled', 'true');
                    } else {
                        element.classList.remove('access-lock-disabled-admin');
                        element.removeAttribute('aria-disabled');
                    }
                });
            });
        }
    };

    window.AccessLockManagerAdmin = AccessLockManagerAdmin;
})(window, document);



