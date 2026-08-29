<?php
/**
 * AgriCart — Farmer equipment-for-rent submission
 *
 * Lets a logged-in farmer list their OWN tractor/harvester/drone/pump etc.
 * for other farmers to rent. Every self-listed item:
 *   - is tied to the farmer's user account (owner_user_id)
 *   - starts as approval_status = 'pending' until Admin reviews it
 *   - carries commission_percent — AgriCart's platform charge on every
 *     booking, shown to the farmer up front (Amazon/Flipkart-style seller fee)
 *
 * Also supports: multiple equipment photos + documents (securely uploaded,
 * validated, unique filenames), full rental/availability/owner details,
 * and automatic EN/MR/HI translation of the equipment name (see
 * includes/agri_translate.php). The name the owner actually typed is
 * preserved untouched in name_original; a translation failure never
 * blocks the listing — the original name is always saved and translation
 * can be retried later (e.g. by re-saving from the Admin Panel).
 *
 * SECURITY: prepared statements throughout. CSRF token required. Nothing
 * from hidden fields is trusted — validation and translation are redone
 * on the server regardless of what the client sent.
 */
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
include __DIR__ . '/../includes/agri_translate.php';
include __DIR__ . '/../includes/commission_schema.php';

// Resolved dynamically (seller override -> category override -> admin
// default) via includes/commission_schema.php — see admin/commission.php.
// Equipment rentals use the pseudo-category "equipment_rental" so admins
// can set a different default for rentals vs. produce; a category-override
// row seeded below preserves the previous fixed 10% until an admin changes it.
define('AGRI_EQUIPMENT_MAX_IMAGE_BYTES', 5 * 1024 * 1024);
define('AGRI_EQUIPMENT_MAX_IMAGES', 6);
define('AGRI_EQUIPMENT_MAX_DOC_BYTES', 8 * 1024 * 1024);
define('AGRI_EQUIPMENT_MAX_DOCS', 4);
define('AGRI_EQUIPMENT_UPLOAD_DIR', __DIR__ . '/../assets/uploads/equipment');
define('AGRI_EQUIPMENT_UPLOAD_WEB_PATH', 'assets/uploads/equipment');
define('AGRI_EQUIPMENT_DOC_UPLOAD_DIR', __DIR__ . '/../assets/uploads/equipment_docs');
define('AGRI_EQUIPMENT_DOC_UPLOAD_WEB_PATH', 'assets/uploads/equipment_docs');

$isAjax = (($_POST['ajax'] ?? '') === '1') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function agri_eq_fail($msg, $isAjax = false) {
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

if (!isset($_POST['add_equipment'])) {
    header("Location: rental.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) { agri_eq_fail('login_required', true); }
    header("Location: login.php");
    exit();
}

// CSRF check
$postedCsrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
    agri_eq_fail('Your session has expired. Please reload the page and try again.', $isAjax);
}

$rawName     = trim($_POST['name'] ?? '');
$type        = trim($_POST['type'] ?? 'other');
$brand       = trim($_POST['brand'] ?? '');
$model       = trim($_POST['model'] ?? '');
$yearRaw     = trim($_POST['manufacturing_year'] ?? '');
$hp          = trim($_POST['hp'] ?? '');
$condition   = trim($_POST['equipment_condition'] ?? 'good');
$description = trim($_POST['description'] ?? '');

$rentType    = trim($_POST['rent_type'] ?? 'day');
$rentPriceRaw= (float)($_POST['rent_price'] ?? 0);
$securityDeposit = (float)($_POST['security_deposit'] ?? 0);
$minRentalDuration = trim($_POST['min_rental_duration'] ?? '');
$operatorAvailable = (($_POST['operator_available'] ?? 'no') === 'yes') ? 1 : 0;
$fuelIncluded      = (($_POST['fuel_included'] ?? 'no') === 'yes') ? 1 : 0;
$transportAvailable= (($_POST['transport_available'] ?? 'no') === 'yes') ? 1 : 0;
$transportCharge   = (float)($_POST['transport_charge'] ?? 0);

$availableFrom = trim($_POST['available_from'] ?? '');
$availableTo   = trim($_POST['available_to'] ?? '');
$availableDaysArr = isset($_POST['available_days']) && is_array($_POST['available_days']) ? $_POST['available_days'] : [];
$allowedDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$availableDaysArr = array_values(array_intersect($availableDaysArr, $allowedDays));
$availableDaysStr = implode(',', $availableDaysArr);
$bookingNoticePeriod = trim($_POST['booking_notice_period'] ?? '');

$cityName    = trim($_POST['city'] ?? '');
$ownerPhone  = trim($_POST['owner_phone'] ?? '');
$ownerEmail  = trim($_POST['owner_email'] ?? '');
$ownerDistrict = trim($_POST['owner_district'] ?? '');
$ownerAddress  = trim($_POST['owner_address'] ?? '');
$rentalRules   = trim($_POST['rental_rules'] ?? '');

// ---- Required-field validation (server-side, never trust JS alone) ----
if ($rawName === '' || $cityName === '' || $ownerDistrict === '' || $ownerAddress === '' || $ownerPhone === '') {
    agri_eq_fail('Equipment name, owner village/city, district, address and contact number are required.', $isAjax);
}
if ($rentPriceRaw <= 0) {
    agri_eq_fail('Enter a valid rental price.', $isAjax);
}
if (!preg_match('/^[0-9]{10}$/', $ownerPhone)) {
    agri_eq_fail('Enter a valid 10-digit mobile number.', $isAjax);
}
if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    agri_eq_fail('Enter a valid email address.', $isAjax);
}
$manufacturingYear = null;
if ($yearRaw !== '') {
    $yearInt = (int)$yearRaw;
    if ($yearInt < 1980 || $yearInt > (int)date('Y')) {
        agri_eq_fail('Enter a valid manufacturing year.', $isAjax);
    }
    $manufacturingYear = $yearInt;
}
if ($availableFrom !== '' && $availableTo !== '' && $availableTo < $availableFrom) {
    agri_eq_fail('Available To date must be after Available From date.', $isAjax);
}
if (!in_array($condition, ['excellent', 'good', 'average'], true)) { $condition = 'good'; }
if (!in_array($rentType, ['hour', 'day', 'acre'], true)) { $rentType = 'day'; }
if (empty($_POST['terms'])) {
    agri_eq_fail('You must accept the Terms and Conditions.', $isAjax);
}

