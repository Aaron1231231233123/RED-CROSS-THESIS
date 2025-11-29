(function (window, document) {
    'use strict';

    if (window.__adminFeedbackModalInitialized) {
        return;
    }
    window.__adminFeedbackModalInitialized = true;

    const modalQueue = [];
    let isShowing = false;
    let activeResolver = null;
    let modalElement = null;
    let confirmBtn = null;
    let cancelBtn = null;
    let titleEl = null;
    let messageEl = null;
    let bsInstance = null;

    const CLOSE_TITLE = 'Close Without Saving?';
    const CLOSE_MESSAGE = 'Are you sure you want to close this? All changes will not be saved.';
    const CLOSE_CONFIRM_LABEL = 'Close Anyway';
    const CLOSE_CANCEL_LABEL = 'Cancel';

    function ensureBootstrapReady() {
        return typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined';
    }

    function injectStyles() {
        if (document.getElementById('adminFeedbackModalStyles')) {
            return;
        }

        const styleTag = document.createElement('style');
        styleTag.id = 'adminFeedbackModalStyles';
        styleTag.textContent = `
            #adminFeedbackModal .modal-content {
                border: none;
                border-radius: 16px;
                box-shadow: 0 18px 60px rgba(148, 16, 34, 0.25);
            }
            #adminFeedbackModal .modal-header {
                background: radial-gradient(circle at top left, #ff6b81, #941022);
                color: #fff;
                border-bottom: none;
                border-radius: 16px 16px 0 0;
            }
            #adminFeedbackModal .modal-body {
                padding: 1.5rem;
                font-size: 1rem;
                color: #4b4b4b;
                line-height: 1.5;
            }
            #adminFeedbackModal .modal-footer {
                border-top: none;
                padding: 0 1.5rem 1.5rem;
            }
            #adminFeedbackModal .btn-confirm {
                background: #ff4d6d;
                border: none;
                box-shadow: 0 8px 24px rgba(255, 77, 109, 0.35);
            }
            #adminFeedbackModal .btn-confirm:hover {
                background: #ff3359;
            }
        `;

        document.head.appendChild(styleTag);
    }

    function buildModalSkeleton() {
        if (modalElement) {
            return;
        }

        if (!document.body) {
            document.addEventListener('DOMContentLoaded', buildModalSkeleton, { once: true });
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="adminFeedbackModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title mb-0">Notification</h5>
                            <button type="button" class="btn-close btn-close-white" data-admin-action="dismiss" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary me-2" data-admin-action="cancel">Cancel</button>
                            <button type="button" class="btn btn-danger btn-confirm" data-admin-action="confirm">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        modalElement = wrapper.firstElementChild;
        document.body.appendChild(modalElement);
        titleEl = modalElement.querySelector('.modal-title');
        messageEl = modalElement.querySelector('.modal-body p');
        confirmBtn = modalElement.querySelector('[data-admin-action="confirm"]');
        cancelBtn = modalElement.querySelector('[data-admin-action="cancel"]');

        const dismissBtn = modalElement.querySelector('[data-admin-action="dismiss"]');
        dismissBtn.addEventListener('click', () => handleUserChoice(false));
        cancelBtn.addEventListener('click', () => handleUserChoice(false));
        confirmBtn.addEventListener('click', () => handleUserChoice(true));

        modalElement.addEventListener('hidden.bs.modal', () => {
            activeResolver = null;
            isShowing = false;
            processQueue();
        });
    }

    function getModalInstance() {
        if (!bsInstance) {
            bsInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        }
        return bsInstance;
    }

    function handleUserChoice(result) {
        if (activeResolver) {
            activeResolver(result);
            activeResolver = null;
        }
        const instance = getModalInstance();
        instance.hide();
    }

    function enqueueModal(config) {
        modalQueue.push(config);
        processQueue();
    }

    function processQueue() {
        if (isShowing || !modalQueue.length) {
            return;
        }

        if (!ensureBootstrapReady()) {
            // Fallback to native alert/confirm behavior
            setTimeout(() => {
                isShowing = false;
                modalQueue.unshift(nextConfig);
                processQueue();
            }, 50);
            return;
        }

        if (!modalElement) {
            buildModalSkeleton();
        }

        const nextConfig = modalQueue.shift();
        isShowing = true;

        titleEl.textContent = nextConfig.title || (nextConfig.isConfirm ? 'Please Confirm' : 'Heads Up');
        messageEl.textContent = nextConfig.message || '';

        confirmBtn.classList.remove('btn-outline-danger', 'btn-danger', 'btn-confirm');

        if (nextConfig.isConfirm) {
            cancelBtn.classList.remove('d-none');
            cancelBtn.textContent = nextConfig.cancelText || 'Cancel';
            confirmBtn.textContent = nextConfig.confirmText || 'Yes, Proceed';
            if (nextConfig.outlineConfirm) {
                confirmBtn.classList.add('btn-outline-danger');
            } else {
                confirmBtn.classList.add('btn-danger');
            }
        } else {
            cancelBtn.classList.add('d-none');
            confirmBtn.textContent = nextConfig.confirmText || 'Got it';
            confirmBtn.classList.add('btn-confirm');
        }

        activeResolver = (result) => {
            if (typeof nextConfig.resolve === 'function') {
                nextConfig.resolve(result);
            }
        };

        injectStyles();
        
        // Use dynamic z-index calculation based on open modals
        if (modalElement) {
            // Use the modal stacking utility if available, otherwise calculate dynamically
            if (typeof applyModalStacking === 'function') {
                applyModalStacking(modalElement);
            } else {
                // Fallback: Calculate z-index based on open modals
                const openModals = document.querySelectorAll('.modal.show, .medical-history-modal.show');
                let maxZIndex = 1050;
                openModals.forEach(m => {
                    if (m === modalElement) return;
                    const z = parseInt(window.getComputedStyle(m).zIndex) || parseInt(m.style.zIndex) || 0;
                    if (z > maxZIndex) maxZIndex = z;
                });
                const newZIndex = maxZIndex + 10;
                modalElement.style.zIndex = newZIndex.toString();
                modalElement.style.position = 'fixed';
                const dialog = modalElement.querySelector('.modal-dialog');
                if (dialog) dialog.style.zIndex = (newZIndex + 1).toString();
                const content = modalElement.querySelector('.modal-content');
                if (content) content.style.zIndex = (newZIndex + 2).toString();
                
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 0) {
                        backdrops[backdrops.length - 1].style.zIndex = (newZIndex - 1).toString();
                    }
                }, 10);
            }
        }
        
        const instance = getModalInstance();
        instance.show();

        if (nextConfig.isConfirm) {
            setTimeout(() => {
                cancelBtn && cancelBtn.focus();
            }, 150);
        }
    }

    function showAlert(message, options) {
        const normalized = typeof options === 'object' && options !== null ? options : {};
        return new Promise((resolve) => {
            enqueueModal({
                isConfirm: false,
                message,
                title: normalized.title,
                confirmText: normalized.confirmText,
                resolve: () => {
                    if (typeof normalized.onClose === 'function') {
                        normalized.onClose();
                    }
                    resolve();
                }
            });
        });
    }

    function showConfirm(message, options) {
        const normalized = typeof options === 'object' && options !== null ? options : {};
        return new Promise((resolve) => {
            enqueueModal({
                isConfirm: true,
                message,
                title: normalized.title,
                confirmText: normalized.confirmText,
                cancelText: normalized.cancelText,
                outlineConfirm: normalized.outlineConfirm,
                resolve: (result) => {
                    if (!result && typeof normalized.onCancel === 'function') {
                        normalized.onCancel();
                    }
                    if (result && typeof normalized.onConfirm === 'function') {
                        normalized.onConfirm();
                    }
                    resolve(result);
                }
            });
        });
    }

    function adminConfirm(message, onConfirm, options) {
        const normalizedOptions = typeof options === 'object' && options !== null ? options : {};
        if (typeof onConfirm === 'function') {
            normalizedOptions.onConfirm = onConfirm;
        }
        return showConfirm(message, normalizedOptions);
    }

    function adminAlert(message, options) {
        return showAlert(message, options);
    }

    window.adminModal = {
        alert: adminAlert,
        confirm: adminConfirm
    };

    window.adminAlert = adminAlert;
    window.adminConfirm = adminConfirm;

    window.alert = function (message, options) {
        return adminAlert(message, options);
    };

    function requestCloseWithoutSaving(options = {}) {
        const message = options.message || CLOSE_MESSAGE;
        const confirmText = options.confirmText || CLOSE_CONFIRM_LABEL;
        const cancelText = options.cancelText || CLOSE_CANCEL_LABEL;
        const title = options.title || CLOSE_TITLE;

        return showConfirm(message, {
            title,
            confirmText,
            cancelText,
            outlineConfirm: false
        }).then(result => !!result);
    }

    window.requestCloseWithoutSavingConfirmation = requestCloseWithoutSaving;
    window.adminUnsavedCloseConfirm = requestCloseWithoutSaving;
})(window, document);

