<?php
// =====================================================================
// admin/report_search.php — Global search used by the Reports module
// toolbar (assets/js/report.js). Read-only, GET-only, prepared
// statements throughout. Returns a small set of matches across
// orders / products / users so the admin can jump straight to the
// relevant report tab.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/reports_schema.php';
requirePermission('reports.view');

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => false, 'error' => 'Query too short.']);
    exit;
}
$like = '%' . $q . '%';

$results = ['orders' => [], 'products' => [], 'users' => []];

foreach (rpt_prepared_rows($conn,
    "SELECT id, order_number, order_status FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 5",
    's', [$like]) as $r) {
    $results['orders'][] = [
        'label' => htmlspecialchars(($r['order_number'] ?? ('#' . $r['id'])) . ' — ' . ucfirst($r['order_status'] ?? '')),
        'url'   => 'report.php?tab=orders&q=' . urlencode($r['order_number'] ?? ''),
    ];
}

foreach (rpt_prepared_rows($conn,
    "SELECT id, name, category FROM products WHERE name LIKE ? AND is_active = 1 ORDER BY id DESC LIMIT 5",
    's', [$like]) as $r) {
    $results['products'][] = [
        'label' => htmlspecialchars($r['name'] . ' — ' . ($r['category'] ?? '')),
        'url'   => 'report.php?tab=products&category=' . urlencode($r['category'] ?? ''),
    ];
}

foreach (rpt_prepared_rows($conn,
    "SELECT id, full_name, mobile FROM users WHERE full_name LIKE ? OR mobile LIKE ? ORDER BY id DESC LIMIT 5",
    'ss', [$like, $like]) as $r) {
    $results['users'][] = [
        'label' => htmlspecialchars($r['full_name'] . ' — ' . $r['mobile']),
        'url'   => 'report.php?tab=users&q=' . urlencode($r['full_name'] ?? ''),
    ];
}

echo json_encode(['success' => true, 'results' => $results]);
