<?php
/**
 * AgriCart — Centralized Catalog Router
 * Adds a product from either the Store (admin) or Farmer's own listing flow.
 *
 * Farmer self-listings (source = 'farmer') are tied to the logged-in
 * farmer's account, start out as approval_status = 'pending' so an admin
 * can review them before they appear on the Marketplace, and carry the
 * platform's commission_percent so the farmer knows AgriCart's cut
 * up front — the same "seller fee" idea Amazon/Flipkart use.
 *
 * Farmer listings also support: multiple product images (securely
 * uploaded, validated, and stored with unique filenames), seller contact
 * details, product condition/brand/delivery-available, and automatic
 * EN/MR/HI translation of the product name (see includes/agri_translate.php).
 * The name the seller actually typed is preserved untouched in
 * `name_original` — translation never overwrites it, and a translation
 * failure never blocks the listing from being saved.
 *
 * SECURITY: uses prepared statements throughout — no raw SQL concatenation.
 * CSRF token is required and checked for farmer submissions. Nothing from
 * hidden fields (translations, computed language) is trusted — the server
 * recomputes them itself from the raw product name.
 */
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
include __DIR__ . '/../includes/agri_translate.php';
include __DIR__ . '/../includes/commission_schema.php';
require_once __DIR__ . '/../includes/gst_sync.php';

// Platform commission charged to farmers who list their own produce.
// Resolved dynamically per listing (seller override -> category override ->
// admin-configured global default) via includes/commission_schema.php —
// see admin/commission.php to change it. No longer a hardcoded constant.
define('AGRI_PRODUCT_MAX_IMAGE_BYTES', 5 * 1024 * 1024); // 5 MB
define('AGRI_PRODUCT_MAX_IMAGES', 5);
define('AGRI_PRODUCT_UPLOAD_DIR', __DIR__ . '/../assets/uploads/products');
define('AGRI_PRODUCT_UPLOAD_WEB_PATH', 'assets/uploads/products');

$isAjax = (($_POST['ajax'] ?? '') === '1') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function agri_fail($msg, $isAjax = false) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    die("<div style='padding:20px; background:#ffebee; color:#c62828; font-family:sans-serif;'>
            <h3>⚠️ " . htmlspecialchars($msg) . "</h3>
            <a href='javascript:history.back()'>Go Back</a>
         </div>");
}

