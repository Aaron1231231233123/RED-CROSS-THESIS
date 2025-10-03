// Dedicated Error feedback modal utility (separate from success modal)
(function(global){
    function createModal(id, title, message){
        try { const old = document.getElementById(id); if (old) old.remove(); } catch(_){ }
        const modalHtml = `
        <div class="modal fade" id="${id}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background:#dc3545;color:#fff;">
                        <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return document.getElementById(id);
    }

    global.showErrorModal = function(title, message, options){
        const id = (options && options.id) || 'errorFeedbackModal';
        const el = createModal(id, title, message);
        const modal = new bootstrap.Modal(el);
        if (options && options.autoCloseMs){
            setTimeout(() => { try { modal.hide(); } catch(_){ } }, options.autoCloseMs);
        }
        if (options && options.reloadOnClose){
            el.addEventListener('hidden.bs.modal', function(){
                try { window.location.reload(); } catch(_){ window.location.href = window.location.href; }
            }, { once: true });
        }
        modal.show();
        return modal;
    };
})(window);


