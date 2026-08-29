<?php
// =====================================================================
// admin/global_search.php — Admin Global Search (spec §16).
//
// Searches across Sellers/Companies, Buyers, Products, Orders, Invoices,
// Payouts and GSTIN in one request, returns categorized results. Every
// category is gated by the SAME permission its own module page already
// requires, so an admin never sees a search result for something they
// couldn't otherwise open — this mirrors admin/report_search.php's
// pattern but widens it from "Reports only" to the whole admin panel.
//
// Read-only, GET-only, every query parameterized. Column-name variance
// across installs handled the same defensive way as pages/invoice.php's
// agi_pick_col (see gs_pick_col-style helpers below).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => false, 'error' => 'Type at least 2 characters.']);
    exit;
}
$like = '%' . $q . '%';

if (!function_exists('gs_col_exists')) {
    function gs_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('gs_pick_col')) {
    function gs_pick_col(mysqli $conn, string $table, array $candidates): ?string {
        foreach ($candidates as $c) { if (gs_col_exists($conn, $table, $c)) return $c; }
        return null;
    }
}

$results = [];

// ---- Sellers / Companies ----
if (hasPermission('companies.view')) {
    $items = [];
    $gstinCol = gs_pick_col($conn, 'sellers', ['gstin']);
    $sql = "SELECT id, name, category" . ($gstinCol ? ", $gstinCol AS gstin" : ", NULL AS gstin") . " FROM sellers WHERE name LIKE ?" . ($gstinCol ? " OR $gstinCol LIKE ?" : "") . " ORDER BY id DESC LIMIT 6";
    $stmt = $conn->prepare($sql);
    if ($gstinCol) { $stmt->bind_param('ss', $like, $like); } else { $stmt->bind_param('s', $like); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = ['label' => $r['name'] . ($r['gstin'] ? ' — ' . $r['gstin'] : ''), 'url' => 'companies.php?highlight=' . (int)$r['id']];
    }
    if ($items) { $results['Sellers / Companies'] = $items; }
}

// ---- Buyers / Accounts ----
if (hasPermission('accounts.view')) {
    $items = [];
    $mobileCol = gs_pick_col($conn, 'users', ['mobile', 'phone']);
    $sql = "SELECT id, full_name, email" . ($mobileCol ? ", $mobileCol AS mobile" : ", NULL AS mobile")
         . " FROM users WHERE role <> 'admin' AND (full_name LIKE ? OR email LIKE ?" . ($mobileCol ? " OR $mobileCol LIKE ?" : "") . ") ORDER BY id DESC LIMIT 6";
    $stmt = $conn->prepare($sql);
    if ($mobileCol) { $stmt->bind_param('sss', $like, $like, $like); } else { $stmt->bind_param('ss', $like, $like); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = ['label' => ($r['full_name'] ?: $r['email']) . ($r['mobile'] ? ' — ' . $r['mobile'] : ''), 'url' => 'account_details.php?id=' . (int)$r['id']];
    }
    if ($items) { $results['Buyers / Accounts'] = $items; }
}

// ---- Products ----
if (hasPermission('products.view')) {
    $items = [];
    $stmt = $conn->prepare("SELECT id, name, category FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 6");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = ['label' => $r['name'] . ($r['category'] ? ' (' . $r['category'] . ')' : ''), 'url' => 'index.php?tab=products&q=' . urlencode($r['name'])];
    }
    if ($items) { $results['Products'] = $items; }
}

// ---- Orders ----
$orderNumCol = gs_pick_col($conn, 'orders', ['order_number']);
if (hasPermission('orders.view')) {
    $items = [];
    if ($orderNumCol) {
        $stmt = $conn->prepare("SELECT id, $orderNumCol AS order_number, order_status FROM orders WHERE $orderNumCol LIKE ? ORDER BY id DESC LIMIT 6");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $items[] = ['label' => $r['order_number'] . ' — ' . ucfirst($r['order_status'] ?? ''), 'url' => 'invoice.php?order_id=' . (int)$r['id']];
        }
    }
    if ($items) { $results['Orders'] = $items; }
}

// ---- Invoices (seller commission invoices) + Transactions ----
if (hasPermission('finance.view')) {
    $items = [];
    if (gs_col_exists($conn, 'seller_invoices', 'invoice_number')) {
        $stmt = $conn->prepare("SELECT id, invoice_number FROM seller_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 6");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $items[] = ['label' => $r['invoice_number'], 'url' => 'seller_invoices.php?highlight=' . (int)$r['id']];
        }
    }
    if ($items) { $results['Invoices'] = $items; }

    $txnItems = [];
    if ($orderNumCol) {
        $stmt2 = $conn->prepare("SELECT id, $orderNumCol AS order_number FROM orders WHERE $orderNumCol LIKE ? ORDER BY id DESC LIMIT 4");
        $stmt2->bind_param('s', $like);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $txnItems[] = ['label' => 'ORD-' . $r['order_number'], 'url' => 'finance_center.php?range=all&q=' . urlencode($r['order_number'])];
        }
    }
    if ($txnItems) { $results['Transactions'] = $txnItems; }
}

// ---- Payouts ----
if (hasPermission('finance.payout')) {
    $items = [];
    if (is_numeric($q)) {
        $stmt = $conn->prepare("SELECT id, amount, status FROM payouts WHERE id = ? LIMIT 3");
        $idParam = (int)$q;
        $stmt->bind_param('i', $idParam);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $items[] = ['label' => 'Payout #' . $r['id'] . ' — ₹' . number_format((float)$r['amount'], 2) . ' (' . ucfirst($r['status']) . ')', 'url' => 'seller_payouts.php?highlight=' . (int)$r['id']];
        }
    }
    if ($items) { $results['Payouts'] = $items; }
}

// ---- GSTIN ----
if (hasPermission('accounts.verify') || hasPermission('companies.view')) {
    $items = [];
    if (gs_col_exists($conn, 'gst_verification_requests', 'gstin')) {
        $stmt = $conn->prepare("SELECT id, business_name, gstin, status FROM gst_verification_requests WHERE gstin LIKE ? ORDER BY id DESC LIMIT 6");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $items[] = ['label' => $r['gstin'] . ' — ' . $r['business_name'] . ' (' . ucfirst($r['status']) . ')', 'url' => 'gst_verification_requests.php?highlight=' . (int)$r['id']];
        }
    }
    if ($items) { $results['GSTIN'] = $items; }
}

echo json_encode(['success' => true, 'results' => $results]);
