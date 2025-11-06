/**
 * Professional Filter Loading Modal
 * Reusable modal component for filter operations across dashboards
 * Features:
 * - Professional styling matching Red Cross theme (#b22222, #8b0000)
 * - Real-time "..." animation without delays
 * - Automatic close when data is loaded
 * - Backdrop support
 */

(function() {
    'use strict';

    // Create modal HTML structure
    const modalHTML = `
        <div id="filterLoadingModal" class="filter-loading-modal" style="display: none;">
            <div class="filter-loading-backdrop"></div>
            <div class="filter-loading-content">
                <div class="filter-loading-header">
                    <span class="filter-loading-title">Processing Request</span>
                </div>
                <div class="filter-loading-body">
                    <div class="filter-loading-text">
                        <span class="filter-loading-message">This may take a while</span>
                        <span class="filter-loading-dots">
                            <span class="dot dot-1">.</span>
                            <span class="dot dot-2">.</span>
                            <span class="dot dot-3">.</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    `;

    // CSS styles content
    const stylesContent = `
        .filter-loading-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .filter-loading-modal.show {
            opacity: 1;
        }

        .filter-loading-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
        }

        .filter-loading-content {
            position: relative;
            z-index: 10001;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            overflow: hidden;
        }

        .filter-loading-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }

        .filter-loading-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
        }

        .filter-loading-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            padding: 35px 40px;
            text-align: center;
        }

        .filter-loading-text {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
            gap: 4px;
        }

        .filter-loading-message {
            color: #333;
        }

        .filter-loading-dots {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            color: #b22222;
            font-weight: 700;
            margin-left: 2px;
        }

        .filter-loading-dots .dot {
            display: inline-block;
            animation: dotPulse 1.4s ease-in-out infinite;
            opacity: 0.3;
        }

        .filter-loading-dots .dot-1 {
            animation-delay: 0s;
        }

        .filter-loading-dots .dot-2 {
            animation-delay: 0.2s;
        }

        .filter-loading-dots .dot-3 {
            animation-delay: 0.4s;
        }

        @keyframes dotPulse {
            0%, 100% {
                opacity: 0.3;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }


        /* Responsive design */
        @media (max-width: 768px) {
            .filter-loading-content {
                min-width: 320px;
            }

            .filter-loading-header {
                padding: 18px 25px;
            }

            .filter-loading-title {
                font-size: 1.15rem;
            }

            .filter-loading-body {
                padding: 30px 30px;
            }

            .filter-loading-text {
                font-size: 1rem;
            }
        }
    `;

    // Modal control object
    const FilterLoadingModal = {
        modal: null,
        isVisible: false,
        initialized: false,
        styleSheet: null,

        init: function() {
            if (this.initialized) return true;
            
            // Inject styles (only once)
            if (!document.getElementById('filter-loading-modal-styles')) {
                this.styleSheet = document.createElement('style');
                this.styleSheet.id = 'filter-loading-modal-styles';
                this.styleSheet.textContent = stylesContent;
                if (document.head) {
                    document.head.appendChild(this.styleSheet);
                }
            }

            // Inject modal HTML - ensure body exists
            if (!document.getElementById('filterLoadingModal')) {
                if (document.body) {
                    document.body.insertAdjacentHTML('beforeend', modalHTML);
                } else {
                    // If body doesn't exist yet, wait for it
                    console.warn('FilterLoadingModal: document.body not ready, will retry');
                    return false;
                }
            }

            // Get modal element
            this.modal = document.getElementById('filterLoadingModal');
            if (!this.modal) {
                console.error('Filter loading modal not found in DOM after insertion');
                return false;
            }

            this.initialized = true;
            return true;
        },

        show: function() {
            // Ensure initialization
            if (!this.initialized) {
                if (!this.init()) {
                    // If init failed, try again after a short delay
                    setTimeout(() => {
                        if (this.init()) {
                            this.show();
                        }
                    }, 100);
                    return;
                }
            }

            if (!this.modal) {
                this.modal = document.getElementById('filterLoadingModal');
            }

            if (!this.modal) {
                console.error('FilterLoadingModal: Cannot show - modal element not found');
                return;
            }

            this.modal.style.display = 'flex';
            // Force reflow to ensure display change is applied
            this.modal.offsetHeight;
            this.modal.classList.add('show');
            this.isVisible = true;
        },

        hide: function() {
            if (!this.modal) {
                this.modal = document.getElementById('filterLoadingModal');
            }

            if (!this.modal) return;

            this.modal.classList.remove('show');
            // Wait for transition to complete before hiding
            setTimeout(() => {
                if (this.modal) {
                    this.modal.style.display = 'none';
                }
                this.isVisible = false;
            }, 300);
        },

        isShowing: function() {
            return this.isVisible;
        }
    };

    // Initialize on DOM ready
    function initializeModal() {
        if (document.body) {
            FilterLoadingModal.init();
        } else {
            // If body doesn't exist, wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeModal);
            } else {
                // If DOMContentLoaded already fired, wait a bit for body
                setTimeout(initializeModal, 50);
            }
        }
    }

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeModal);
    } else {
        // DOM might already be ready, but body might not exist yet
        initializeModal();
    }

    // Expose globally immediately (methods will handle initialization)
    window.FilterLoadingModal = FilterLoadingModal;
})();
