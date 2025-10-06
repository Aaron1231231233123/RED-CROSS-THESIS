// One-time per-request (per ID) per-browser notification for Handed_over status
// Usage:
//   initHandedOverNotifier({
//     userId: '123',
//     handedOverIds: [1,2,3],
//     modalSelector: '#printSuccessModal',
//     viewButtonSelector: '#printSuccessModal .btn-primary',
//     buildViewUrl: (id) => `../../src/views/forms/print-blood-request.php?request_id=${id}`
//   });

export function initHandedOverNotifier(options) {
    try {
        const {
            userId,
            handedOverIds,
            modalSelector,
            viewButtonSelector,
            buildViewUrl
        } = options || {};

        if (!userId || !Array.isArray(handedOverIds) || handedOverIds.length === 0) return;
        const keyPrefix = `handedOverNotified_${userId}_`;
        const targetId = handedOverIds.find(id => !localStorage.getItem(keyPrefix + String(id)));
        if (!targetId) return;

        const modalEl = document.querySelector(modalSelector);
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const viewBtn = document.querySelector(viewButtonSelector);
        if (viewBtn) {
            viewBtn.onclick = function () {
                // Prefer opening the in-page modal rather than the receipt directly
                const actionBtn = document.querySelector(`.view-btn[data-request-id="${targetId}"]`) ||
                                   document.querySelector(`.handover-btn[data-request-id="${targetId}"]`);
                if (actionBtn) actionBtn.click();
                modal.hide();
            };
        }

        localStorage.setItem(keyPrefix + String(targetId), '1');
    } catch (e) {
        console.warn('HandedOverNotifier error:', e);
    }
}

// Auto-init if a global config is present
if (window.HandedOverNotifyConfig) {
    document.addEventListener('DOMContentLoaded', function() {
        initHandedOverNotifier(window.HandedOverNotifyConfig);
    });
}

// Helpers to reset notifications
export function resetHandedOverNotification(userId, requestId) {
    const key = `handedOverNotified_${userId}_${requestId}`;
    localStorage.removeItem(key);
}

export function resetAllHandedOverNotifications(userId) {
    const prefix = `handedOverNotified_${userId}_`;
    const toRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (k && k.startsWith(prefix)) toRemove.push(k);
    }
    toRemove.forEach(k => localStorage.removeItem(k));
}

// Expose simple globals for console-based manual reset if needed
window.HandedOverNotifyReset = {
    one: resetHandedOverNotification,
    all: resetAllHandedOverNotifications
};


