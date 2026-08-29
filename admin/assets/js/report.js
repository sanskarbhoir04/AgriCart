/* =====================================================================
 * admin/assets/js/report.js
 * Client-side behavior for the Reports & Analytics module:
 *   - Chart.js rendering for whichever tab is currently active
 *     (window.RPT_DATA is emitted server-side in report.php)
 *   - CSV / Excel export (delegates to report_export.php, GET, same
 *     filters as the current view — read-only, no CSRF needed)
 *   - Print / "Export PDF" both use the browser's print-to-PDF via
 *     the print stylesheet in report.css (no server PDF library is
 *     vendored in this project, so this keeps the module dependency-
 *     free while still producing a clean PDF from the print dialog)
 *   - Lightweight global search across orders / products / users
 * ===================================================================== */
(function () {
    'use strict';

    var DATA = window.RPT_DATA || {};
    var THEME = {
        primary: '#2F4F44',
        primaryLight: '#4A7A6A',
        accent: '#A98B4A',
        success: '#2E7D32',
        warn: '#B8860B',
        danger: '#9B3B37',
        grid: '#E3E7E2'
    };
    var PALETTE = [THEME.primary, THEME.accent, THEME.success, THEME.warn, THEME.danger, THEME.primaryLight, '#6B8F82', '#C9A96A'];

    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#68706B';

    function el(id) { return document.getElementById(id); }

    function lineChart(canvasId, labels, values, label, color) {
        var c = el(canvasId);
        if (!c) return;
        new Chart(c, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '22',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: color
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: THEME.grid }, beginAtZero: true }
                }
            }
        });
    }

    function barChart(canvasId, labels, values, label, color, horizontal) {
        var c = el(canvasId);
        if (!c) return;
        new Chart(c, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: label, data: values, backgroundColor: color, borderRadius: 6, maxBarThickness: 34 }] },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: !horizontal ? false : true, color: THEME.grid } },
                    y: { grid: { display: horizontal ? false : true, color: THEME.grid }, beginAtZero: true }
                }
            }
        });
    }

    function pieChart(canvasId, labels, values, doughnut) {
        var c = el(canvasId);
        if (!c) return;
        new Chart(c, {
            type: doughnut ? 'doughnut' : 'pie',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 11, padding: 12 } } }
            }
        });
    }

    function pluck(arr, key) { return (arr || []).map(function (r) { return r[key]; }); }

    function renderCharts() {
        var tab = DATA.activeTab;

        if (tab === 'overview' && DATA.overview) {
            lineChart('chartOvRevenue', pluck(DATA.overview.revenueTrend, 'label'), pluck(DATA.overview.revenueTrend, 'value'), 'Revenue', THEME.primary);
            pieChart('chartOvOrderStatus', pluck(DATA.overview.orderStatus, 'label'), pluck(DATA.overview.orderStatus, 'value'), false);
            pieChart('chartOvCategory', pluck(DATA.overview.categorySplit, 'label'), pluck(DATA.overview.categorySplit, 'value'), true);
        }
        if (tab === 'sales' && DATA.sales) {
            lineChart('chartSalesRevenue', pluck(DATA.sales.revenue, 'label'), pluck(DATA.sales.revenue, 'value'), 'Revenue', THEME.primary);
            barChart('chartSalesTrend', pluck(DATA.sales.orders, 'label'), pluck(DATA.sales.orders, 'value'), 'Orders', THEME.accent, false);
        }
        if (tab === 'products' && DATA.products) {
            barChart('chartProductSales', pluck(DATA.products.sales, 'label'), pluck(DATA.products.sales, 'value'), 'Qty Sold', THEME.success, true);
        }
        if (tab === 'rentals' && DATA.rentals) {
            pieChart('chartRentalStatus', pluck(DATA.rentals.status, 'label'), pluck(DATA.rentals.status, 'value'), true);
        }
        if (tab === 'users' && DATA.users) {
            lineChart('chartUserRegistration', pluck(DATA.users.registration, 'label'), pluck(DATA.users.registration, 'value'), 'New Users', THEME.primary);
        }
        if (tab === 'reviews' && DATA.reviews) {
            barChart('chartRatingDist', pluck(DATA.reviews.ratingDist, 'label'), pluck(DATA.reviews.ratingDist, 'value'), 'Reviews', THEME.warn, true);
        }
    }

    // ---- Export (CSV / Excel) — read-only GET request carrying the current tab + filters ----
    window.rptExport = function (type) {
        var params = new URLSearchParams(window.location.search);
        params.set('export', type);
        window.location.href = 'report_export.php?' + params.toString();
    };

    // ---- Global search ----
    var searchTimer = null;
    function initSearch() {
        var input = el('rptGlobalSearch');
        if (!input) return;
        var results = document.createElement('div');
        results.className = 'rpt-search-results';
        results.id = 'rptSearchResults';
        input.parentNode.appendChild(results);

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(searchTimer);
            if (q.length < 2) { results.classList.remove('open'); return; }
            searchTimer = setTimeout(function () { runSearch(q, results); }, 300);
        });
        document.addEventListener('click', function (e) {
            if (!results.contains(e.target) && e.target !== input) { results.classList.remove('open'); }
        });
    }

    function runSearch(q, results) {
        fetch('report_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) { results.classList.remove('open'); return; }
                var html = '';
                ['orders', 'products', 'users'].forEach(function (group) {
                    var items = data.results[group] || [];
                    if (!items.length) return;
                    items.forEach(function (item) {
                        html += '<a class="rpt-search-result" href="' + item.url + '">' +
                            '<span class="grp">' + group + '</span><br>' + item.label + '</a>';
                    });
                });
                results.innerHTML = html || '<div class="rpt-search-result">No matches found.</div>';
                results.classList.add('open');
            })
            .catch(function () { results.classList.remove('open'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCharts();
        initSearch();
    });
})();
