(function (global) {
    const ctx = global.BUFFER_BLOOD_CONTEXT || null;
    if (!ctx) {
        global.BufferBloodToolkit = null;
        return;
    }

    const styleId = 'buffer-blood-style';
    if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .buffer-toast {
                position: fixed;
                top: 70px;
                right: 20px;
                background: #fff8e1;
                color: #7a4a00;
                border: 1px solid #f7d774;
                border-radius: 8px;
                padding: 12px 16px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 9999;
                transform: translateX(120%);
                transition: transform 0.3s ease, opacity 0.3s ease;
                opacity: 0;
                max-width: 320px;
            }
            .buffer-toast.show {
                transform: translateX(0);
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    }

    const idSet = new Set((ctx.unit_ids || []).map(String));
    const serialSet = new Set((ctx.unit_serials || []).map(String));

    function isBufferUnit(unitId, serial) {
        if (!unitId && !serial) return false;
        if (unitId && idSet.has(String(unitId))) return true;
        if (serial && serialSet.has(String(serial))) return true;
        return false;
    }

    function showToast(message, variant = 'warning') {
        const toast = document.createElement('div');
        toast.className = `buffer-toast buffer-toast-${variant}`;
        toast.innerHTML = `<i class="fas fa-shield-alt"></i><span>${message}</span>`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    function wireDrawerToggle() {
        const toggle = document.querySelector('[data-buffer-toggle]');
        const drawer = document.querySelector('[data-buffer-drawer]');
        if (!toggle || !drawer) {
            return;
        }

        toggle.addEventListener('click', () => {
            const isOpen = drawer.classList.toggle('open');
            toggle.innerHTML = isOpen
                ? '<i class="fas fa-chevron-up me-1"></i>Hide Buffer List'
                : '<i class="fas fa-list me-1"></i>View Buffer List';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wireDrawerToggle();
    });

    global.BufferBloodToolkit = {
        context: ctx,
        isBufferUnit,
        showToast
    };
})(window);


