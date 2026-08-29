<?php
// =====================================================================
// pages/mandi.php — Public/customer-facing mandi price JSON endpoint.
// Thin wrapper: all validation, rate limiting, and the actual data.gov.in
// call live in includes/mandi_client.php (shared with admin/mandi.php).
// =====================================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/mandi_client.php';

echo json_encode(agri_mandi_fetch($_GET));