// Rent price applies to whichever rent_type was chosen; the other two
// price columns stay null so rental.php/admin can tell which one is live.
$rentPerDay = $rentType === 'day' ? $rentPriceRaw : null;
$rentPerHour = $rentType === 'hour' ? $rentPriceRaw : null;
$rentPerAcre = $rentType === 'acre' ? $rentPriceRaw : null;

$ownerUserId = (int)$_SESSION['user_id'];
$ownerName   = trim($_POST['owner_name'] ?? '') ?: ($_SESSION['user_name'] ?? 'AgriCart Farmer');
$commissionPercent = agri_resolve_commission_percent($conn, 'equipment_rental', $ownerUserId);

// ---- Translation: server is the ONLY source of truth for the raw name
//      and language — client-supplied translated values are ignored. ----
$nameOriginal = $rawName;
$requestedLang = trim($_POST['input_language'] ?? 'auto');
if (!in_array($requestedLang, ['auto', 'en', 'mr', 'hi'], true)) { $requestedLang = 'auto'; }
try {
    $originalLanguage = $requestedLang === 'auto' ? agri_detect_language($rawName) : $requestedLang;
    $translated = agri_translate_product_name($rawName, $requestedLang);
    $nameEn = $translated['en']; $nameMr = $translated['mr']; $nameHi = $translated['hi'];
} catch (\Throwable $eTranslate) {
    // Translation failure must never block submission — save the original
    // name in every language; it can be retried later from Admin.
    $originalLanguage = $originalLanguage ?? 'en';
    $nameEn = $rawName; $nameMr = $rawName; $nameHi = $rawName;
}
if ($nameEn === '' || $nameEn === null) { $nameEn = $rawName; }
if ($nameMr === '' || $nameMr === null) { $nameMr = $rawName; }
if ($nameHi === '' || $nameHi === null) { $nameHi = $rawName; }
$name = htmlspecialchars($nameEn);
$nameMr = htmlspecialchars($nameMr);
$nameHi = htmlspecialchars($nameHi);

// Resolve city name to a cities.id, creating the city if it doesn't exist yet.
$cityId = null;
if ($cityName !== '') {
    $stmt = $conn->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $cityName);
    $stmt->execute();
    $cityRow = $stmt->get_result()->fetch_assoc();
    if ($cityRow) {
        $cityId = (int)$cityRow['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO cities (name) VALUES (?)");
        $stmt->bind_param("s", $cityName);
        if ($stmt->execute()) { $cityId = $conn->insert_id; }
    }
}

