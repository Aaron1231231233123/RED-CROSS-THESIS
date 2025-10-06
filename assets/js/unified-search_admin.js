/*
 Unified Search Module
 - Reusable, configurable search engine for admin dashboards
 - Supports frontend, backend, and hybrid modes
*/
(function() {
  'use strict';

  function $(id) { return document.getElementById(id); }

  function debounce(fn, wait) {
    let t;
    return function() {
      const ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function() { fn.apply(ctx, args); }, wait);
    };
  }

  function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function highlightText(node, query) {
    if (!query || !node) return;
    const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null, false);
    const textNodes = [];
    let current;
    while ((current = walker.nextNode())) textNodes.push(current);
    const re = new RegExp('(' + escapeRegExp(query) + ')', 'ig');
    for (var i = 0; i < textNodes.length; i++) {
      var tn = textNodes[i];
      if (!tn.nodeValue || !re.test(tn.nodeValue)) continue;
      const span = document.createElement('span');
      span.innerHTML = tn.nodeValue.replace(re, '<mark>$1</mark>');
      tn.parentNode.replaceChild(span, tn);
    }
  }

  function clearHighlights(container) {
    if (!container) return;
    var marks = container.querySelectorAll('mark');
    for (var i = 0; i < marks.length; i++) {
      var m = marks[i];
      m.replaceWith(document.createTextNode(m.textContent));
    }
  }

  function textIncludes(haystack, needle) {
    return String(haystack || '').toLowerCase().indexOf(String(needle || '').toLowerCase()) !== -1;
  }

  function UnifiedSearch(config) {
    this.inputEl = config.inputId ? $(config.inputId) : null;
    this.categoryEl = config.categoryId ? $(config.categoryId) : null;
    this.tableEl = config.tableId ? $(config.tableId) : null;
    this.rowSelector = config.rowSelector || 'tbody tr';
    this.mode = config.mode || 'frontend';
    this.debounceMs = typeof config.debounceMs === 'number' ? config.debounceMs : 250;
    this.highlight = !!config.highlight;
    this.columnsMapping = config.columnsMapping || { all: 'all' };
    this.backend = config.backend || null;
    this.renderResults = typeof config.renderResults === 'function' ? config.renderResults : null;
    this.autobind = config.autobind !== false; // default true
    this.currentQuery = '';
    this.isSearching = false;
    this.init();
  }

  UnifiedSearch.prototype.init = function() {
    if (!this.inputEl) return;
    if (!this.autobind) return; // allow pages to include without attaching listeners
    this.bind();
  };

  UnifiedSearch.prototype.bind = function() {
    if (!this.inputEl) return;
    var onInput = debounce(this.onInput.bind(this), this.debounceMs);
    this.inputEl.addEventListener('input', onInput);
    if (this.categoryEl) this.categoryEl.addEventListener('change', onInput);
  };

  UnifiedSearch.prototype.onInput = function() {
    var q = (this.inputEl && this.inputEl.value || '').trim();
    var category = this.categoryEl ? this.categoryEl.value : 'all';
    this.currentQuery = q;
    if (this.mode === 'frontend') {
      this.searchFrontend(q, category);
    } else if (this.mode === 'backend') {
      this.searchBackend(q, category);
    } else {
      // hybrid: immediate frontend, then backend
      this.searchFrontend(q, category);
      this.searchBackend(q, category);
    }
  };

  UnifiedSearch.prototype.getRows = function() {
    if (!this.tableEl) return [];
    return Array.prototype.slice.call(this.tableEl.querySelectorAll(this.rowSelector));
  };

  UnifiedSearch.prototype.resetFrontend = function() {
    var rows = this.getRows();
    for (var i = 0; i < rows.length; i++) rows[i].style.display = '';
    if (this.highlight) clearHighlights(this.tableEl);
  };

  UnifiedSearch.prototype.matchRow = function(row, query, category) {
    if (!query) return true;
    var columns = this.columnsMapping[category] != null ? this.columnsMapping[category] : 'all';
    if (columns === 'all') {
      var cellsAll = row.querySelectorAll('td');
      for (var i = 0; i < cellsAll.length; i++) {
        if (textIncludes(cellsAll[i].textContent, query)) return true;
      }
      return false;
    }
    if (Array.isArray(columns)) {
      for (var j = 0; j < columns.length; j++) {
        var idx = columns[j];
        var cell = row.cells && row.cells[idx];
        if (cell && textIncludes(cell.textContent, query)) return true;
      }
      return false;
    }
    // single index
    var cellSingle = row.cells && row.cells[columns];
    return !!(cellSingle && textIncludes(cellSingle.textContent, query));
  };

  UnifiedSearch.prototype.searchFrontend = function(query, category) {
    if (!this.tableEl) return;
    if (!query) { this.resetFrontend(); return; }
    var rows = this.getRows();
    var visibleCount = 0;
    if (this.highlight) clearHighlights(this.tableEl);
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var show = this.matchRow(row, query, category);
      row.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    }
    if (this.highlight && visibleCount) highlightText(this.tableEl, query);
  };

  UnifiedSearch.prototype.searchBackend = function(query, category) {
    if (!this.backend || !this.backend.url) return;
    if (this.isSearching) return;
    var url = this.backend.url;
    var action = this.backend.action || '';
    var pageSize = this.backend.pageSize || 50;
    var page = 1;
    var params = new URLSearchParams({ action: action, q: query || '', category: category || 'all', page: String(page), limit: String(pageSize) });
    var self = this;
    this.isSearching = true;
    fetch(url + '?' + params.toString(), { method: 'GET', headers: { 'Accept': 'application/json' } })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        self.isSearching = false;
        if (typeof self.renderResults === 'function') {
          self.renderResults(data);
        } else if (self.tableEl && data && Array.isArray(data.results)) {
          // Default renderer assumes array of arrays matching table columns
          var tbody = self.tableEl.querySelector('tbody');
          if (!tbody) return;
          while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
          for (var i = 0; i < data.results.length; i++) {
            var rowData = data.results[i];
            var tr = document.createElement('tr');
            if (Array.isArray(rowData)) {
              for (var c = 0; c < rowData.length; c++) {
                var td = document.createElement('td');
                td.textContent = rowData[c] == null ? '' : String(rowData[c]);
                tr.appendChild(td);
              }
            } else if (rowData && typeof rowData === 'object' && rowData.html) {
              tr.innerHTML = rowData.html; // allow server to send HTML row
            }
            tbody.appendChild(tr);
          }
          if (self.highlight && self.currentQuery) highlightText(self.tableEl, self.currentQuery);
        }
      })
      .catch(function() { self.isSearching = false; });
  };

  // Expose globally
  window.UnifiedSearch = UnifiedSearch;
})();


