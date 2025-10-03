// Bootstrap 5 Success/Error feedback modal utilities
// Creates lightweight modals on demand and handles auto-close + reload behavior

(function(global){
    function createModal(id, title, message, variant){
        // Remove existing instance
        try { const old = document.getElementById(id); if (old) old.remove(); } catch(_){}

        const headerBg = (variant === 'success') ? '#9c0000' : '#dc3545';
        const icon = (variant === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
        // Success variant: centered, compact, no close buttons
        const isSuccess = variant === 'success';
        const dialogClasses = isSuccess ? 'modal-dialog modal-dialog-centered' : 'modal-dialog';
        const headerRight = isSuccess ? '' : '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>';
        const footerHtml = isSuccess ? '' : '<div class="modal-footer">\n                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>\n                    </div>';
        const bodyStyle = isSuccess ? 'style="padding: 16px 18px;"' : '';
        const modalHtml = `
        <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
            <div class="${dialogClasses}">
                <div class="modal-content" style="${isSuccess ? 'min-height:auto;' : ''}">
                    <div class="modal-header" style="background:${headerBg};color:#fff;">
                        <h5 class="modal-title" style="margin:0;"><i class="fas ${icon} me-2"></i>${title}</h5>
                        ${headerRight}
                    </div>
                    <div class="modal-body" ${bodyStyle}>
                        <p class="mb-0">${message}</p>
                    </div>
                    ${footerHtml}
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return document.getElementById(id);
    }

    function showModal({ id='feedbackModal', title='Notice', message='', variant='success', autoCloseMs=null, reloadOnClose=false }){
        const el = createModal(id, title, message, variant);
        const modal = new bootstrap.Modal(el);

        if (reloadOnClose){
            el.addEventListener('hidden.bs.modal', function(){
                try { window.location.reload(); } catch(_) { window.location.href = window.location.href; }
            }, { once: true });
        }

        modal.show();

        if (autoCloseMs && Number(autoCloseMs) > 0){
            setTimeout(() => {
                try { modal.hide(); } catch(_){ }
            }, autoCloseMs);
        }
        return modal;
    }

    global.showSuccessModal = function(title, message, options){
        const opts = Object.assign({ variant:'success', autoCloseMs: 1500, reloadOnClose: true }, options||{}, { title, message });
        return showModal(opts);
    };

})(window);



