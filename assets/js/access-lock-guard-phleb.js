/* Phlebotomist-specific access lock guard */
(function (window, document) {
    'use strict';

    const MODAL_ID = 'phlebAccessRestrictedModal';
    const MESSAGE_CLASS = 'phleb-access-message';
    const DEFAULT_ENDPOINT = window.PHLEB_ACCESS_ENDPOINT || '../../assets/php_func/access_lock_manager.php';
    const DEFAULT_MESSAGE = 'Restricted access: this donor is being processed by an admin.';

    function ensureModal() {
        if (document.getElementById(MODAL_ID)) {
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="${MODAL_ID}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title mb-0">Restricted Access</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="${MESSAGE_CLASS} mb-0">${DEFAULT_MESSAGE}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(wrapper.firstElementChild);
    }

    function showRestricted(message) {
        ensureModal();
        const modalEl = document.getElementById(MODAL_ID);
        const messageEl = modalEl.querySelector(`.${MESSAGE_CLASS}`);
        if (messageEl) {
            messageEl.textContent = message || DEFAULT_MESSAGE;
        }
        const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
        instance.show();
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

    function buildRecordFilters(record) {
        const filters = {};
        if (record && record.blood_collection_id) {
            filters.blood_collection_id = record.blood_collection_id;
        } else if (record && record.physical_exam_id) {
            filters.physical_exam_id = record.physical_exam_id;
        } else if (record && record.donor_id) {
            filters.donor_id = record.donor_id;
        }
        return filters;
    }

    function buildRecordPayload(record) {
        const filters = buildRecordFilters(record);
        if (!Object.keys(filters).length) {
            return null;
        }
        return [{
            scope: 'blood_collection',
            filters
        }];
    }

    const PhlebAccessLock = {
        activeRecord: null,

        ensureAccess(record) {
            const records = buildRecordPayload(record);
            if (!records) {
                return Promise.resolve(true);
            }
            return sendRequest({
                action: 'status',
                scopes: ['blood_collection'],
                records
            }).then((response) => {
                const state = response && response.states ? response.states.blood_collection : null;
                if (state === 2) {
                    showRestricted();
                    return false;
                }
                return true;
            }).catch((error) => {
                console.warn('Access status check failed', error);
                // Fail-open so staff aren't blocked by transient errors
                return true;
            });
        },

        claimLock(record) {
            const records = buildRecordPayload(record);
            if (!records) {
                return Promise.resolve();
            }
            return sendRequest({
                action: 'claim',
                access: 1,
                scopes: ['blood_collection'],
                records
            }).then((response) => {
                this.activeRecord = records[0];
                return response;
            });
        },

        releaseLock() {
            if (!this.activeRecord) {
                return Promise.resolve();
            }
            const payload = {
                action: 'release',
                scopes: ['blood_collection'],
                records: [this.activeRecord]
            };
            this.activeRecord = null;
            return sendRequest(payload).catch((error) => {
                console.warn('Failed to release phlebotomist lock', error);
            });
        }
    };

    window.PhlebAccessLock = PhlebAccessLock;

    window.addEventListener('beforeunload', () => {
        window.PhlebAccessLock && window.PhlebAccessLock.releaseLock();
    });
})(window, document);

