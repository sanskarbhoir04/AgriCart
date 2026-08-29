<?php
// =====================================================================
// seller/export_statement.php — downloads the logged-in seller's
// earnings ledger as a CSV file ("Download Statement" button).
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/seller_functions.php';

$sellerId = agri_seller_require_login(); // returns JSON+exits if not logged in; fine here too since no HTML sent yet

// Language comes from the dashboard's own selector via ?lang= (see
// sdSyncExportStatementLink() in dashboard.js) since this is a plain file
// download, not an API call that can read localStorage. Same three
// languages as the rest of the seller dashboard (SD_T in dashboard.js).
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, ['en', 'mr', 'hi'], true)) $lang = 'en';

$L = [
    'en' => [
        'headers' => ['Order Item ID', 'Product', 'Gross Amount (₹)', 'Platform Charge (₹)', 'Net Amount (₹)', 'Status', 'Date'],
        'status' => ['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'paid' => 'Paid', 'rejected' => 'Rejected', 'refunded' => 'Refunded'],
        'na' => '-',
        'filename' => 'agricart_statement_',
    ],
    'mr' => [
        'headers' => ['ऑर्डर आयटम आयडी', 'उत्पादन', 'एकूण रक्कम (₹)', 'प्लॅटफॉर्म शुल्क (₹)', 'निव्वळ रक्कम (₹)', 'स्थिती', 'दिनांक'],
        'status' => ['pending' => 'प्रलंबित', 'processing' => 'प्रक्रियेत', 'completed' => 'पूर्ण', 'paid' => 'भरले', 'rejected' => 'नाकारले', 'refunded' => 'परत केले'],
        'na' => '-',
        'filename' => 'agricart_vivaran_',
    ],
    'hi' => [
        'headers' => ['ऑर्डर आइटम आईडी', 'उत्पाद', 'सकल राशि (₹)', 'प्लेटफॉर्म शुल्क (₹)', 'शुद्ध राशि (₹)', 'स्थिति', 'दिनांक'],
        'status' => ['pending' => 'लंबित', 'processing' => 'प्रक्रिया में', 'completed' => 'पूर्ण', 'paid' => 'भुगतान किया गया', 'rejected' => 'अस्वीकृत', 'refunded' => 'वापस किया गया'],
        'na' => '-',
        'filename' => 'agricart_vivaran_',
    ],
][$lang];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $L['filename'] . date('Ymd_His') . '.csv"');
// UTF-8 BOM so Marathi/Hindi text displays correctly when the CSV is
// opened directly in Excel, instead of showing as mojibake.
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, $L['headers']);

try {
    $stmt = $conn->prepare(
        "SELECT se.order_item_id, p.name product_name, p.name_mr product_name_mr, p.name_hi product_name_hi,
                se.gross_amount, se.platform_charge, se.net_amount, se.status, se.created_at
         FROM seller_earnings se
         LEFT JOIN order_items oi ON oi.id = se.order_item_id
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE se.seller_id = ? ORDER BY se.created_at DESC"
    );
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $res = $stmt->get_result();
} catch (\Throwable $e) {
    // name_hi column not present on this install yet — fall back without it.
    $stmt = $conn->prepare(
        "SELECT se.order_item_id, p.name product_name, p.name_mr product_name_mr,
                se.gross_amount, se.platform_charge, se.net_amount, se.status, se.created_at
         FROM seller_earnings se
         LEFT JOIN order_items oi ON oi.id = se.order_item_id
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE se.seller_id = ? ORDER BY se.created_at DESC"
    );
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $res = $stmt->get_result();
}
while ($row = $res->fetch_assoc()) {
    $productName = $row['product_name'] ?? $L['na'];
    if ($lang === 'mr' && !empty($row['product_name_mr'])) $productName = $row['product_name_mr'];
    if ($lang === 'hi' && !empty($row['product_name_hi'])) $productName = $row['product_name_hi'];

    $statusKey = strtolower((string)$row['status']);
    $statusText = $L['status'][$statusKey] ?? ucfirst($row['status']);

    fputcsv($out, [
        $row['order_item_id'], $productName, number_format((float)$row['gross_amount'], 2),
        number_format((float)$row['platform_charge'], 2), number_format((float)$row['net_amount'], 2),
        $statusText, $row['created_at'],
    ]);
}
fclose($out);
