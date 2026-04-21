/**
 * Client-side table sorting. Alphanumeric columns use PHP-style natural order:
 * same rules as natcasesort / strnatcasecmp (case-insensitive; digit runs compare numerically).
 * Implemented from Locutus strnatcmp (PHP 8.3 parity), lowercased for strnatcasecmp.
 * For case-sensitive natural order (PHP natsort / strnatcmp), use strnatcmp on raw strings instead.
 *
 * Cells with data-sort-value are sorted as that full string (natural), never digit-stripped, unless
 * the column th has data-sort-type="number".
 *
 * Tables may opt into a default sort via data-default-sort-col / data-default-sort-dir.
 */
(function () {
    'use strict';

    const SELECTOR = '.data-table';
    const tables = document.querySelectorAll(SELECTOR);
    if (tables.length === 0) {
        return;
    }

    const LEADING_ZEROS = /^0+(?=\d)/;
    const WHITESPACE = /^\s/;
    const DIGIT = /^\d/;

    function phpCastString(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value);
    }

    function strnatcmp(left, right) {
        let leftValue = phpCastString(left);
        let rightValue = phpCastString(right);
        if (leftValue.length === 0 || rightValue.length === 0) {
            return leftValue.length - rightValue.length;
        }
        let i = 0;
        let j = 0;
        leftValue = leftValue.replace(LEADING_ZEROS, '');
        rightValue = rightValue.replace(LEADING_ZEROS, '');
        while (i < leftValue.length && j < rightValue.length) {
            while (WHITESPACE.test(leftValue.charAt(i))) {
                i += 1;
            }
            while (WHITESPACE.test(rightValue.charAt(j))) {
                j += 1;
            }
            let ac = leftValue.charAt(i);
            let bc = rightValue.charAt(j);
            let aIsDigit = DIGIT.test(ac);
            let bIsDigit = DIGIT.test(bc);
            if (aIsDigit && bIsDigit) {
                let bias = 0;
                const fractional = ac === '0' || bc === '0';
                do {
                    if (!aIsDigit) {
                        return -1;
                    }
                    if (!bIsDigit) {
                        return 1;
                    }
                    if (ac < bc) {
                        if (bias === 0) {
                            bias = -1;
                        }
                        if (fractional) {
                            return -1;
                        }
                    } else if (ac > bc) {
                        if (bias === 0) {
                            bias = 1;
                        }
                        if (fractional) {
                            return 1;
                        }
                    }
                    i += 1;
                    j += 1;
                    ac = leftValue.charAt(i);
                    bc = rightValue.charAt(j);
                    aIsDigit = DIGIT.test(ac);
                    bIsDigit = DIGIT.test(bc);
                } while (aIsDigit || bIsDigit);
                if (!fractional && bias !== 0) {
                    return bias;
                }
                continue;
            }
            if (!ac || !bc) {
                continue;
            }
            if (ac < bc) {
                return -1;
            }
            if (ac > bc) {
                return 1;
            }
            i += 1;
            j += 1;
        }
        const iBeforeStrEnd = i < leftValue.length;
        const jBeforeStrEnd = j < rightValue.length;
        if (iBeforeStrEnd && !jBeforeStrEnd) {
            return 1;
        }
        if (!iBeforeStrEnd && jBeforeStrEnd) {
            return -1;
        }
        return 0;
    }

    function strnatcasecmp(a, b) {
        return strnatcmp(phpCastString(a).toLowerCase(), phpCastString(b).toLowerCase());
    }

    function parseCell(text) {
        const t = String(text).trim();
        const d = Date.parse(t);
        if (!Number.isNaN(d)) {
            return { type: 'date', value: d };
        }
        const num = t.replace(/[^0-9.-]/g, '');
        if (num !== '' && !Number.isNaN(parseFloat(num))) {
            return { type: 'number', value: parseFloat(num) };
        }
        return { type: 'natural', value: t };
    }

    function getColumnSortType(table, colIndex) {
        const ths = table.querySelectorAll('thead tr th');
        const th = ths[colIndex];
        if (!th || !th.dataset.sortType) {
            return '';
        }
        return String(th.dataset.sortType).toLowerCase();
    }

    function parseCellForSort(cell, colIndex, table) {
        const sortType = getColumnSortType(table, colIndex);
        if (cell.hasAttribute('data-sort-value')) {
            let raw = cell.getAttribute('data-sort-value');
            if (raw === null) {
                raw = '';
            }
            const trimmed = raw.trim();
            if (sortType === 'number') {
                const n = parseFloat(trimmed);
                return { type: 'number', value: Number.isFinite(n) ? n : 0 };
            }
            return { type: 'natural', value: trimmed };
        }
        return parseCell(cell.textContent);
    }

    function compare(a, b, dir) {
        const mul = dir === 'asc' ? 1 : -1;
        if (a.type !== b.type) {
            return (a.type < b.type ? -1 : 1) * mul;
        }
        if (a.type === 'number') {
            return (a.value - b.value) * mul;
        }
        if (a.type === 'date') {
            return (a.value - b.value) * mul;
        }
        if (a.type === 'natural') {
            return strnatcasecmp(a.value, b.value) * mul;
        }
        return 0;
    }

    function sortTable(table, colIndex, direction) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        const rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        if (rows.length === 0) {
            return;
        }

        rows.sort((ra, rb) => {
            const cellA = ra.cells[colIndex];
            const cellB = rb.cells[colIndex];
            if (!cellA || !cellB) {
                return 0;
            }
            const parsedA = parseCellForSort(cellA, colIndex, table);
            const parsedB = parseCellForSort(cellB, colIndex, table);
            return compare(parsedA, parsedB, direction);
        });

        rows.forEach((r) => {
            tbody.appendChild(r);
        });
    }

    function setHeaderState(table, colIndex, direction) {
        const ths = table.querySelectorAll('thead tr th');
        ths.forEach((th, i) => {
            th.classList.remove('sort-asc', 'sort-desc');
            th.setAttribute('aria-sort', 'none');
            if (i === colIndex) {
                th.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
                th.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
            }
        });
    }

    function initTable(table) {
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        if (!thead || !tbody) {
            return;
        }
        const headerRow = thead.querySelector('tr');
        if (!headerRow) {
            return;
        }
        const ths = headerRow.querySelectorAll('th');
        const numCols = ths.length;
        if (numCols < 2) {
            return;
        }
        const sortAllCols = table.classList.contains('data-table-sort-all-cols');
        const sortableCount = sortAllCols ? numCols : numCols - 1;
        for (let colIndex = 0; colIndex < sortableCount; colIndex += 1) {
            const th = ths[colIndex];
            th.classList.add('sortable');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');
            th.setAttribute('aria-sort', 'none');
            th.addEventListener('click', () => {
                let currentDir;
                if (th.classList.contains('sort-asc')) {
                    currentDir = 'asc';
                } else if (th.classList.contains('sort-desc')) {
                    currentDir = 'desc';
                } else {
                    currentDir = null;
                }
                const nextDir = currentDir === null ? 'asc' : (currentDir === 'asc' ? 'desc' : 'asc');
                setHeaderState(table, colIndex, nextDir);
                sortTable(table, colIndex, nextDir);
            });
            th.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    th.click();
                }
            });
        }

        const defaultColAttr = table.getAttribute('data-default-sort-col');
        if (defaultColAttr !== null && defaultColAttr !== '') {
            const defaultCol = parseInt(defaultColAttr, 10);
            const defaultDir = table.getAttribute('data-default-sort-dir') === 'desc' ? 'desc' : 'asc';
            if (Number.isInteger(defaultCol) && defaultCol >= 0 && defaultCol < sortableCount) {
                setHeaderState(table, defaultCol, defaultDir);
                sortTable(table, defaultCol, defaultDir);
            }
        }
    }

    tables.forEach(initTable);
}());
