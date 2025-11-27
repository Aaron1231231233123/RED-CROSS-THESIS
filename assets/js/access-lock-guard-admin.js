(function (window, document) {
    'use strict';

    if (window.AccessLockGuardAdmin) {
        return;
    }

    const NOTICE_MODAL_ID = 'accessLockNoticeModalAdmin';
    const DEFAULT_ENDPOINT = window.ACCESS_LOCK_ENDPOINT_ADMIN || '../../assets/php_func/access_lock_manager_admin.php';

    function ensureModal() {
        if (document.getElementById(NOTICE_MODAL_ID)) {
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="${NOTICE_MODAL_ID}" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title mb-0">Access Restricted</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0 access-lock-notice-message-admin">
                                This donor is currently being processed. Please try again later.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(wrapper.firstElementChild);
    }

    function showNotice(message) {
        ensureModal();
        const modalEl = document.getElementById(NOTICE_MODAL_ID);
        if (!modalEl) {
            console.warn('AccessLockGuardAdmin: notice modal missing');
            return;
        }
        modalEl.setAttribute('data-bs-backdrop', 'false');
        modalEl.setAttribute('data-bs-keyboard', 'true');
        const placeholder = modalEl.querySelector('.access-lock-notice-message-admin');
        if (placeholder) {
            placeholder.textContent = message || 'This donor is currently being processed. Please try again later.';
        }
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: false, keyboard: true, focus: true });
        modalInstance.show();
    }

    function sendRequest(payload) {
        return fetch(DEFAULT_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(async (response) => {
            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || 'Access lock request failed');
            }
            return response.json();
        });
    }

    function ensureAccess(options = {}) {
        const scopes = Array.isArray(options.scope) ? options.scope.filter(Boolean) : [options.scope].filter(Boolean);
        const donorId = options.donorId || options.donor_id;
        const lockValue = typeof options.lockValue === 'number'
            ? options.lockValue
            : 2;
        const defaultMessage = lockValue === 2
            ? 'This donor is currently being processed by a staff account. Please try again later.'
            : 'This donor is currently being processed by another admin. Please try again later.';
        const scopeMessages = options.messages || {};

        if (!scopes.length || !donorId) {
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

        sendRequest({
            action: 'status',
            scopes,
            records
        }).then((response) => {
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
                showNotice(noticeMessage);
                if (typeof options.onBlocked === 'function') {
                    options.onBlocked(blockingScope, response && response.states ? response.states : {});
                }
            } else if (typeof options.onAllowed === 'function') {
                options.onAllowed();
            }
        }).catch(() => {
            if (typeof options.onAllowed === 'function') {
                options.onAllowed();
            }
        });
    }

    window.AccessLockGuardAdmin = {
        ensureAccess,
        showNotice
    };
})(window, document);



