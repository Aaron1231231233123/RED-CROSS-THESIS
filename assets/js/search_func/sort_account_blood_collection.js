document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('bloodCollectionTableBody');
    if (!tbody) return;

    if (!window.__bloodCollectionTableCache) {
        window.__bloodCollectionTableCache = {
            initial: tbody.innerHTML,
            last: tbody.innerHTML
        };
    }

    const cache = window.__bloodCollectionTableCache;
    const headers = document.querySelectorAll('th[data-sort-field]');
    const loadingIndicator = document.getElementById('searchLoading');
    const searchInput = document.getElementById('searchInput');
    const paginationContainer = document.querySelector('.pagination-container');
    const paginationList = paginationContainer ? paginationContainer.querySelector('.pagination') : null;

    let isSortingActive = false;
    let perPage = parseInt(tbody.dataset.recordsPerPage || '15', 10);
    if (!Number.isFinite(perPage) || perPage <= 0) perPage = 15;
    let currentPage = parseInt(tbody.dataset.currentPage || '1', 10);
    if (!Number.isFinite(currentPage) || currentPage < 1) currentPage = 1;
    let totalPages = parseInt(tbody.dataset.totalPages || '0', 10);
    if (!Number.isFinite(totalPages) || totalPages < 0) totalPages = 0;
    const statusFilter = tbody.dataset.statusFilter || 'all';
    const statusParam = tbody.dataset.statusParam || '';

    const activeSort = {
        column: tbody.dataset.sortColumn || null,
        direction: tbody.dataset.sortDirection || 'default'
    };

    if ('MutationObserver' in window) {
        const observer = new MutationObserver(() => {
            window.__bloodCollectionTableCache.last = tbody.innerHTML;
            if (!isSortingActive) {
                resetAllHeaders();
            }
        });
        observer.observe(tbody, { childList: true, subtree: false });
    }

    headers.forEach((header) => {
        const trigger = header.querySelector('.sort-trigger');
        if (!trigger) return;

        trigger.setAttribute('data-sort-state', 'default');
        header.setAttribute('aria-sort', 'none');
        updateIcon(trigger, 'default');

        if (activeSort.direction !== 'default' && activeSort.column === header.getAttribute('data-sort-field')) {
            trigger.setAttribute('data-sort-state', activeSort.direction);
            updateIcon(trigger, activeSort.direction);
            header.setAttribute('aria-sort', activeSort.direction === 'asc' ? 'ascending' : 'descending');
        }

        trigger.addEventListener('click', () => {
            const currentState = trigger.getAttribute('data-sort-state') || 'default';
            const nextState = currentState === 'default' ? 'asc' : currentState === 'asc' ? 'desc' : 'default';

            resetOtherHeaders(header);
            trigger.setAttribute('data-sort-state', nextState);
            updateIcon(trigger, nextState);

            const column = header.getAttribute('data-sort-field');
            activeSort.column = column;
            activeSort.direction = nextState;
            currentPage = 1;

            const query = searchInput ? searchInput.value.trim() : '';
            const filters = collectFilters();

            if (nextState === 'default') {
                if (!query && filtersEmpty(filters)) {
                    isSortingActive = false;
                    activeSort.column = null;
                    activeSort.direction = 'default';
                    tbody.dataset.sortColumn = '';
                    tbody.dataset.sortDirection = 'default';
                    tbody.innerHTML = cache.initial;
                    window.__bloodCollectionTableCache.last = cache.initial;
                    rebuildPagination(totalPages, currentPage);
                    rebindRowHandlers();
                    resetAllHeaders();
                    return;
                }
                performSort({ column, direction: 'default', query, filters }, trigger);
                return;
            }

            performSort({ column, direction: nextState, query, filters }, trigger);
        });
    });

    if (paginationList) {
        paginationList.addEventListener('click', function (event) {
            const link = event.target.closest('a[data-page]');
            if (!link) return;
            const parent = link.closest('.page-item');
            if (parent && parent.classList.contains('disabled')) return;
            if (!hasActiveSort()) return;

            event.preventDefault();
            const targetPage = parseInt(link.dataset.page, 10);
            if (!Number.isFinite(targetPage) || targetPage === currentPage) return;

            currentPage = targetPage;
            const query = searchInput ? searchInput.value.trim() : '';
            const filters = collectFilters();
            performSort({
                column: activeSort.column,
                direction: activeSort.direction,
                query,
                filters
            }, null);
        });
    }

    function resetOtherHeaders(activeHeader) {
        headers.forEach((header) => {
            if (header === activeHeader) return;
            const otherTrigger = header.querySelector('.sort-trigger');
            if (!otherTrigger) return;
            otherTrigger.setAttribute('data-sort-state', 'default');
            updateIcon(otherTrigger, 'default');
            header.setAttribute('aria-sort', 'none');
        });
    }

    function resetAllHeaders() {
        headers.forEach((header) => {
            const trigger = header.querySelector('.sort-trigger');
            if (!trigger) return;
            trigger.setAttribute('data-sort-state', 'default');
            updateIcon(trigger, 'default');
            header.setAttribute('aria-sort', 'none');
        });
        activeSort.column = null;
        activeSort.direction = 'default';
        tbody.dataset.sortColumn = '';
        tbody.dataset.sortDirection = 'default';
    }

    function updateIcon(trigger, state) {
        const icon = trigger.querySelector('.sort-icon');
        const header = trigger.closest('th');
        if (icon) {
            icon.classList.remove('fa-sort-up', 'fa-sort-down', 'fa-sort');
            switch (state) {
                case 'asc':
                    icon.classList.add('fa-sort-up');
                    break;
                case 'desc':
                    icon.classList.add('fa-sort-down');
                    break;
                default:
                    icon.classList.add('fa-sort');
            }
        }
        if (header) {
            const ariaValue = state === 'asc' ? 'ascending' : state === 'desc' ? 'descending' : 'none';
            header.setAttribute('aria-sort', ariaValue);
        }
    }

    function collectFilters() {
        const status = [];
        const completed = document.getElementById('fltStatusCompleted');
        const failed = document.getElementById('fltStatusFailed');
        const notStarted = document.getElementById('fltStatusNotStarted');
        if (completed && completed.checked) status.push('completed');
        if (failed && failed.checked) status.push('failed');
        if (notStarted && notStarted.checked) status.push('not started');
        const q = searchInput ? searchInput.value.trim() : '';
        return { status, q };
    }

    function filtersEmpty(filters) {
        if (!filters) return true;
        const hasStatus = filters.status && filters.status.length > 0;
        const hasQuery = filters.q && filters.q.length > 0;
        return !hasStatus && !hasQuery;
    }

    function hasActiveSort() {
        return !!(activeSort.direction && activeSort.direction !== 'default' && activeSort.column);
    }

    function performSort(payload, trigger) {
        const effectiveColumn = payload.column || activeSort.column;
        const effectiveDirection = payload.direction || activeSort.direction || 'default';
        if (!effectiveColumn && effectiveDirection !== 'default') {
            return;
        }

        const filters = payload.filters || collectFilters();
        const query = typeof payload.query === 'string' ? payload.query : (filters.q || '');

        const requestPayload = {
            column: effectiveDirection === 'default' ? (effectiveColumn || 'no') : effectiveColumn,
            direction: effectiveDirection,
            query,
            filters,
            status_filter: statusFilter,
            page: currentPage,
            per_page: perPage
        };

        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }
        if (trigger) {
            trigger.setAttribute('aria-busy', 'true');
        }
        isSortingActive = true;

        fetch('../api/search_func/sort_account_blood_collection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestPayload)
        })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((res) => {
                if (res && res.success && typeof res.html === 'string') {
                    tbody.innerHTML = res.html;
                    window.__bloodCollectionTableCache.last = res.html;
                    if (typeof res.current_page !== 'undefined') {
                        const parsedPage = parseInt(res.current_page, 10);
                        if (Number.isFinite(parsedPage) && parsedPage >= 1) {
                            currentPage = parsedPage;
                            tbody.dataset.currentPage = String(currentPage);
                        }
                    }
                    if (typeof res.records_per_page !== 'undefined') {
                        const parsedPerPage = parseInt(res.records_per_page, 10);
                        if (Number.isFinite(parsedPerPage) && parsedPerPage > 0) {
                            perPage = parsedPerPage;
                            tbody.dataset.recordsPerPage = String(perPage);
                        }
                    }
                    if (typeof res.total_pages !== 'undefined') {
                        const parsedTotal = parseInt(res.total_pages, 10);
                        totalPages = Number.isFinite(parsedTotal) && parsedTotal >= 0 ? parsedTotal : 0;
                        tbody.dataset.totalPages = String(totalPages);
                    }
                    if (effectiveDirection === 'default') {
                        resetAllHeaders();
                    } else {
                        tbody.dataset.sortDirection = effectiveDirection;
                        tbody.dataset.sortColumn = effectiveColumn;
                    }
                    rebuildPagination(totalPages, currentPage);
                    rebindRowHandlers();
                } else if (!query && filtersEmpty(filters) && effectiveDirection === 'default') {
                    tbody.innerHTML = cache.initial;
                    window.__bloodCollectionTableCache.last = cache.initial;
                    tbody.dataset.currentPage = String(currentPage);
                    resetAllHeaders();
                    rebuildPagination(totalPages, currentPage);
                    rebindRowHandlers();
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No records found</td></tr>';
                    window.__bloodCollectionTableCache.last = tbody.innerHTML;
                    rebuildPagination(0, 1);
                }
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Unable to sort records. Please try again.</td></tr>';
                rebuildPagination(totalPages, currentPage);
            })
            .finally(() => {
                if (trigger) {
                    trigger.removeAttribute('aria-busy');
                }
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
                isSortingActive = false;
            });
    }

    function rebuildPagination(total, current) {
        if (!paginationList || !paginationContainer) return;

        if (!total || total <= 1) {
            paginationList.innerHTML = '';
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = '';
        const fragment = document.createDocumentFragment();

        const createPageItem = (page, label, { disabled = false, active = false, ellipsis = false } = {}) => {
            const li = document.createElement('li');
            li.classList.add('page-item');
            if (disabled) li.classList.add('disabled');
            if (active) li.classList.add('active');

            if (ellipsis) {
                const span = document.createElement('span');
                span.className = 'page-link';
                span.textContent = label;
                li.appendChild(span);
                return li;
            }

            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = `?page=${page}${statusParam}`;
            a.dataset.page = String(page);
            a.textContent = label;
            if (disabled) {
                a.setAttribute('tabindex', '-1');
                a.setAttribute('aria-disabled', 'true');
            }
            li.appendChild(a);
            return li;
        };

        const prevPage = Math.max(1, current - 1);
        fragment.appendChild(createPageItem(prevPage, 'Previous', {
            disabled: current <= 1
        }));

        const startPage = Math.max(1, current - 2);
        const endPage = Math.min(total, current + 2);

        if (startPage > 1) {
            fragment.appendChild(createPageItem(1, '1'));
            if (startPage > 2) {
                fragment.appendChild(createPageItem(0, '...', { ellipsis: true, disabled: true }));
            }
        }

        for (let page = startPage; page <= endPage; page++) {
            fragment.appendChild(createPageItem(page, String(page), {
                active: page === current
            }));
        }

        if (endPage < total) {
            if (endPage < total - 1) {
                fragment.appendChild(createPageItem(0, '...', { ellipsis: true, disabled: true }));
            }
            fragment.appendChild(createPageItem(total, String(total)));
        }

        const nextPage = Math.min(total, current + 1);
        fragment.appendChild(createPageItem(nextPage, 'Next', {
            disabled: current >= total
        }));

        paginationList.innerHTML = '';
        paginationList.appendChild(fragment);
    }

    function rebindRowHandlers() {
        try {
            if (typeof attachRowClickHandlers === 'function') {
                attachRowClickHandlers();
            }
        } catch (e) {
            console.error(e);
        }
    }
});


