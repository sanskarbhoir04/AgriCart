<?php
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
$result = mysqli_query($conn, "SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 500");
$products = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}
echo json_encode($products);
?>