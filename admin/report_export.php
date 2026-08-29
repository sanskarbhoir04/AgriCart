<?php
// =====================================================================
// admin/report_export.php — CSV / Excel export for the Reports module.
//
// GET-only, read-only, prepared statements throughout (same filters as
// the current report.php view are re-applied here). No third-party
// PDF/Excel library is vendored in this project, so:
//   - CSV  -> a real text/csv file (opens in Excel/Sheets natively).
//   - Excel -> an HTML table served with the classic .xls / Excel MIME
//              type. This is the same technique used by most PHP admin
//              panels that don't ship PhpSpreadsheet — Excel opens it
//              directly and every column/format still looks right.
//   - PDF  -> intentionally NOT generated here; the "Export PDF" button
//              in report.php uses the browser's print-to-PDF instead
//              (see report.css's @media print rules), which needs zero
//              extra dependencies and produces a clean, styled PDF.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/reports_schema.php';
requirePermission('reports.view');

$exportType = $_GET['export'] ?? 'csv';
if (!in_array($exportType, ['csv', 'excel'], true)) { $exportType = 'csv'; }

$tab = $_GET['tab'] ?? 'overview';
$allowedTabs = ['overview', 'sales', 'products', 'rentals', 'orders', 'users', 'payments', 'reviews'];
if (!in_array($tab, $allowedTabs, true)) { $tab = 'overview'; }

$fCategory      = trim($_GET['category'] ?? '');
$fSeller        = trim($_GET['seller'] ?? '');
$fOrderStatus   = trim($_GET['order_status'] ?? '');
$fPaymentStatus = trim($_GET['payment_status'] ?? '');
$fRating        = trim($_GET['rating'] ?? '');
$fDistrict      = trim($_GET['district'] ?? '');
$fSearch        = trim($_GET['q'] ?? '');
$fromDate       = $_GET['from'] ?? '';
$toDate         = $_GET['to'] ?? '';

// ---- Build [header row, data rows] for whichever tab was exported ----
$headers = [];
$rows = [];

