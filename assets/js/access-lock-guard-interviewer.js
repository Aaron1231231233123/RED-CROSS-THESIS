(function (window, document) {
    'use strict';

    if (window.AccessLockGuardInterviewer) {
        return;
    }

    function showBlocked(message) {
        if (window.AccessLockManagerInterviewer && typeof window.AccessLockManagerInterviewer.notifyBlocked === 'function') {
            window.AccessLockManagerInterviewer.notifyBlocked(message);
            return;
        }
        alert(message || 'This donor data is currently locked.');
    }

    function ensureAccess(options = {}) {
        const scopes = Array.isArray(options.scope) ? options.scope.filter(Boolean) : [options.scope].filter(Boolean);
        const donorId = options.donorId || options.donor_id;
        const lockValue = typeof options.lockValue === 'number'
            ? options.lockValue
            : (window.ACCESS_LOCK_ROLE_VALUE || 1);
        const defaultMessage = lockValue === 2
            ? 'This donor data is being processed by a staff account.'
            : 'This donor data is being processed by an admin account.';
        const scopeMessages = options.messages || {};

        if (!scopes.length || !donorId || !window.AccessLockAPIInterviewer) {
            if (typeof options.onAllowed === 'function') {
                options.onAllowed();
            }
            return;
        }

        const records = scopes.map((scope) => {
            const record = { scope, donor_id: donorId };
            if (options.filters && options.filters[scope]) {
                Object.assign(record, options.filters[scope]);
            } else if (options.filters && typeof options.filters === 'object') {
                Object.assign(record, options.filters);
            }
            return record;
        });

        window.AccessLockAPIInterviewer.status({ scopes, records })
            .then((response) => {
                let blocked = false;
                let blockingScope = null;
                scopes.forEach((scope) => {
                    const rawState = response && response.states ? response.states[scope] : null;
                    const numericState = (typeof rawState === 'number')
                        ? rawState
                        : (rawState !== null && rawState !== undefined && rawState !== '')
                            ? parseInt(rawState, 10)
                            : null;
                    if (Number.isFinite(numericState) && numericState !== 0 && numericState !== lockValue) {
                        blocked = true;
                        if (!blockingScope) {
                            blockingScope = scope;
                        }
                    }
                });

                if (blocked) {
                    const noticeMessage = scopeMessages[blockingScope] || options.message || defaultMessage;
                    showBlocked(noticeMessage);
                    if (typeof options.onBlocked === 'function') {
                        options.onBlocked(blockingScope, response && response.states ? response.states : {});
                    }
                } else if (typeof options.onAllowed === 'function') {
                    options.onAllowed();
                }
            })
            .catch(() => {
                if (typeof options.onAllowed === 'function') {
                    options.onAllowed();
                }
            });
    }

    window.AccessLockGuardInterviewer = {
        ensureAccess
    };
})(window, document);

