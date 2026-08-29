<?php
// =====================================================================
// admin/inventory_export.php — CSV / Excel export for the Inventory
// Management module. Same technique as report_export.php: real CSV for
// "csv", an HTML table served with the Excel MIME type for "excel" (no
// PDF library vendored — the "Export PDF" button in inventory.php uses
// the browser's print-to-PDF via report.css's @media print rules).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/reports_schema.php';
require_once __DIR__ . '/includes/inventory_schema.php';
requirePermission('inventory.export');

$exportType = $_GET['export'] ?? 'csv';
if (!in_array($exportType, ['csv', 'excel'], true)) { $exportType = 'csv'; }

$section = $_GET['section'] ?? 'products';
if (!in_array($section, ['products', 'equipment', 'history'], true)) { $section = 'products'; }

$today = date('Y-m-d');
$headers = [];
$rows = [];

if ($section === 'products') {
    $q = trim($_GET['q'] ?? '');
    $fCategory = trim($_GET['category'] ?? '');
    $fSeller   = trim($_GET['seller'] ?? '');
    $fStatus   = trim($_GET['status'] ?? '');

    $where = ["is_active = 1"]; $types = ''; $params = [];
    if ($q !== '')        { $where[] = "(name LIKE ? OR sku LIKE ?)"; $types .= 'ss'; $like = "%$q%"; array_push($params, $like, $like); }
    if ($fCategory !== '') { $where[] = "category = ?"; $types .= 's'; $params[] = $fCategory; }
    if ($fSeller !== '')   { $where[] = "farmer_name = ?"; $types .= 's'; $params[] = $fSeller; }
    if ($fStatus === 'in_stock')  { $where[] = "stock > low_stock_threshold"; }
    if ($fStatus === 'low_stock') { $where[] = "stock > 0 AND stock <= low_stock_threshold"; }
    if ($fStatus === 'out_of_stock') { $where[] = "(stock <= 0 OR stock IS NULL)"; }
    $whereSql = implode(' AND ', $where);

    $headers = ['Product', 'SKU', 'Category', 'Seller', 'Quantity', 'Unit Price', 'Stock Value', 'Status', 'Last Updated'];
    foreach (rpt_prepared_rows($conn, "SELECT name, sku, category, farmer_name, stock, price, low_stock_threshold, updated_at FROM products WHERE $whereSql ORDER BY id DESC LIMIT 5000", $types, $params) as $r) {
        $status = inv_product_status((int)$r['stock'], (int)$r['low_stock_threshold']);
        $rows[] = [$r['name'], $r['sku'] ?: '', $r['category'], $r['farmer_name'], (int)$r['stock'], $r['price'], ((int)$r['stock']) * (float)$r['price'], $status['label'], $r['updated_at']];
    }
}

if ($section === 'equipment') {
    $q      = trim($_GET['q'] ?? '');
    $fType  = trim($_GET['type'] ?? '');
    $fOwner = trim($_GET['owner'] ?? '');
    $fStatus = trim($_GET['status'] ?? '');

    $where = ["1=1"]; $types = ''; $params = [];
    if ($q !== '')      { $where[] = "(name LIKE ? OR pn LIKE ? OR serial_no LIKE ?)"; $types .= 'sss'; $like = "%$q%"; array_push($params, $like, $like, $like); }
    if ($fType !== '')  { $where[] = "type = ?"; $types .= 's'; $params[] = $fType; }
    if ($fOwner !== '') { $where[] = "owner_name = ?"; $types .= 's'; $params[] = $fOwner; }
    if ($fStatus === 'available')      { $where[] = "availability = 1 AND (maintenance_status = 'available' OR maintenance_status IS NULL)"; }
    if ($fStatus === 'maintenance')    { $where[] = "maintenance_status = 'maintenance'"; }
    if ($fStatus === 'out_of_service') { $where[] = "(availability = 0 OR maintenance_status = 'out_of_service')"; }
    $whereSql = implode(' AND ', $where);

    $activeBookingIds = [];
    foreach (rpt_rows($conn, "SELECT DISTINCT equipment_id FROM equipment_bookings WHERE status IN ('confirmed','on_the_way') AND from_date <= '$today' AND to_date >= '$today'") as $r) {
        $activeBookingIds[(int)$r['equipment_id']] = true;
    }

    $headers = ['Equipment', 'Equipment ID', 'Category', 'Owner/Seller', 'Rental Price/Day', 'Equipment Value', 'Status', 'Last Updated'];
    foreach (rpt_prepared_rows($conn, "SELECT id, name, type, owner_name, availability, maintenance_status, rent_per_day, equipment_value, updated_at FROM equipment WHERE $whereSql ORDER BY id DESC LIMIT 5000", $types, $params) as $r) {
        $status = inv_equipment_status($r, $activeBookingIds);
        $rows[] = [$r['name'], '#' . $r['id'], $r['type'], $r['owner_name'], $r['rent_per_day'], $r['equipment_value'] ?? '', $status['label'], $r['updated_at']];
    }
}

if ($section === 'history') {
    $fType   = trim($_GET['type'] ?? '');
    $fAction = trim($_GET['haction'] ?? '');
    $q       = trim($_GET['q'] ?? '');

    $where = ["1=1"]; $types = ''; $params = [];
    if ($fType !== '')   { $where[] = "item_type = ?"; $types .= 's'; $params[] = $fType; }
    if ($fAction !== '') { $where[] = "action = ?"; $types .= 's'; $params[] = $fAction; }
    if ($q !== '')       { $where[] = "item_name LIKE ?"; $types .= 's'; $params[] = "%$q%"; }
    $whereSql = implode(' AND ', $where);

    $headers = ['Date & Time', 'Item Name', 'Type', 'Action', 'Previous Qty', 'Updated Qty', 'Updated By', 'Remarks'];
    foreach (rpt_prepared_rows($conn, "SELECT * FROM inventory_stock_history WHERE $whereSql ORDER BY id DESC LIMIT 5000", $types, $params) as $r) {
        $rows[] = [$r['created_at'], $r['item_name'], ucfirst($r['item_type']), ucwords(str_replace('_', ' ', $r['action'])), $r['previous_qty'], $r['updated_qty'], $r['updated_by'], $r['remarks']];
    }
}

if (function_exists('logAdminActivity')) {
    logAdminActivity('inventory_exported', 'inventory', null, null, $section, "Exported \"$section\" inventory as $exportType");
}

$filename = rpt_csrf_safe_filename('agricart_inventory_' . $section . '_' . date('Y-m-d'));

if ($exportType === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><tr>';
    foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) { echo '<td>' . htmlspecialchars((string)$cell) . '</td>'; }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

// CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $row) { fputcsv($out, $row); }
fclose($out);