switch ($tab) {
    case 'orders':
        $where = ["1=1"]; $types = ''; $params = [];
        if ($fOrderStatus !== '') { $where[] = "o.order_status = ?"; $types .= 's'; $params[] = $fOrderStatus; }
        if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) { $where[] = "DATE(o.created_at) >= ?"; $types .= 's'; $params[] = $fromDate; }
        if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   { $where[] = "DATE(o.created_at) <= ?"; $types .= 's'; $params[] = $toDate; }
        if ($fSearch !== '') { $where[] = "(o.order_number LIKE ? OR u.full_name LIKE ?)"; $types .= 'ss'; $like = '%'.$fSearch.'%'; array_push($params, $like, $like); }
        $whereSql = implode(' AND ', $where);
        $headers = ['Order #', 'Customer', 'Mobile', 'Date', 'Amount', 'Status'];
        foreach (rpt_prepared_rows($conn, "SELECT o.order_number, o.created_at, o.total_amount, o.final_amount, o.order_status, u.full_name, u.mobile FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE $whereSql ORDER BY o.id DESC LIMIT 5000", $types, $params) as $r) {
            $rows[] = [$r['order_number'], $r['full_name'], $r['mobile'], $r['created_at'], $r['final_amount'] ?? $r['total_amount'], ucfirst($r['order_status'])];
        }
        break;

    case 'products':
        $where = ["1=1"]; $types = ''; $params = [];
        if ($fCategory !== '') { $where[] = "p.category = ?"; $types .= 's'; $params[] = $fCategory; }
        if ($fSeller !== '')   { $where[] = "p.farmer_name = ?"; $types .= 's'; $params[] = $fSeller; }
        $whereSql = implode(' AND ', $where);
        $headers = ['Product', 'Category', 'Seller', 'Qty Sold', 'Revenue'];
        foreach (rpt_prepared_rows($conn, "
            SELECT p.name, p.category, p.farmer_name, COALESCE(SUM(oi.quantity),0) AS qty_sold, COALESCE(SUM(oi.quantity*oi.price),0) AS revenue
              FROM products p
              LEFT JOIN order_items oi ON oi.product_id = p.id
              LEFT JOIN orders o ON o.id = oi.order_id AND o.order_status NOT IN ('cancelled','returned')
             WHERE $whereSql GROUP BY p.id, p.name, p.category, p.farmer_name ORDER BY qty_sold DESC LIMIT 5000", $types, $params) as $r) {
            $rows[] = [$r['name'], $r['category'], $r['farmer_name'], (int)$r['qty_sold'], (float)$r['revenue']];
        }
        break;

    case 'rentals':
        $headers = ['Equipment', 'Bookings', 'Revenue'];
        foreach (rpt_rows($conn, "SELECT e.name, COUNT(eb.id) AS booking_cnt, COALESCE(SUM(eb.total_amount),0) AS revenue FROM equipment_bookings eb JOIN equipment e ON e.id=eb.equipment_id GROUP BY e.id, e.name ORDER BY booking_cnt DESC LIMIT 5000") as $r) {
            $rows[] = [$r['name'], (int)$r['booking_cnt'], (float)$r['revenue']];
        }
        break;

    case 'users':
        $where = ["1=1"]; $types = ''; $params = [];
        if ($fDistrict !== '') { $where[] = "district = ?"; $types .= 's'; $params[] = $fDistrict; }
        if ($fSearch !== '')   { $where[] = "(full_name LIKE ? OR mobile LIKE ?)"; $types .= 'ss'; $like = '%'.$fSearch.'%'; array_push($params, $like, $like); }
        $whereSql = implode(' AND ', $where);
        $headers = ['Name', 'Mobile', 'Email', 'District', 'Role', 'Joined'];
        foreach (rpt_prepared_rows($conn, "SELECT full_name, mobile, email, district, role, created_at FROM users WHERE $whereSql ORDER BY id DESC LIMIT 5000", $types, $params) as $r) {
            $rows[] = [$r['full_name'], $r['mobile'], $r['email'], $r['district'], ucfirst($r['role'] ?? ''), $r['created_at']];
        }
        break;

    case 'payments':
        $where = ["1=1"]; $types = ''; $params = [];
        if ($fPaymentStatus !== '') { $where[] = "p.status = ?"; $types .= 's'; $params[] = $fPaymentStatus; }
        $whereSql = implode(' AND ', $where);
        $headers = ['Seller', 'Amount', 'Method', 'Requested', 'Status'];
        foreach (rpt_prepared_rows($conn, "SELECT p.amount, p.method, p.status, p.requested_at, u.full_name FROM payouts p LEFT JOIN users u ON u.id=p.seller_id WHERE $whereSql ORDER BY p.id DESC LIMIT 5000", $types, $params) as $r) {
            $rows[] = [$r['full_name'], (float)$r['amount'], $r['method'], $r['requested_at'], ucfirst($r['status'])];
        }
        break;

    case 'reviews':
        $where = ["1=1"]; $types = ''; $params = [];
        if ($fRating !== '' && ctype_digit($fRating)) { $where[] = "r.rating = ?"; $types .= 'i'; $params[] = (int)$fRating; }
        $whereSql = implode(' AND ', $where);
        $headers = ['Item', 'Rating', 'Comment', 'By', 'Date'];
        foreach (rpt_prepared_rows($conn, "
            SELECT r.rating, r.comment, r.created_at, r.item_type,
                   CASE WHEN r.item_type='product' THEN p.name WHEN r.item_type='equipment' THEN e.name ELSE NULL END AS item_name,
                   u.full_name
              FROM reviews r
              LEFT JOIN users u ON u.id = r.user_id
              LEFT JOIN products p ON r.item_type='product' AND p.id = r.item_id
              LEFT JOIN equipment e ON r.item_type='equipment' AND e.id = r.item_id
             WHERE $whereSql ORDER BY r.id DESC LIMIT 5000", $types, $params) as $r) {
            $rows[] = [$r['item_name'] ?? ucfirst($r['item_type']), (int)$r['rating'], $r['comment'], $r['full_name'], $r['created_at']];
        }
        break;

    case 'sales':
        $headers = ['Date', 'Revenue', 'Orders'];
        foreach (rpt_rows($conn, "SELECT DATE(created_at) AS d, SUM(COALESCE(final_amount,total_amount)) AS revenue, COUNT(*) AS cnt FROM orders WHERE order_status NOT IN ('cancelled','returned') GROUP BY d ORDER BY d DESC LIMIT 365") as $r) {
            $rows[] = [$r['d'], (float)$r['revenue'], (int)$r['cnt']];
        }
        break;

    case 'overview':
    default:
        $headers = ['Metric', 'Value'];
        $rows[] = ['Total Users', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users")];
        $rows[] = ['Total Sellers', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role='seller'")];
        $rows[] = ['Total Buyers', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role NOT IN ('seller','admin')")];
        $rows[] = ['Total Products', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active=1")];
        $rows[] = ['Total Orders', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders")];
        $rows[] = ['Total Revenue', (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE order_status NOT IN ('cancelled','returned')")];
        $rows[] = ['Total Reviews', (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews")];
        $rows[] = ['Average Rating', round((float)rpt_scalar($conn, "SELECT COALESCE(AVG(rating),0) FROM reviews"), 2)];
        break;
}

if (function_exists('logAdminActivity')) {
    logAdminActivity('report_exported', 'reports', null, null, $tab, "Exported \"$tab\" report as $exportType");
}

$filename = rpt_csrf_safe_filename('agricart_report_' . $tab . '_' . date('Y-m-d'));

if ($exportType === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel renders non-ASCII text correctly
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