if (isset($_POST['add_product'])) {
    $name       = htmlspecialchars(trim($_POST['p_name'] ?? ''));
    $price      = (float)($_POST['p_price'] ?? 0);
    $category   = trim($_POST['p_category'] ?? '');
    $source     = trim($_POST['p_source'] ?? 'store');
    $unit       = trim($_POST['p_unit'] ?? '1 pc');
    $stock      = (int)($_POST['p_stock'] ?? 0);
    $image      = trim($_POST['p_image'] ?? ''); // legacy image-URL path (store flow)
    $description = trim($_POST['p_description'] ?? '');
    $farmerPhone = trim($_POST['p_phone'] ?? '');

    if ($name === '' || $price <= 0 || $category === '') {
        agri_fail('Product name, price, and category are required.', $isAjax);
    }
    if ($source === 'farmer' && $farmerPhone === '') {
        agri_fail('A contact number is required so buyers/admin can reach you.', $isAjax);
    }

    $addedByUserId = null;
    $approvalStatus = 'approved';
    $commissionPercent = 0.00;
    $farmerName = trim($_POST['p_farmer'] ?? 'AgriCart Logistics');

    // ---- Farmer-only fields (product_condition, translations, uploads, etc.) ----
    $nameOriginal = null; $originalLanguage = null; $nameEn = null; $nameMr = null; $nameHi = null;
    $brand = null; $condition = 'new'; $deliveryAvailable = 0;
    $sellerEmail = null; $sellerVillage = null; $sellerDistrict = null; $sellerAddress = null;
    $uploadedImagePaths = [];

    if ($source === 'farmer') {
        // Farmer self-listings must be logged in — the item is attributed
        // to their account and always goes to Admin for approval first.
        if (!isset($_SESSION['user_id'])) {
            if ($isAjax) { agri_fail('login_required', true); }
            header("Location: login.php");
            exit();
        }

        // CSRF check (farmer flow only — the form always includes this token).
        $postedCsrf = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
            agri_fail('Your session has expired. Please reload the page and try again.', $isAjax);
        }

        $addedByUserId = (int)$_SESSION['user_id'];
        $farmerName = $_SESSION['user_name'] ?? $farmerName;
        $approvalStatus = 'pending';
        $commissionPercent = agri_resolve_commission_percent($conn, $category, $addedByUserId);

        // --- Required seller-details / product-details fields (never trust
        //     hidden fields or client-side-only validation) ---
        $sellerEmail    = trim($_POST['p_email'] ?? '');
        $sellerVillage  = trim($_POST['p_village'] ?? '');
        $sellerDistrict = trim($_POST['p_district'] ?? '');
        $sellerAddress  = trim($_POST['p_address'] ?? '');
        $brand          = trim($_POST['p_brand'] ?? '');
        $condition      = (($_POST['p_condition'] ?? 'new') === 'used') ? 'used' : 'new';
        $deliveryAvailable = (($_POST['p_delivery'] ?? 'yes') === 'no') ? 0 : 1;

        if ($sellerVillage === '' || $sellerDistrict === '' || $sellerAddress === '') {
            agri_fail('Village/City, District, and Full Address are required.', $isAjax);
        }
        if (!preg_match('/^[0-9]{10}$/', $farmerPhone)) {
            agri_fail('Enter a valid 10-digit mobile number.', $isAjax);
        }
        if ($sellerEmail !== '' && !filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
            agri_fail('Enter a valid email address.', $isAjax);
        }
        if ($price <= 0 || $stock < 0) {
            agri_fail('Enter a valid price and quantity.', $isAjax);
        }
        if (empty($_POST['p_terms'])) {
            agri_fail('You must accept the Terms and Conditions.', $isAjax);
        }

        $allowedUnits = ['Kg', 'Quintal', 'Litre', 'Packet', 'Piece', 'Dozen'];
        if (!in_array($unit, $allowedUnits, true)) { $unit = 'Piece'; }

        // --- Translation: the ONLY source of truth for the raw name and
        //     input-language is what the seller actually typed / picked
        //     here on the server — client-supplied translated values (if
        //     any were ever sent) are ignored entirely. ---
        $rawName = trim($_POST['p_name'] ?? '');
        $nameOriginal = $rawName;
        $requestedLang = trim($_POST['p_input_language'] ?? 'auto');
        if (!in_array($requestedLang, ['auto', 'en', 'mr', 'hi'], true)) { $requestedLang = 'auto'; }

        try {
            $originalLanguage = $requestedLang === 'auto' ? agri_detect_language($rawName) : $requestedLang;
            $translated = agri_translate_product_name($rawName, $requestedLang);
            $nameEn = $translated['en'];
            $nameMr = $translated['mr'];
            $nameHi = $translated['hi'];
        } catch (\Throwable $eTranslate) {
            // Translation failure must never block submission — fall back
            // to the original text in every language.
            $originalLanguage = $originalLanguage ?: 'en';
            $nameEn = $rawName; $nameMr = $rawName; $nameHi = $rawName;
        }
        if ($nameEn === '' || $nameEn === null) { $nameEn = $rawName; }
        if ($nameMr === '' || $nameMr === null) { $nameMr = $rawName; }
        if ($nameHi === '' || $nameHi === null) { $nameHi = $rawName; }
        // `name` stays the site's canonical English-ish display column
        // (same column already used everywhere else in the app).
        $name = htmlspecialchars($nameEn);

        // --- Secure multi-image upload ---
        if (!is_dir(AGRI_PRODUCT_UPLOAD_DIR)) {
            @mkdir(AGRI_PRODUCT_UPLOAD_DIR, 0755, true);
        }
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $filesArr = $_FILES['p_images'] ?? null;
        if ($filesArr && isset($filesArr['name']) && is_array($filesArr['name'])) {
            $count = count($filesArr['name']);
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            for ($i = 0; $i < $count && count($uploadedImagePaths) < AGRI_PRODUCT_MAX_IMAGES; $i++) {
                if (($filesArr['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
                if (($filesArr['error'][$i] ?? 1) !== UPLOAD_ERR_OK) { continue; } // skip failed uploads silently
                $tmpPath = $filesArr['tmp_name'][$i];
                if (!is_uploaded_file($tmpPath)) { continue; }
                if (($filesArr['size'][$i] ?? 0) > AGRI_PRODUCT_MAX_IMAGE_BYTES) { continue; } // oversized: skip
                $mime = $finfo ? finfo_file($finfo, $tmpPath) : ($filesArr['type'][$i] ?? '');
                if (!isset($allowedMimes[$mime])) { continue; } // wrong type: skip
                $ext = $allowedMimes[$mime];
                $uniqueName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $destPath = AGRI_PRODUCT_UPLOAD_DIR . '/' . $uniqueName;
                if (move_uploaded_file($tmpPath, $destPath)) {
                    $uploadedImagePaths[] = AGRI_PRODUCT_UPLOAD_WEB_PATH . '/' . $uniqueName;
                }
            }
            if ($finfo) { finfo_close($finfo); }
        }
        if (empty($uploadedImagePaths)) {
            agri_fail('Please upload at least one valid product image (JPG, JPEG, PNG, or WEBP, up to 5 MB).', $isAjax);
        }
        $image = $uploadedImagePaths[0]; // keep `products.image` in sync for pages that only read that column
    }

    // Try the richest insert first (all farmer-selling + translation +
    // seller-detail columns). If any of those columns don't exist yet on
    // this database (setup/sell_product_upgrade.sql hasn't been run),
    // fall back progressively so nothing breaks — same defensive pattern
    // this file already used for farmer_phone/approval_status.
    $stmt = null;
    $ok = false;
    $newProductId = null;

    if ($source === 'farmer') {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO products
                    (name, name_mr, name_hi, name_original, original_language, price, category, source,
                     farmer_name, farmer_phone, unit, stock, image, description, added_by_user_id,
                     approval_status, commission_percent, brand, product_condition, delivery_available,
                     seller_email, seller_village, seller_district, seller_address)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                "sssssdsssssissisdssissss",
                $name, $nameMr, $nameHi, $nameOriginal, $originalLanguage, $price, $category, $source,
                $farmerName, $farmerPhone, $unit, $stock, $image, $description, $addedByUserId,
                $approvalStatus, $commissionPercent, $brand, $condition, $deliveryAvailable,
                $sellerEmail, $sellerVillage, $sellerDistrict, $sellerAddress
            );
            $ok = $stmt->execute();
            if ($ok) { $newProductId = $stmt->insert_id ?: $conn->insert_id; }
        } catch (\Throwable $e) {
            $ok = false;
        }
    }

    if (!$ok) {
        // Fallback: original farmer-selling column set (no translation /
        // seller-detail columns yet on this database).
        try {
            $stmt = $conn->prepare(
                "INSERT INTO products (name, price, category, source, farmer_name, farmer_phone, unit, stock, image, description, added_by_user_id, approval_status, commission_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sdsssssissisd",
                $name, $price, $category, $source, $farmerName, $farmerPhone, $unit, $stock, $image, $description, $addedByUserId, $approvalStatus, $commissionPercent
            );
            $ok = $stmt->execute();
            if ($ok) { $newProductId = $stmt->insert_id ?: $conn->insert_id; }
        } catch (\Throwable $e) {
            $ok = false;
        }
    }

    if (!$ok) {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO products (name, price, category, source, farmer_name, unit, stock, image, description, added_by_user_id, approval_status, commission_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sdssssissisd",
                $name, $price, $category, $source, $farmerName, $unit, $stock, $image, $description, $addedByUserId, $approvalStatus, $commissionPercent
            );
            $ok = $stmt->execute();
            if ($ok) { $newProductId = $stmt->insert_id ?: $conn->insert_id; }
        } catch (\Throwable $e) {
            $ok = false;
        }
    }

    if (!$ok) {
        $stmt = $conn->prepare(
            "INSERT INTO products (name, price, category, source, farmer_name) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sdsss", $name, $price, $category, $source, $farmerName);
        $ok = $stmt->execute();
        if ($ok) { $newProductId = $stmt->insert_id ?: $conn->insert_id; }
    }

    if ($ok) {
        // Auto-link this product to its Company/Seller master record (spec
        // §5 "Company → Seller → Product... automatically available wherever
        // required" — no manual re-entry). If this farmer's account is
        // linked to a `sellers` row (Admin > Companies), stamp seller_id on
        // the new product so invoices/reports resolve the company via a
        // real foreign key instead of falling back to farmer_name string
        // matching. Silently skipped if this farmer has no company record
        // (the common case for casual/unregistered listings) or if the
        // seller_id/linked_user_id columns don't exist on this install yet.
        if ($newProductId && function_exists('gst_sync_resolve_company_id')) {
            $linkedSellerId = gst_sync_resolve_company_id($conn, $addedByUserId);
            if ($linkedSellerId) {
                try {
                    $u = $conn->prepare("UPDATE products SET seller_id = ? WHERE id = ?");
                    $u->bind_param('ii', $linkedSellerId, $newProductId);
                    $u->execute();
                } catch (\Throwable $eLink) {
                    // seller_id column not present on this install — the
                    // farmer_name fallback join still works fine.
                }
            }
        }

        // Save any additional uploaded images (beyond the first, which is
        // already mirrored into products.image) into product_images.
        if ($source === 'farmer' && $newProductId && !empty($uploadedImagePaths)) {
            try {
                $imgStmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                foreach ($uploadedImagePaths as $idx => $path) {
                    $imgStmt->bind_param("isi", $newProductId, $path, $idx);
                    $imgStmt->execute();
                }
            } catch (\Throwable $eImg) {
                // product_images table not created yet — the first image is
                // still safely stored on products.image, so nothing is lost.
            }
        }

        if ($source === 'farmer') {
            // Best-effort: fill in the Marathi name automatically so the listing
            // still reads correctly when a buyer has the site set to मराठी,
            // even though the farmer only typed the name in one language.
            // (Only used as a further fallback if our own translator above
            // somehow left name_mr identical to the raw original.)
            if ($newProductId && function_exists('agri_autofill_name_mr') && $nameMr === $nameOriginal) {
                agri_autofill_name_mr($conn, 'products', $newProductId, $name);
            }
            if ($newProductId && function_exists('agri_autofill_name_hi') && $nameHi === $nameOriginal) {
                agri_autofill_name_hi($conn, 'products', $newProductId, $name);
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $newProductId, 'commission' => $commissionPercent]);
                exit();
            }
            // Non-JS fallback: send the farmer to their activity page with a
            // friendly note that the item is pending admin review.
            header("Location: my_activity.php?listed=product&commission=" . $commissionPercent);
        } elseif ($source === 'farmer_bazaar') {
            header("Location: krishi_bazaar.php");
        } else {
            header("Location: marketplace.php");
        }
        exit();
    } else {
        agri_fail('Could not save product. Error: ' . ($stmt ? $stmt->error : 'unknown'), $isAjax);
    }
} else {
    // If accessed directly without a form submission, bounce safely to core index.
    header("Location: index.php");
    exit();
}