// ---- Secure multi-image upload ----
$uploadedImagePaths = [];
if (!is_dir(AGRI_EQUIPMENT_UPLOAD_DIR)) { @mkdir(AGRI_EQUIPMENT_UPLOAD_DIR, 0755, true); }
$allowedImageMimes = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$imgFiles = $_FILES['images'] ?? null;
if ($imgFiles && isset($imgFiles['name']) && is_array($imgFiles['name'])) {
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    for ($i = 0; $i < count($imgFiles['name']) && count($uploadedImagePaths) < AGRI_EQUIPMENT_MAX_IMAGES; $i++) {
        if (($imgFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
        if (($imgFiles['error'][$i] ?? 1) !== UPLOAD_ERR_OK) { continue; }
        $tmpPath = $imgFiles['tmp_name'][$i];
        if (!is_uploaded_file($tmpPath)) { continue; }
        if (($imgFiles['size'][$i] ?? 0) > AGRI_EQUIPMENT_MAX_IMAGE_BYTES) { continue; }
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : ($imgFiles['type'][$i] ?? '');
        if (!isset($allowedImageMimes[$mime])) { continue; }
        $uniqueName = 'equip_' . bin2hex(random_bytes(16)) . '.' . $allowedImageMimes[$mime];
        $destPath = AGRI_EQUIPMENT_UPLOAD_DIR . '/' . $uniqueName;
        if (move_uploaded_file($tmpPath, $destPath)) {
            $uploadedImagePaths[] = AGRI_EQUIPMENT_UPLOAD_WEB_PATH . '/' . $uniqueName;
        }
    }
    if ($finfo) { finfo_close($finfo); }
}
if (empty($uploadedImagePaths)) {
    agri_eq_fail('Please upload at least one valid equipment image (JPG, JPEG, PNG, or WEBP, up to 5 MB).', $isAjax);
}
$image = $uploadedImagePaths[0];

// ---- Secure document upload (RC book, insurance, etc. — optional) ----
$uploadedDocPaths = []; // [ 'path' => ..., 'name' => original filename ]
if (!is_dir(AGRI_EQUIPMENT_DOC_UPLOAD_DIR)) { @mkdir(AGRI_EQUIPMENT_DOC_UPLOAD_DIR, 0755, true); }
$allowedDocMimes = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png'];
$docFiles = $_FILES['documents'] ?? null;
if ($docFiles && isset($docFiles['name']) && is_array($docFiles['name'])) {
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    for ($i = 0; $i < count($docFiles['name']) && count($uploadedDocPaths) < AGRI_EQUIPMENT_MAX_DOCS; $i++) {
        if (($docFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
        if (($docFiles['error'][$i] ?? 1) !== UPLOAD_ERR_OK) { continue; }
        $tmpPath = $docFiles['tmp_name'][$i];
        if (!is_uploaded_file($tmpPath)) { continue; }
        if (($docFiles['size'][$i] ?? 0) > AGRI_EQUIPMENT_MAX_DOC_BYTES) { continue; }
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : ($docFiles['type'][$i] ?? '');
        if (!isset($allowedDocMimes[$mime])) { continue; }
        $uniqueName = 'doc_' . bin2hex(random_bytes(16)) . '.' . $allowedDocMimes[$mime];
        $destPath = AGRI_EQUIPMENT_DOC_UPLOAD_DIR . '/' . $uniqueName;
        if (move_uploaded_file($tmpPath, $destPath)) {
            $uploadedDocPaths[] = [
                'path' => AGRI_EQUIPMENT_DOC_UPLOAD_WEB_PATH . '/' . $uniqueName,
                'name' => trim($docFiles['name'][$i]) ?: $uniqueName,
            ];
        }
    }
    if ($finfo) { finfo_close($finfo); }
}
// Documents are optional — no failure if none were uploaded.

// Try the richest insert first (all new translation/rental/availability/
// owner columns). If any don't exist yet on this database
// (setup/list_equipment_upgrade.sql hasn't been run), fall back
// progressively so nothing breaks — same defensive pattern the rest of
// AgriCart's insert files already use.
$ok = false;
$newEquipmentId = null;

try {
    $stmt = $conn->prepare(
        "INSERT INTO equipment
            (name, name_mr, name_hi, name_original, original_language, type, brand, model, manufacturing_year, hp,
             equipment_condition, description, rent_type, rent_per_day, rent_per_hour, rent_per_acre,
             security_deposit, min_rental_duration, operator_available, fuel_included, transport_available, transport_charge,
             available_from, available_to, available_days, booking_notice_period,
             owner_name, owner_phone, owner_email, owner_village, owner_district, owner_address, rental_rules,
             owner_user_id, city_id, availability, image, approval_status, commission_percent, owner_verified)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,'pending',?,0)"
    );
    $availableFromParam = $availableFrom !== '' ? $availableFrom : null;
    $availableToParam = $availableTo !== '' ? $availableTo : null;
    $stmt->bind_param(
        "ssssssssissssddddsiiidsssssssssssiisd",
        $name, $nameMr, $nameHi, $nameOriginal, $originalLanguage, $type, $brand, $model, $manufacturingYear, $hp,
        $condition, $description, $rentType, $rentPerDay, $rentPerHour, $rentPerAcre,
        $securityDeposit, $minRentalDuration, $operatorAvailable, $fuelIncluded, $transportAvailable, $transportCharge,
        $availableFromParam, $availableToParam, $availableDaysStr, $bookingNoticePeriod,
        $ownerName, $ownerPhone, $ownerEmail, $cityName, $ownerDistrict, $ownerAddress, $rentalRules,
        $ownerUserId, $cityId, $image, $commissionPercent
    );
    $ok = $stmt->execute();
    if ($ok) { $newEquipmentId = $stmt->insert_id ?: $conn->insert_id; }
} catch (\Throwable $e) {
    $ok = false;
}

if (!$ok) {
    // Fallback: original farmer-listing column set only (no new columns
    // on this database yet).
    try {
        $stmt = $conn->prepare(
            "INSERT INTO equipment (name, name_mr, type, rent_per_day, hp, owner_name, owner_phone, owner_user_id, city_id, availability, image, description, approval_status, commission_percent, owner_verified)
             VALUES (?,?,?,?,?,?,?,?,?,1,?,?,'pending',?,0)"
        );
        $fallbackRentPerDay = $rentPerDay !== null ? $rentPerDay : $rentPriceRaw;
        $stmt->bind_param(
            "sssdsssiissd",
            $name, $nameMr, $type, $fallbackRentPerDay, $hp, $ownerName, $ownerPhone, $ownerUserId, $cityId, $image, $description, $commissionPercent
        );
        $ok = $stmt->execute();
        if ($ok) { $newEquipmentId = $stmt->insert_id ?: $conn->insert_id; }
    } catch (\Throwable $e2) {
        $ok = false;
    }
}

if (!$ok) {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO equipment (name, name_mr, type, rent_per_day, hp, owner_name, owner_phone, city_id, availability, image, description, owner_verified)
             VALUES (?,?,?,?,?,?,?,?,1,?,?,0)"
        );
        $fallbackRentPerDay = $rentPerDay !== null ? $rentPerDay : $rentPriceRaw;
        $stmt->bind_param(
            "sssdsssiss",
            $name, $nameMr, $type, $fallbackRentPerDay, $hp, $ownerName, $ownerPhone, $cityId, $image, $description
        );
        $ok = $stmt->execute();
        if ($ok) { $newEquipmentId = $stmt->insert_id ?: $conn->insert_id; }
    } catch (\Throwable $e3) {
        $ok = false;
    }
}

if ($ok) {
    if ($newEquipmentId) {
        if (!empty($uploadedImagePaths)) {
            try {
                $imgStmt = $conn->prepare("INSERT INTO equipment_images (equipment_id, image_path, sort_order) VALUES (?, ?, ?)");
                foreach ($uploadedImagePaths as $idx => $path) {
                    $imgStmt->bind_param("isi", $newEquipmentId, $path, $idx);
                    $imgStmt->execute();
                }
            } catch (\Throwable $eImg) {
                // equipment_images table not created yet — the cover photo
                // is still safely stored on equipment.image.
            }
        }
        if (!empty($uploadedDocPaths)) {
            try {
                $docStmt = $conn->prepare("INSERT INTO equipment_documents (equipment_id, doc_path, doc_name) VALUES (?, ?, ?)");
                foreach ($uploadedDocPaths as $doc) {
                    $docStmt->bind_param("iss", $newEquipmentId, $doc['path'], $doc['name']);
                    $docStmt->execute();
                }
            } catch (\Throwable $eDoc) {
                // equipment_documents table not created yet — documents are
                // optional, so this is a safe no-op.
            }
        }
        // Best-effort Marathi autofill fallback, same as the original file.
        if (function_exists('agri_autofill_name_mr') && $nameMr === $nameOriginal) {
            agri_autofill_name_mr($conn, 'equipment', $newEquipmentId, $name);
        }
        // Same last-resort fallback for Hindi, so Hindi listings get the
        // same second chance at translation that Marathi already had.
        if (function_exists('agri_autofill_name_hi') && $nameHi === $nameOriginal) {
            agri_autofill_name_hi($conn, 'equipment', $newEquipmentId, $name);
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $newEquipmentId, 'commission' => $commissionPercent]);
        exit();
    }
    header("Location: my_activity.php?listed=equipment&commission=" . $commissionPercent);
    exit();
} else {
    error_log('insert_equipment.php: equipment INSERT failed: ' . ($conn->error ?: 'unknown'));
    agri_eq_fail('Could not save your equipment listing right now. Please try again.', $isAjax);
}
