<?php
// =====================================================================
// seller/dashboard.php — AgriCart Seller Dashboard
//
// Any logged-in user who has listed at least one product is treated as
// a seller automatically (products.added_by_user_id = users.id) — there
// is no separate seller login/signup. Everything on this page is loaded
// live from seller_api.php, which scopes every query to the logged-in
// user; a seller can never see another seller's data.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
require_once __DIR__ . '/../includes/gstin_lib.php';
gstin_bootstrap_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php?next=seller/dashboard.php");
    exit();
}
$sellerId = (int)$_SESSION['user_id'];
$sellerName = $_SESSION['user_name'] ?? 'Seller';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

agri_seller_ensure_profile($conn, $sellerId);

// A user with zero listings yet still sees the dashboard (empty states
// everywhere) with a prominent nudge toward "Add Product" — they become
// a seller the moment their first product is saved.
$stmt = $conn->prepare("SELECT COUNT(*) c FROM products WHERE added_by_user_id = ?");
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$hasProducts = ((int)$stmt->get_result()->fetch_assoc()['c']) > 0;

$eqStmt = $conn->prepare("SELECT COUNT(*) c FROM equipment WHERE owner_user_id = ?");
$eqStmt->bind_param("i", $sellerId);
$eqStmt->execute();
$hasEquipment = ((int)$eqStmt->get_result()->fetch_assoc()['c']) > 0;

// seller_type drives which sections of the dashboard show below:
// 'product' -> only the selling sections, 'rental' -> only the rental
// sections, 'both'/null -> everything (null covers legacy sellers from
// before this preference existed).
$sellerType = agri_seller_get_type($conn, $sellerId);
if ($sellerType === null) {
    if (!$hasProducts && !$hasEquipment) {
        // Brand new seller who never went through the "Become a Seller"
        // form (e.g. followed an old bookmark) — send them there first.
        header('Location: ../pages/become_seller.php');
        exit();
    }
    // Legacy seller with existing listings from before this feature —
    // keep them seeing everything, and persist that so it's stable.
    $sellerType = 'both';
    agri_seller_set_type($conn, $sellerId, 'both');
}
$showProductSections = in_array($sellerType, ['product', 'both'], true);
$showRentalSections   = in_array($sellerType, ['rental', 'both'], true);

// Prefill for the "My Account" section (account_details + change_password).
$accStmt = $conn->prepare("SELECT full_name, mobile, email, village, taluka, district FROM users WHERE id = ? LIMIT 1");
$accStmt->bind_param("i", $sellerId);
$accStmt->execute();
$accountRow = $accStmt->get_result()->fetch_assoc() ?: [];
$accountAddress = trim(implode(', ', array_filter([
    $accountRow['village'] ?? '', $accountRow['taluka'] ?? '', $accountRow['district'] ?? '',
])));

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* Font Awesome + Poppins + the site nav/lang-switcher already come from
   includes/header.php above — only the dashboard's own look lives here. */
:root{
    --sd-dark-green:#123524;
    --sd-green:#3F8F5F;
    --sd-green-light:#EAF4EC;
    --sd-white:#FFFFFF;
    --sd-grey:#FAF8F3;
    --sd-grey-border:#E4E9E0;
    --sd-text:#1c2b20;
    --sd-muted:#7c8a7a;
    --sd-orange:#D98E2B;
    --sd-orange-light:#FBF0DC;
    --sd-danger:#c0392b;
    --sd-danger-light:#FDECEA;
    --sd-radius:18px;
    --sd-shadow:0 4px 16px rgba(20,50,35,.08);
    --sd-ease:cubic-bezier(.22,1,.36,1);
    --sd-teal-bg:#DCE9D4;
    --sd-sidebar-teal:#163C27;
    --sd-sidebar-teal-2:#0D2617;
    --sd-glass-bg:rgba(255,255,255,.14);
    --sd-glass-border:rgba(255,255,255,.14);
    --sd-elevate:0 24px 60px -18px rgba(10,38,25,.35), 0 2px 10px rgba(10,38,25,.06);
    --sd-focus-ring:0 0 0 3px rgba(63,143,95,.28);
    --sd-sidebar-w:258px;
    --sd-sidebar-w-collapsed:78px;
}
*{box-sizing:border-box}
.lang-select{box-sizing:content-box}
html,body{margin:0;padding:0}
.sd-shell{display:flex;font-family:'Poppins',sans-serif;color:var(--sd-text);
    background:#fff;
    padding:0;gap:0;}
.sd-shell-inner{display:flex;flex:1;min-width:0;background:#fff;border-radius:0;box-shadow:none;overflow:hidden;border:none;}
.sd-shell ::-webkit-scrollbar{width:8px;height:8px}
.sd-shell ::-webkit-scrollbar-thumb{background:#c9d6c9;border-radius:8px}
.sd-shell ::-webkit-scrollbar-thumb:hover{background:var(--sd-green)}
:focus-visible{outline:none;box-shadow:var(--sd-focus-ring);border-radius:8px}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms !important;animation-iteration-count:1 !important;transition-duration:.001ms !important}}

/* ---------- Sidebar (mirrors admin/index.php .sidebar) ---------- */
.sd-sidebar{
    width:var(--sd-sidebar-w);
    background:linear-gradient(200deg,var(--sd-sidebar-teal) 0%,var(--sd-sidebar-teal-2) 100%);
    color:#fff;display:flex;flex-direction:column;flex-shrink:0;
    transition:width .25s var(--sd-ease), transform .3s var(--sd-ease);z-index:50;
    position:sticky;top:0;align-self:flex-start;height:100vh;
}
.sd-brand{display:flex;align-items:center;gap:11px;padding:20px 16px 14px;flex-shrink:0;position:relative;z-index:1}
.sd-brand-mark{width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex-shrink:0;box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);overflow:hidden}
.sd-brand-mark img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.sd-brand-text{display:flex;flex-direction:column;line-height:1.25;min-width:0;overflow:hidden}
.sd-brand-name{font-weight:800;font-size:16px;white-space:nowrap}
.sd-brand-name .sd-brand-agri{color:#fff}
.sd-brand-name .sd-brand-cart{color:#8BC53F}
.sd-brand-tag{font-size:10.5px;font-weight:600;letter-spacing:.4px;color:rgba(255,255,255,.55);text-transform:uppercase;white-space:nowrap}
.sd-sidebar-collapse-btn{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.5);font-size:13px;cursor:pointer;width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:background .15s ease,color .15s ease,transform .2s var(--sd-ease);flex-shrink:0}
.sd-sidebar-collapse-btn:hover{background:rgba(255,255,255,.1);color:#fff}
.sd-sidebar-collapse-btn i{transition:transform .25s var(--sd-ease)}
.sd-nav{flex:1;min-height:0;padding:6px 12px 12px;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1}
.sd-sidebar::before{content:'';position:absolute;inset:0;pointer-events:none;
    background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);
    background-size:20px 20px;opacity:.6}
.sd-nav::-webkit-scrollbar{width:5px}
.sd-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.18);border-radius:10px}
.sd-nav::-webkit-scrollbar-track{background:transparent}
.sd-nav-group{margin-bottom:6px}
.sd-nav-group-label{display:block;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:rgba(255,255,255,.38);padding:14px 14px 6px}
.sd-nav-item{
    display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,0.82);
    cursor:pointer;font-size:13.5px;margin-bottom:2px;transition:background .2s ease, transform .15s ease, padding-left .2s ease;
    text-decoration:none;font-weight:600;position:relative;
}
.sd-nav-item i{width:18px;text-align:center;transition:transform .2s ease}
.sd-nav-item:hover{background:rgba(255,255,255,0.1); padding-left:18px; color:#fff}
.sd-nav-item:hover i{transform:scale(1.15)}
.sd-nav-item.active{background:#fff;color:var(--sd-sidebar-teal);font-weight:700;box-shadow:0 6px 14px rgba(0,0,0,.12)}
.sd-nav-item.active::before{content:'';position:absolute;left:-12px;top:50%;transform:translateY(-50%);width:4px;height:18px;border-radius:0 4px 4px 0;background:var(--sd-orange)}
.sd-nav-badge{margin-left:auto;background:var(--sd-danger);color:#fff;font-size:10.5px;font-weight:800;border-radius:10px;padding:1px 7px;display:none;animation:sdPop .3s var(--sd-ease)}
.sd-nav-badge.show{display:inline-block}
@keyframes sdPop{0%{transform:scale(0)}60%{transform:scale(1.25)}100%{transform:scale(1)}}
.sd-sidebar-footer{flex-shrink:0;padding:12px;border-top:1px solid rgba(255,255,255,.08);position:relative;z-index:1}
.sd-sidebar-profile{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;background:rgba(255,255,255,.08);backdrop-filter:blur(6px);cursor:pointer;transition:background .18s ease}
.sd-sidebar-profile:hover{background:rgba(255,255,255,.15)}
.sd-sidebar-avatar{width:32px;height:32px;border-radius:9px;background:var(--sd-orange);color:#fff;font-weight:800;font-size:12.5px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sd-sidebar-profile-text{display:flex;flex-direction:column;min-width:0;line-height:1.25;flex:1}
.sd-sidebar-profile-name{font-size:12.5px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sd-sidebar-profile-role{font-size:10.5px;color:rgba(255,255,255,.5)}
.sd-sidebar-profile>i{color:rgba(255,255,255,.4);font-size:11px;flex-shrink:0}
.sd-sidebar-scrim{display:none}

/* Desktop collapse mode */
@media(min-width:941px){
    .sd-sidebar.collapsed{width:var(--sd-sidebar-w-collapsed)}
    .sd-sidebar.collapsed .sd-brand{padding:20px 0 14px;justify-content:center}
    .sd-sidebar.collapsed .sd-brand-text,
    .sd-sidebar.collapsed .sd-nav-group-label,
    .sd-sidebar.collapsed .sd-sidebar-profile-text,
    .sd-sidebar.collapsed .sd-sidebar-profile>i{display:none}
    .sd-sidebar.collapsed .sd-sidebar-collapse-btn{margin-left:0;position:absolute;right:-11px;top:22px;background:var(--sd-sidebar-teal);box-shadow:0 2px 8px rgba(0,0,0,.25)}
    .sd-sidebar.collapsed .sd-sidebar-collapse-btn i{transform:rotate(180deg)}
    .sd-sidebar.collapsed .sd-nav-item{justify-content:center;padding-left:14px}
    .sd-sidebar.collapsed .sd-nav-item:hover{padding-left:14px}
    .sd-sidebar.collapsed .sd-nav-item>span:not(.sd-nav-badge){display:none}
    .sd-sidebar.collapsed .sd-nav-item .sd-nav-badge{position:absolute;top:6px;right:10px;margin-left:0}
    .sd-sidebar.collapsed .sd-nav-item.active::before{left:0;border-radius:0 4px 4px 0}
    .sd-sidebar.collapsed .sd-sidebar-profile{justify-content:center}
}

/* ---------- Topbar (mirrors admin/index.php .topbar) ---------- */
.sd-main{flex:1;min-width:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;padding:28px 32px;background:#fff;position:sticky;top:0;align-self:flex-start}
.sd-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;background:none;box-shadow:none;padding:0;position:static;border-bottom:none}
.sd-topbar-title{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
.sd-topbar-title i{display:none}
.sd-hello-greet{font-size:13px;font-weight:600;color:var(--sd-green);margin-bottom:2px}
.sd-topbar-title .sd-sub{color:var(--sd-muted);font-size:13px}
.sd-store-link{font-size:13px;color:var(--sd-green);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:6px;transition:transform .15s ease, color .15s ease}
.sd-store-link:hover{transform:translateX(3px); color:var(--sd-dark-green)}
.sd-menu-toggle{display:none;background:none;border:none;font-size:20px;color:var(--sd-text);cursor:pointer;transition:transform .2s var(--sd-ease)}
.sd-menu-toggle:hover{transform:scale(1.15)}
.sd-topbar-right{display:flex;align-items:center;gap:14px}
.sd-bell{position:relative;font-size:15px;color:var(--sd-dark-green);cursor:pointer;background:var(--sd-green-light);width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:transform .15s cubic-bezier(.34,1.56,.64,1), background .15s ease, box-shadow .15s ease}
.sd-bell:hover{transform:translateY(-2px) scale(1.08); box-shadow:0 4px 10px rgba(0,0,0,0.15)}
.sd-bell-dot{position:absolute;top:4px;right:5px;width:9px;height:9px;background:var(--sd-danger);border-radius:50%;display:none;border:2px solid #fff;animation:sdPulse 1.8s ease-in-out infinite}
.sd-topbar-avatar{width:34px;height:34px;border-radius:9px;background:linear-gradient(150deg,var(--sd-green) 0%,var(--sd-dark-green) 100%);color:#fff;font-weight:800;font-size:12.5px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;box-shadow:0 3px 8px rgba(18,53,36,.28);border:2px solid #fff;outline:1px solid var(--sd-green-light)}
.sd-topbar-avatar:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(18,53,36,.3)}
.sd-bell-dot.show{display:block}
@keyframes sdPulse{0%,100%{box-shadow:0 0 0 0 rgba(192,57,43,.5)}50%{box-shadow:0 0 0 4px rgba(192,57,43,0)}}

.sd-content{padding:0;max-width:1400px;width:100%;margin:0 auto}
.sd-section{display:none}
.sd-section.active{display:block;animation:sdFade .3s var(--sd-ease)}
@keyframes sdFade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.sd-section-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.sd-section-head h2{font-size:20px;font-weight:800;color:var(--sd-dark-green);display:flex;align-items:center;gap:10px}

/* ---------- Cards (mirrors admin/index.php .stat-card) ---------- */
.sd-cards-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
.sd-card{position:relative;background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 1px 3px rgba(20,30,25,0.06);display:flex;align-items:center;gap:14px;overflow:hidden;transition:transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s ease;cursor:pointer}
.sd-card.sd-card-static{cursor:default}
.sd-card:hover{transform:translateY(-5px); box-shadow:0 12px 26px rgba(0,0,0,0.12)}
.sd-card::after{display:none}
.sd-card-icon{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;transition:transform .3s cubic-bezier(.34,1.56,.64,1);position:relative}
.sd-card:hover .sd-card-icon{transform:scale(1.12) rotate(-6deg)}
.sd-card-value{font-size:21px;font-weight:800;color:var(--sd-text);line-height:1.1}
.sd-card-label{font-size:12px;color:var(--sd-muted);font-weight:500}
.sd-ic-green{background:var(--sd-green);color:#fff}
.sd-ic-orange{background:var(--sd-orange);color:#fff}
.sd-ic-danger{background:var(--sd-danger);color:#fff}

.sd-panel{background:#fff;border-radius:16px;box-shadow:none;border:1.5px solid var(--sd-grey-border);padding:22px;margin-bottom:22px}
.sd-panel:hover{box-shadow:none}
.sd-panel-title{font-size:17px;font-weight:700;color:var(--sd-text);margin-bottom:18px;display:flex;align-items:center;gap:8px}

/* ---------- Tables (mirrors admin table/th/td) ---------- */
.sd-table-wrap{overflow-x:auto;border-radius:0;border:none}
table.sd-table{width:100%;border-collapse:collapse;font-size:13px;min-width:720px}
table.sd-table th{background:var(--sd-green-light);color:var(--sd-dark-green);text-align:left;padding:10px 12px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap;position:sticky;top:0;z-index:2}
table.sd-table tbody tr{transition:background .15s ease}
table.sd-table tbody tr:nth-child(even){background:transparent}
table.sd-table td{padding:10px 12px;border-bottom:1px solid var(--sd-grey-border);border-top:none;vertical-align:middle}
table.sd-table tr:hover td{background:var(--sd-green-light)}
.sd-prod-thumb{width:42px;height:42px;border-radius:8px;object-fit:cover;background:#eee;flex-shrink:0;transition:transform .25s ease;display:block}
table.sd-table tr:hover .sd-prod-thumb{transform:scale(1.12)}
.sd-prod-cell{display:flex;align-items:center;gap:10px;font-weight:600}
.sd-avatar-sm{width:30px;height:30px;border-radius:50%;object-fit:cover;background:var(--sd-green);flex-shrink:0}

.sd-badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;transition:transform .15s ease}
.sd-badge:hover{transform:scale(1.05)}
.sd-badge-green{background:var(--sd-green-light);color:var(--sd-green)}
.sd-badge-orange{background:var(--sd-orange-light);color:#F9A825}
.sd-badge-danger{background:var(--sd-danger-light);color:var(--sd-danger)}
.sd-badge-grey{background:#eef0ee;color:var(--sd-muted)}

.sd-btn{border:none;border-radius:9px;padding:10px 16px;font-size:13.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .2s ease, transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease}
@media(pointer:coarse){.sd-btn{min-height:44px}.sd-menu-toggle,.sd-bell,.sd-topbar-avatar{min-width:36px;min-height:36px}}
.sd-btn:active{transform:translateY(0) scale(.97)}
.sd-btn-green{background:var(--sd-green);color:#fff}
.sd-btn-green:hover{background:var(--sd-dark-green); transform:translateY(-2px); box-shadow:0 6px 14px rgba(47,79,68,0.3)}
.sd-btn-orange{background:var(--sd-orange);color:#1c2e1c}
.sd-btn-orange:hover{background:#e6ac00; transform:translateY(-2px); box-shadow:0 6px 14px rgba(249,168,37,0.35)}
.sd-btn-danger{background:var(--sd-danger-light);color:var(--sd-danger)}
.sd-btn-danger:hover{background:var(--sd-danger);color:#fff}
.sd-btn-outline{background:#f2f2f2;color:#444;border:none}
.sd-btn-outline:hover{background:#e6e6e6}
.sd-row-actions{display:flex;gap:6px;flex-wrap:wrap}

.sd-input,.sd-select,textarea.sd-input{width:100%;padding:9px 10px;border:1px solid var(--sd-grey-border);border-radius:8px;font-size:13px;font-family:inherit;transition:border-color .15s ease, box-shadow .15s ease}
.sd-input:hover,.sd-select:hover{border-color:var(--sd-green)}
.sd-input:focus,.sd-select:focus{outline:none;border-color:var(--sd-green);box-shadow:0 0 0 3px rgba(47,79,68,0.1)}
.sd-form-row{margin-bottom:14px}
.sd-form-row label{font-size:12px;font-weight:600;color:var(--sd-muted);display:block;margin-bottom:4px}
.sd-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:600px){.sd-form-grid{grid-template-columns:1fr}}

.sd-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.sd-toolbar .sd-input,.sd-toolbar .sd-select{width:auto;min-width:150px}
.sd-pagination{display:flex;gap:6px;justify-content:center;margin-top:16px}
.sd-pagination button{border:1px solid var(--sd-grey-border);background:#fff;color:var(--sd-text);font-size:12px;padding:5px 10px;border-radius:6px;cursor:pointer;transition:background .15s ease,color .15s ease}
.sd-pagination button:hover{background:var(--sd-green-light)}
.sd-pagination button.active{background:var(--sd-green);border-color:var(--sd-green);color:#fff;border-radius:50%;width:26px;height:26px;padding:0;display:inline-flex;align-items:center;justify-content:center}

.sd-empty{text-align:center;padding:40px 0;color:var(--sd-muted);font-size:13.5px}
.sd-empty i{font-size:32px;color:var(--sd-grey-border);margin-bottom:10px;display:block;animation:sdFloat 3s ease-in-out infinite}
@keyframes sdFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.sd-empty-title{font-weight:700;color:var(--sd-text);margin-bottom:6px}
.sd-loading{text-align:center;padding:40px;color:var(--sd-muted)}
.sd-loading i{font-size:24px;animation:sdSpin 1s linear infinite}
@keyframes sdSpin{to{transform:rotate(360deg)}}

/* ---------- Modal (mirrors admin .modal-overlay/.modal-box) ---------- */
.sd-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:200;padding:20px}
.sd-modal-overlay.open{display:flex;animation:sdOverlayIn .2s ease}
@keyframes sdOverlayIn{from{opacity:0}to{opacity:1}}
.sd-modal{background:#fff;border-radius:14px;max-width:560px;width:100%;max-height:90vh;overflow-y:auto;padding:26px;animation:sdModalIn .3s cubic-bezier(.22,.8,.36,1) both}
@keyframes sdModalIn{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.sd-modal h3{font-size:16px;font-weight:700;color:var(--sd-text);margin-bottom:16px;margin-top:0;display:flex;align-items:center;gap:8px}
.sd-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
.sd-timeline{position:relative;padding-left:18px;margin-top:8px}
.sd-timeline-row{position:relative;padding-bottom:16px}
.sd-timeline-row:last-child{padding-bottom:0}
.sd-timeline-row:not(:last-child)::before{content:'';position:absolute;left:-14px;top:12px;bottom:-4px;width:2px;background:var(--sd-border,#e3e8e4)}
.sd-timeline-dot{position:absolute;left:-18px;top:3px;width:9px;height:9px;border-radius:50%;background:var(--sd-green,#3f8f5f);box-shadow:0 0 0 3px rgba(63,143,95,.15)}
.sd-timeline-body strong{font-size:13px;color:var(--sd-text)}
.sd-modal.sd-modal-wide{max-width:760px}

/* ---------- Reviews ---------- */
.sd-review-card{border:1px solid var(--sd-grey-border);border-radius:14px;padding:16px;margin-bottom:12px;transition:box-shadow .22s ease}
.sd-review-card:hover{box-shadow:0 10px 24px rgba(27,67,50,.10)}
.sd-review-head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.sd-stars{color:#F9A825;font-size:13px}
.sd-review-imgs{display:flex;gap:8px;margin:8px 0}
.sd-review-imgs img{width:56px;height:56px;border-radius:8px;object-fit:cover;transition:transform .2s ease;cursor:pointer}
.sd-review-imgs img:hover{transform:scale(1.08)}
.sd-reply-box{background:var(--sd-green-light);border-radius:10px;padding:10px 14px;margin-top:10px;font-size:13px}

/* ---------- Notifications dropdown ---------- */
.sd-notif-panel{position:absolute;top:60px;right:26px;width:340px;max-height:420px;overflow-y:auto;background:#fff;border-radius:14px;box-shadow:0 10px 28px rgba(0,0,0,0.4);display:none;z-index:210;border:1px solid var(--sd-grey-border)}
.sd-notif-panel.open{display:block;animation:sdModalIn .22s ease}
.sd-notif-item{padding:13px 16px;border-bottom:1px solid var(--sd-grey-border);font-size:12.5px;cursor:pointer;transition:background .15s ease,padding-left .15s ease}
.sd-notif-item:hover{background:var(--sd-green-light);padding-left:20px}
.sd-notif-item.unread{background:#FAFDF9;border-left:3px solid var(--sd-green)}
.sd-notif-title{font-weight:700;color:var(--sd-text);margin-bottom:3px}
.sd-notif-meta{color:var(--sd-muted);font-size:10.5px;margin-top:4px}

.sd-perf-highlight{border-radius:8px;padding:12px 16px;font-size:12.5px;color:var(--sd-muted);margin-bottom:16px;display:flex;align-items:center;gap:10px;background:var(--sd-green-light);border-left:4px solid var(--sd-green)}

/* ---------- Responsive ---------- */
@media(max-width:940px){
    .sd-sidebar{position:fixed;left:-280px;top:0;bottom:0;width:270px;transform:none;z-index:999;transition:left .25s var(--sd-ease);box-shadow:0 0 0 rgba(0,0,0,0)}
    .sd-sidebar.open{left:0;box-shadow:12px 0 40px rgba(0,0,0,.25)}
    .sd-sidebar-collapse-btn{display:none}
    .sd-menu-toggle{display:block}
    .sd-form-grid{grid-template-columns:1fr}
    .sd-sidebar-scrim{display:none;position:fixed;inset:0;background:rgba(10,30,20,.45);z-index:998;backdrop-filter:blur(2px)}
    .sd-sidebar-scrim.show{display:block;animation:sdToastIn .2s ease}
    /* Notification dropdown was fixed at right:26px + width:340px, which
       needs 366px of clearance from the right edge — wider than most phones
       (320-428px), pushing it off the left side of the screen. */
    .sd-notif-panel{width:min(340px, calc(100vw - 32px));right:16px}
}
.sd-toast{position:fixed;bottom:24px;right:24px;background:var(--sd-dark-green);color:#fff;padding:12px 18px;border-radius:10px;font-size:13.5px;font-weight:500;box-shadow:0 8px 20px rgba(0,0,0,0.3);z-index:2000;display:none;max-width:320px}
.sd-toast.show{display:flex;align-items:center;gap:10px;animation:sdToastIn .3s var(--sd-ease)}
@keyframes sdToastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ---------- Overview (Dashboard) — reference-matched layout ---------- */
.sd-ov-grid{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start}
@media(max-width:1100px){.sd-ov-grid{grid-template-columns:1fr}}
.sd-ov-greet{margin-bottom:20px}
.sd-ov-greet-sub{font-size:12.5px;color:var(--sd-muted);font-weight:500}
.sd-ov-greet-title{font-size:28px;font-weight:800;color:var(--sd-text);margin:4px 0 0}
.sd-ov-greet-name{color:var(--sd-orange)}
.sd-ov-row{display:grid;grid-template-columns:1fr 260px;gap:16px;margin-bottom:16px;align-items:stretch}
@media(max-width:760px){.sd-ov-row{grid-template-columns:1fr}}
.sd-ov-chart-panel,.sd-ov-todo-panel{margin-bottom:0}
.sd-ov-chart-panel .sd-panel-title{margin-bottom:10px}
/* Fixed-height positioned wrapper so Chart.js (maintainAspectRatio:false)
   resizes cleanly on narrow screens instead of collapsing or stretching. */
.sd-chart-canvas-wrap{position:relative;height:260px}
@media(max-width:600px){.sd-chart-canvas-wrap{height:210px}}
.sd-ov-range-select{margin-left:auto;width:auto;font-size:12px;padding:6px 10px}
.sd-ov-todo-panel{background:#fff;border:1.5px solid var(--sd-grey-border)}
.sd-ov-todo-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 14px}
.sd-ov-todo-item{cursor:pointer;transition:transform .2s var(--sd-ease)}
.sd-ov-todo-item:hover{transform:translateY(-3px)}
.sd-ov-todo-item i{display:none}
.sd-ov-todo-value{font-size:26px;font-weight:800;color:var(--sd-dark-green);line-height:1.1}
.sd-ov-todo-label{font-size:11px;color:var(--sd-muted);margin-top:3px;font-weight:500}
.sd-ov-stats-strip{display:flex;flex-wrap:wrap;gap:0;margin-bottom:22px;background:#fff;border:1.5px solid var(--sd-grey-border);border-radius:16px;padding:16px 20px}
.sd-ov-stat-box{background:none;border-radius:0;padding:0 20px;box-shadow:none;display:flex;flex-direction:column;gap:2px;transition:transform .2s var(--sd-ease);border-left:1px solid var(--sd-grey-border);flex:1 1 0;min-width:120px}
.sd-ov-stat-box:first-child{border-left:none;padding-left:0}
.sd-ov-stat-box:hover{transform:translateY(-3px)}
.sd-ov-stat-box i{width:8px;height:8px;border-radius:50%;background:var(--sd-green);color:transparent;font-size:0;flex-shrink:0;margin-bottom:2px}
.sd-ov-stat-box:nth-child(2) i{background:var(--sd-orange)}
.sd-ov-stat-box:nth-child(3) i{background:#8b5cf6}
.sd-ov-stat-box:nth-child(4) i{background:var(--sd-danger)}
.sd-ov-stat-value{font-size:22px;font-weight:800;color:var(--sd-text);line-height:1.1}
.sd-ov-stat-label{font-size:11.5px;color:var(--sd-muted);margin-top:2px;font-weight:500}
@media(max-width:640px){.sd-ov-stat-box{flex:1 1 45%;border-left:none;border-top:1px solid var(--sd-grey-border);padding:10px 0 0}.sd-ov-stat-box:first-child,.sd-ov-stat-box:nth-child(2){border-top:none;padding-top:0}}
.sd-ov-topprod-row{display:flex;gap:16px;overflow-x:auto;padding-bottom:6px}
.sd-ov-topprod-card{flex:0 0 140px;text-align:center;cursor:pointer;position:relative;transition:transform .2s var(--sd-ease)}
.sd-ov-topprod-card:hover{transform:translateY(-4px)}
.sd-ov-topprod-card img{width:100%;height:120px;object-fit:cover;border-radius:16px;background:var(--sd-green-light);margin-bottom:10px}
.sd-ov-topprod-rank{position:absolute;bottom:38px;right:8px;background:var(--sd-orange);color:#fff;font-size:10.5px;font-weight:800;padding:3px 8px;min-width:24px;border-radius:20px;display:flex;align-items:center;justify-content:center;z-index:1;box-shadow:0 4px 8px rgba(0,0,0,.15)}
.sd-ov-topprod-name{font-size:12.5px;font-weight:600;color:var(--sd-text);line-height:1.3}

/* ---------- Latest Invoice (overview) — its own polished card-row look ---------- */
#sdLatestInvoiceTable{border-collapse:separate;border-spacing:0}
#sdLatestInvoiceTable thead th{background:none;border-bottom:2px solid var(--sd-grey-border);color:var(--sd-muted);font-size:10.5px;letter-spacing:.4px;padding:0 14px 10px}
#sdLatestInvoiceTable tbody tr{background:#fff;transition:background .15s ease,transform .15s ease}
#sdLatestInvoiceTable tbody tr:hover{background:var(--sd-green-light)}
#sdLatestInvoiceTable tbody tr:hover td{background:none}
#sdLatestInvoiceTable td{padding:14px;border-bottom:1px solid var(--sd-grey-border)}
#sdLatestInvoiceTable tbody tr:last-child td{border-bottom:none}
.sd-inv-id-chip{display:inline-block;background:var(--sd-green-light);color:var(--sd-dark-green);font-weight:700;font-size:11.5px;padding:3px 9px;border-radius:8px;font-family:'Courier New',monospace;margin-bottom:3px}
.sd-inv-prod-row{display:flex;align-items:center;gap:12px}
.sd-inv-thumb{width:44px;height:44px;border-radius:10px;object-fit:cover;background:var(--sd-green-light);flex-shrink:0}
.sd-inv-prod-name{font-weight:600;font-size:13px;color:var(--sd-text)}
.sd-inv-buyer{display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px}
.sd-inv-date{font-size:12.5px;color:var(--sd-muted);display:flex;align-items:center;gap:6px}
.sd-inv-view-btn{width:34px;height:34px;padding:0;border-radius:10px;justify-content:center}

.sd-ov-side{display:flex;flex-direction:column;gap:16px}
.sd-ov-profile-card{background:#fff;border-radius:16px;padding:10px 14px;box-shadow:var(--sd-shadow);display:flex;align-items:center;gap:12px;border:1.5px solid var(--sd-grey-border)}
.sd-ov-avatar{width:42px;height:42px;border-radius:50%;background:var(--sd-green);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0}
.sd-ov-avatar-sm{width:32px;height:32px;font-size:12px}
.sd-ov-profile-name{font-weight:700;font-size:13.5px;color:var(--sd-text)}
.sd-ov-profile-role{font-size:11px;color:var(--sd-muted)}
.sd-ov-visit-link{margin-left:auto;color:var(--sd-green);font-size:13px}
.sd-ov-revenue-card{position:relative;overflow:hidden;background:#fff;border:1.5px solid var(--sd-grey-border);border-radius:16px;padding:20px;color:var(--sd-text)}
.sd-ov-revenue-icon{position:absolute;top:14px;right:16px;font-size:30px;color:var(--sd-orange);opacity:.85}
.sd-ov-revenue-label{font-size:12px;color:var(--sd-muted);margin-bottom:6px;font-weight:600}
.sd-ov-revenue-value{font-size:24px;font-weight:800;margin-bottom:14px;color:var(--sd-text)}
.sd-ov-withdraw-btn{background:var(--sd-green);color:#fff;width:100%;justify-content:center}
.sd-ov-withdraw-btn:hover{background:var(--sd-sidebar-teal)}
.sd-ov-testimonial-panel{margin-bottom:0;border:1.5px solid var(--sd-grey-border)}
.sd-ov-testimonial-head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.sd-ov-testimonial-text{font-size:12.5px;color:var(--sd-muted);font-style:italic;line-height:1.5;margin:0}
.sd-ov-customer-card{background:var(--sd-sidebar-teal);color:#fff;border-radius:16px;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:transform .2s var(--sd-ease)}
.sd-ov-customer-card:hover{transform:translateY(-3px)}
.sd-ov-customer-value{font-size:24px;font-weight:800}
.sd-ov-customer-label{font-size:11.5px;opacity:.85}
.sd-ov-customer-card i{font-size:26px;opacity:.6}
@media(max-width:600px){.sd-ov-todo-grid{grid-template-columns:1fr 1fr}}
@media(max-width:420px){
    .sd-ov-todo-grid{grid-template-columns:1fr}
    .sd-main{padding:18px 16px}
    .sd-cards-grid{grid-template-columns:repeat(2,1fr)}
}
</style>

<div class="sd-shell" id="sdShell">
 <div class="sd-shell-inner">
  <aside class="sd-sidebar" id="sdSidebar">
    <div class="sd-brand">
      <div class="sd-brand-mark"><img src="data:image/png;base64,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" alt="AgriCart"></div>
      <div class="sd-brand-text">
        <span class="sd-brand-name"><span class="sd-brand-agri">Agri</span><span class="sd-brand-cart">Cart</span></span>
        <span class="sd-brand-tag" data-sd="brandTag">Seller Hub</span>
      </div>
      <button class="sd-sidebar-collapse-btn" id="sdSidebarCollapseBtn" type="button" aria-label="Collapse sidebar"><i class="fa-solid fa-angles-left"></i></button>
    </div>
    <nav class="sd-nav" id="sdNav">
      <div class="sd-nav-group">
        <span class="sd-nav-group-label" data-sd="navGroupOverview">Overview</span>
        <a class="sd-nav-item active" data-section="dashboard"><i class="fa-solid fa-gauge-high"></i><span data-sd="navDashboard">Dashboard</span></a>
      </div>
      <?php if ($showProductSections): ?>
      <div class="sd-nav-group">
        <span class="sd-nav-group-label" data-sd="navGroupSelling">Selling</span>
        <a class="sd-nav-item" data-section="products"><i class="fa-solid fa-box-open"></i><span data-sd="navProducts">My Products</span></a>
        <a class="sd-nav-item" href="../pages/sell_product.php"><i class="fa-solid fa-circle-plus"></i><span data-sd="navAdd">Add Product</span></a>
        <a class="sd-nav-item" data-section="stock"><i class="fa-solid fa-warehouse"></i><span data-sd="navStock">Stock Management</span></a>
        <a class="sd-nav-item" data-section="orders"><i class="fa-solid fa-truck-fast"></i><span data-sd="navOrders">Orders</span><span class="sd-nav-badge" id="sdOrdersBadge"></span></a>
      </div>
      <?php endif; ?>
      <?php if ($showRentalSections): ?>
      <div class="sd-nav-group">
        <span class="sd-nav-group-label" data-sd="navGroupRentals">Rentals</span>
        <a class="sd-nav-item" data-section="equipment"><i class="fa-solid fa-tractor"></i><span data-sd="navEquipment">My Equipment</span></a>
        <a class="sd-nav-item" href="../pages/list_equipment.php"><i class="fa-solid fa-circle-plus"></i><span data-sd="navAddEquipment">Add Equipment</span></a>
        <a class="sd-nav-item" data-section="rentalBookings"><i class="fa-solid fa-calendar-check"></i><span data-sd="navRentalBookings">Rental Bookings</span><span class="sd-nav-badge" id="sdRentalBadge"></span></a>
      </div>
      <?php endif; ?>
      <div class="sd-nav-group">
        <span class="sd-nav-group-label" data-sd="navGroupInsights">Insights</span>
        <a class="sd-nav-item" data-section="sales"><i class="fa-solid fa-chart-line"></i><span data-sd="navSales">Sales History</span></a>
        <a class="sd-nav-item" data-section="customers"><i class="fa-solid fa-users"></i><span data-sd="navCustomers">Customers</span></a>
        <a class="sd-nav-item" data-section="reviews"><i class="fa-solid fa-star"></i><span data-sd="navReviews">Reviews</span></a>
        <a class="sd-nav-item" data-section="earnings"><i class="fa-solid fa-wallet"></i><span data-sd="navEarnings">Earnings &amp; Payouts</span></a>
        <a class="sd-nav-item" data-section="invoices"><i class="fa-solid fa-file-invoice-dollar"></i><span data-sd="navInvoices">Sales Invoices</span></a>
      </div>
      <div class="sd-nav-group">
        <span class="sd-nav-group-label" data-sd="navGroupAccount">Account</span>
        <a class="sd-nav-item" data-section="notifications"><i class="fa-solid fa-bell"></i><span data-sd="navNotifications">Notifications</span><span class="sd-nav-badge" id="sdNavNotifBadge"></span></a>
        <a class="sd-nav-item" data-section="account"><i class="fa-solid fa-user-gear"></i><span data-sd="navAccount">My Account</span></a>
        <a class="sd-nav-item" href="../pages/become_seller.php"><i class="fa-solid fa-sliders"></i><span data-sd="navSellingPrefs">Selling Preferences</span></a>
        <a class="sd-nav-item" data-section="inactive"><i class="fa-solid fa-box-archive"></i><span data-sd="navInactive">Inactive Listings</span><span class="sd-nav-badge" id="sdInactiveBadge"></span></a>
      </div>
    </nav>
    <div class="sd-sidebar-footer">
      <div class="sd-sidebar-profile" onclick="sdShowSection('account')">
        <div class="sd-sidebar-avatar" id="sdSidebarAvatar">S</div>
        <div class="sd-sidebar-profile-text">
          <span class="sd-sidebar-profile-name" id="sdSidebarProfileName">Seller</span>
          <span class="sd-sidebar-profile-role" data-sd="sellerRole">Seller</span>
        </div>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
    </div>
  </aside>
  <div class="sd-sidebar-scrim" id="sdSidebarScrim"></div>

  <div class="sd-main">
    <header class="sd-topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="sd-menu-toggle" id="sdMenuToggle"><i class="fa-solid fa-bars"></i></button>
        <div class="sd-topbar-title">
            <div id="sdTopbarGreet">
              <div class="sd-ov-greet-sub" data-sd="ovWelcome">Welcome back, to your AgriCart store</div>
              <h1 class="sd-ov-greet-title" style="margin:2px 0 0"><span id="sdGreetPrefix">Good Morning</span>, <span id="sdGreetName" class="sd-ov-greet-name"></span>!</h1>
            </div>
            <div id="sdTopbarDefault" style="display:none">
              <h1 style="font-size:22px;font-weight:700;margin:0;color:var(--sd-text)" data-sd="topbarTitle">Seller Dashboard</h1>
              <div class="sd-sub" data-sd="topbarSub">Manage your listings, orders, and earnings on AgriCart.</div>
            </div>
        </div>
      </div>
      <div class="sd-topbar-right">
        <a href="../pages/marketplace.php" class="sd-store-link" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> <span data-sd="viewStorefront">View Storefront</span></a>
        <div class="sd-bell" id="sdBellBtn"><i class="fa-solid fa-bell"></i><span class="sd-bell-dot" id="sdBellDot"></span></div>
        <div class="sd-topbar-avatar" id="sdTopbarAvatar" onclick="sdShowSection('account')" title="My Account">S</div>
      </div>
      <div class="sd-notif-panel" id="sdNotifPanel"></div>
    </header>

    <main class="sd-content">
      <?php if (!$hasProducts): ?>
      <div class="sd-panel" style="background:var(--sd-orange-light);border-color:#f0d3ae;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div><strong data-sd="noProdTitle">You haven't listed any products yet.</strong><br><span style="font-size:12.5px;color:var(--sd-muted)" data-sd="noProdSub">List your first product to start selling on AgriCart.</span></div>
        <a href="../pages/sell_product.php" class="sd-btn sd-btn-orange"><i class="fa-solid fa-circle-plus"></i> <span data-sd="noProdBtn">Add Product</span></a>
      </div>
      <?php endif; ?>

      <!-- ===================== DASHBOARD (Overview) ===================== -->
      <section class="sd-section active" id="sec-dashboard">
        <div class="sd-ov-grid">
          <div class="sd-ov-main">
            <div class="sd-ov-greet">
              <h1 style="font-size:20px;font-weight:700;margin:0;color:var(--sd-text)" data-sd="topbarTitle">Seller Dashboard</h1>
              <div class="sd-sub" style="color:var(--sd-muted);font-size:13px;margin-top:2px" data-sd="topbarSub">Manage your listings, orders, and earnings on AgriCart.</div>
            </div>

            <div class="sd-ov-row">
              <div class="sd-panel sd-ov-chart-panel">
                <div class="sd-panel-title">
                  <i class="fa-solid fa-chart-column"></i> <span data-sd="analyticsTitle">Sales Chart</span>
                  <select class="sd-select sd-ov-range-select" id="sdAnalyticsRange" onchange="sdOnRangeChange()">
                    <option value="today" data-sd="rangeToday">Today</option>
                    <option value="7d" selected data-sd="range7d">Last 7 Days</option>
                    <option value="30d" data-sd="range30d">Last 30 Days</option>
                    <option value="month" data-sd="rangeMonth">This Month</option>
                    <option value="custom" data-sd="rangeCustom">Custom Range</option>
                  </select>
                </div>
                <div class="sd-toolbar" id="sdCustomRangeRow" style="display:none">
                  <input type="date" class="sd-input" id="sdRangeStart" style="width:150px">
                  <input type="date" class="sd-input" id="sdRangeEnd" style="width:150px">
                  <button class="sd-btn sd-btn-green" onclick="sdLoadAnalytics()"><span data-sd="apply">Apply</span></button>
                </div>
                <div class="sd-chart-canvas-wrap"><canvas id="sdSalesChart" height="110"></canvas></div>
              </div>

              <div class="sd-panel sd-ov-todo-panel">
                <div class="sd-panel-title" data-sd="todoTitle">To Do List</div>
                <div class="sd-ov-todo-grid" id="sdTodoGrid"></div>
              </div>
            </div>

            <div class="sd-ov-stats-strip" id="sdStoreStats"></div>

            <div class="sd-panel">
              <div class="sd-section-head" style="margin-bottom:14px">
                <div class="sd-panel-title" style="margin:0"><i class="fa-solid fa-ranking-star"></i> <span data-sd="topSellingTitle">Top Selling Product</span></div>
                <a href="javascript:void(0)" onclick="sdShowSection('sales')" class="sd-store-link"><span data-sd="viewAll">View All</span> <i class="fa-solid fa-arrow-right"></i></a>
              </div>
              <div class="sd-ov-topprod-row" id="sdTopProducts"></div>
            </div>

            <div class="sd-panel">
              <div class="sd-section-head" style="margin-bottom:14px">
                <div class="sd-panel-title" style="margin:0"><i class="fa-solid fa-file-invoice"></i> <span data-sd="latestInvoiceTitle">Latest Invoice</span></div>
                <a href="javascript:void(0)" onclick="sdShowSection('orders')" class="sd-store-link"><span data-sd="viewAll">View All</span> <i class="fa-solid fa-arrow-right"></i></a>
              </div>
              <div class="sd-table-wrap"><table class="sd-table" id="sdLatestInvoiceTable"></table></div>
            </div>
          </div>

          <aside class="sd-ov-side">
            <div class="sd-ov-profile-card">
              <div class="sd-ov-avatar" id="sdOvAvatar"></div>
              <div>
                <div class="sd-ov-profile-name" id="sdOvName"></div>
                <div class="sd-ov-profile-role" data-sd="sellerRole">Seller</div>
              </div>
              <a class="sd-ov-visit-link" href="../pages/marketplace.php" title="View Storefront"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            </div>

            <div class="sd-ov-revenue-card">
              <i class="fa-solid fa-coins sd-ov-revenue-icon"></i>
              <div class="sd-ov-revenue-label" data-sd="availableFunds">Available Funds</div>
              <div class="sd-ov-revenue-value" id="sdAvailableFunds">₹0</div>
              <button class="sd-btn sd-ov-withdraw-btn" onclick="sdOpenWithdrawModal()"><i class="fa-solid fa-wallet"></i> <span data-sd="withdraw">Withdraw</span></button>
            </div>

            <div class="sd-panel sd-ov-testimonial-panel">
              <div class="sd-section-head" style="margin-bottom:12px">
                <div class="sd-panel-title" style="margin:0"><span data-sd="recentTestimonials">Recent Testimonials</span></div>
                <a href="javascript:void(0)" onclick="sdShowSection('reviews')" class="sd-store-link"><i class="fa-solid fa-arrow-right"></i></a>
              </div>
              <div id="sdOvTestimonial"></div>
            </div>

            <div class="sd-ov-customer-card" onclick="sdShowSection('customers')">
              <div>
                <div class="sd-ov-customer-value" id="sdOvCustomerCount">0</div>
                <div class="sd-ov-customer-label" data-sd="activeCustomers">Customers</div>
              </div>
              <i class="fa-solid fa-users"></i>
            </div>
          </aside>
        </div>
      </section>


      <?php if ($showProductSections): ?>
      <!-- ===================== PRODUCTS ===================== -->
      <section class="sd-section" id="sec-products">
        <div class="sd-section-head"><h2><i class="fa-solid fa-box-open"></i> <span data-sd="prodTitle">My Products</span></h2></div>
        <div class="sd-toolbar">
          <input class="sd-input" id="sdProdSearch" placeholder="Search products..." data-sd-ph="prodSearchPh" oninput="sdDebounce(sdLoadProducts)">
        </div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdProductsTable"></table></div>
        <div class="sd-pagination" id="sdProductsPager"></div>
      </section>

      <!-- ===================== STOCK ===================== -->
      <section class="sd-section" id="sec-stock">
        <div class="sd-section-head"><h2><i class="fa-solid fa-warehouse"></i> <span data-sd="stockTitle">Stock Management</span></h2></div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdStockTable"></table></div>
        <div class="sd-pagination" id="sdStockPager"></div>
      </section>

      <!-- ===================== ORDERS ===================== -->
      <section class="sd-section" id="sec-orders">
        <div class="sd-section-head"><h2><i class="fa-solid fa-truck-fast"></i> <span data-sd="ordersTitle">Orders</span></h2></div>
        <div class="sd-toolbar">
          <input class="sd-input" id="sdOrderSearch" data-sd-ph="orderSearchPh" placeholder="Search order id / buyer / product" oninput="sdDebounce(()=>sdLoadOrders(1))">
          <select class="sd-select" id="sdOrderStatus" onchange="sdLoadOrders(1)">
            <option value="" data-sd="all">All Statuses</option>
            <option value="new_order" data-sd="stNewOrder">New Order</option>
            <option value="confirmed" data-sd="stConfirmed">Confirmed</option>
            <option value="packed" data-sd="stPacked">Packed</option>
            <option value="shipped" data-sd="stShipped">Shipped</option>
            <option value="delivered" data-sd="stDelivered">Delivered</option>
            <option value="cancelled" data-sd="stCancelled">Cancelled</option>
            <option value="returned" data-sd="stReturned">Returned</option>
          </select>
          <input type="date" class="sd-input" id="sdOrderFrom" onchange="sdLoadOrders(1)">
          <input type="date" class="sd-input" id="sdOrderTo" onchange="sdLoadOrders(1)">
        </div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdOrdersTable"></table></div>
        <div class="sd-pagination" id="sdOrdersPager"></div>
      </section>
      <?php endif; ?>

      <?php if ($showRentalSections): ?>
      <!-- ===================== MY EQUIPMENT (RENTAL) ===================== -->
      <section class="sd-section" id="sec-equipment">
        <div class="sd-section-head">
          <h2><i class="fa-solid fa-tractor"></i> <span data-sd="equipTitle">My Equipment</span></h2>
          <a href="../pages/list_equipment.php" class="sd-btn sd-btn-green"><i class="fa-solid fa-circle-plus"></i> <span data-sd="addEquipment">Add Equipment</span></a>
        </div>
        <div class="note-box-sd" style="background:var(--sd-green-light);border-left:4px solid var(--sd-green);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--sd-muted);margin-bottom:16px">
          <i class="fa-solid fa-circle-info"></i> <span data-sd="equipApprovalNote">New equipment listings need admin approval before they appear on the Rental Hub.</span>
        </div>
        <div class="sd-toolbar">
          <input class="sd-input" id="sdEquipSearch" placeholder="Search equipment..." data-sd-ph="equipSearchPh" oninput="sdDebounce(()=>sdLoadEquipment(1))">
        </div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdEquipmentTable"></table></div>
        <div class="sd-pagination" id="sdEquipmentPager"></div>
      </section>

      <!-- ===================== RENTAL BOOKINGS ===================== -->
      <section class="sd-section" id="sec-rentalBookings">
        <div class="sd-section-head"><h2><i class="fa-solid fa-calendar-check"></i> <span data-sd="rentalBookingsTitle">Rental Bookings</span></h2></div>
        <div class="sd-toolbar">
          <input class="sd-input" id="sdRentalSearch" data-sd-ph="rentalSearchPh" placeholder="Search booking # / equipment / customer" oninput="sdDebounce(()=>sdLoadRentalBookings(1))">
          <select class="sd-select" id="sdRentalStatus" onchange="sdLoadRentalBookings(1)">
            <option value="" data-sd="all">All Statuses</option>
            <option value="pending" data-sd="rbPending">Pending</option>
            <option value="confirmed" data-sd="rbConfirmed">Confirmed</option>
            <option value="on_the_way" data-sd="rbOnTheWay">On the Way</option>
            <option value="completed" data-sd="rbCompleted">Completed</option>
            <option value="cancelled" data-sd="rbCancelled">Cancelled</option>
          </select>
          <input type="date" class="sd-input" id="sdRentalFrom" onchange="sdLoadRentalBookings(1)">
          <input type="date" class="sd-input" id="sdRentalTo" onchange="sdLoadRentalBookings(1)">
        </div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdRentalTable"></table></div>
        <div class="sd-pagination" id="sdRentalPager"></div>
      </section>
      <?php endif; ?>

      <!-- ===================== SALES HISTORY / PERFORMANCE ===================== -->
      <section class="sd-section" id="sec-sales">
        <div class="sd-section-head"><h2><i class="fa-solid fa-chart-line"></i> <span data-sd="perfTitle">Product Performance</span></h2></div>
        <div id="sdPerfHighlights" class="sd-cards-grid"></div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdPerfTable"></table></div>
      </section>

      <!-- ===================== CUSTOMERS ===================== -->
      <section class="sd-section" id="sec-customers">
        <div class="sd-section-head"><h2><i class="fa-solid fa-users"></i> <span data-sd="custTitle">Customer Purchase History</span></h2></div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdCustomersTable"></table></div>
      </section>

      <!-- ===================== REVIEWS ===================== -->
      <section class="sd-section" id="sec-reviews">
        <div class="sd-section-head"><h2><i class="fa-solid fa-star"></i> <span data-sd="revTitle">Ratings &amp; Reviews</span></h2></div>
        <div class="sd-cards-grid" id="sdReviewSummary"></div>
        <div class="sd-toolbar">
          <select class="sd-select" id="sdReviewFilter" onchange="sdLoadReviews()">
            <option value="0" data-sd="allRatings">All Ratings</option>
            <option value="5">5 ★</option><option value="4">4 ★</option><option value="3">3 ★</option>
            <option value="2">2 ★</option><option value="1">1 ★</option>
          </select>
        </div>
        <div id="sdReviewsList"></div>
      </section>

      <!-- ===================== EARNINGS ===================== -->
      <section class="sd-section" id="sec-earnings">
        <div class="sd-section-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <h2><i class="fa-solid fa-wallet"></i> <span data-sd="earnTitle">Earnings &amp; Payouts</span></h2>
          <button class="sd-btn sd-btn-green" onclick="sdOpenWithdrawModal()"><i class="fa-solid fa-money-bill-transfer"></i> <span data-sd="requestWithdrawal">Request Withdrawal</span></button>
        </div>
        <div class="sd-cards-grid" id="sdEarningsCards"></div>
        <div class="sd-panel">
          <div class="sd-panel-title" style="display:flex;justify-content:space-between;align-items:center">
            <span><i class="fa-solid fa-building-columns"></i> <span data-sd="payoutDetailsTitle">Payout Details</span></span>
            <button class="sd-btn sd-btn-outline" onclick="sdOpenPayoutDetailsModal()"><i class="fa-solid fa-pen"></i> <span data-sd="edit">Edit</span></button>
          </div>
          <div id="sdPayoutDetails"></div>
        </div>
        <div class="sd-panel">
          <div class="sd-panel-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <span><i class="fa-solid fa-file-invoice"></i> <span data-sd="gstDetailsTitle">GST Information</span></span>
            <div style="display:flex;gap:8px">
              <button class="sd-btn sd-btn-outline" id="sdGstVerifyBtn" onclick="sdVerifyGstin()" style="display:none"><i class="fa-solid fa-shield-halved"></i> <span data-sd="verifyGstin">Verify GSTIN</span></button>
              <button class="sd-btn sd-btn-outline" onclick="sdOpenGstModal()"><i class="fa-solid fa-pen"></i> <span data-sd="editGstDetails">Edit GST Details</span></button>
            </div>
          </div>
          <div id="sdGstDetails"></div>
        </div>
        <div class="sd-panel">
          <div class="sd-panel-title" style="display:flex;justify-content:space-between;align-items:center">
            <span><i class="fa-solid fa-signature"></i> <span data-sd="invoiceSignatureTitle">Invoice Signature &amp; Stamp</span></span>
            <button class="sd-btn sd-btn-outline" onclick="sdOpenSignatureModal()"><i class="fa-solid fa-pen"></i> <span data-sd="edit">Edit</span></button>
          </div>
          <div class="sd-muted-line" style="font-size:12px;margin-bottom:8px" data-sd="invoiceSignatureHint">Shown as the Authorized Signatory on invoices your buyers receive.</div>
          <div id="sdSignatureStatus"></div>
        </div>
        <div class="sd-panel">
          <div class="sd-panel-title" style="display:flex;justify-content:space-between;align-items:center">
            <span><i class="fa-solid fa-clock-rotate-left"></i> <span data-sd="payoutHistoryTitle">Payout History</span></span>
            <a class="sd-btn sd-btn-outline" id="sdExportStatementLink" href="export_statement.php"><i class="fa-solid fa-download"></i> <span data-sd="downloadStatement">Download Statement</span></a>
          </div>
          <div class="sd-table-wrap"><table class="sd-table" id="sdPayoutsTable"></table></div>
        </div>
      </section>

      <!-- ===================== SALES INVOICES ===================== -->
      <section class="sd-section" id="sec-invoices">
        <div class="sd-section-head"><h2><i class="fa-solid fa-file-invoice-dollar"></i> <span data-sd="invoicesTitle">Sales Invoices</span></h2></div>
        <div class="sd-toolbar">
          <input class="sd-input" id="sdInvoiceSearch" data-sd-ph="invoiceSearchPh" placeholder="Search invoice no. / order id" oninput="sdDebounce(()=>sdLoadInvoices(1))">
          <select class="sd-select" id="sdInvoicePaymentStatus" onchange="sdLoadInvoices(1)">
            <option value="" data-sd="all">All Payment Statuses</option>
            <option value="Paid" data-sd="invPaid">Paid</option>
            <option value="Pending" data-sd="invPending">Pending</option>
            <option value="Unpaid" data-sd="invUnpaid">Unpaid</option>
          </select>
          <select class="sd-select" id="sdInvoiceSettlementStatus" onchange="sdLoadInvoices(1)">
            <option value="" data-sd="all">All Settlement Statuses</option>
            <option value="pending" data-sd="settlePending">Pending</option>
            <option value="available" data-sd="settleAvailable">Ready for Payout</option>
            <option value="paid" data-sd="settlePaid">Paid Out</option>
          </select>
          <input type="date" class="sd-input" id="sdInvoiceFrom" onchange="sdLoadInvoices(1)">
          <input type="date" class="sd-input" id="sdInvoiceTo" onchange="sdLoadInvoices(1)">
        </div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdInvoicesTable"></table></div>
        <div class="sd-pagination" id="sdInvoicesPager"></div>
      </section>

      <!-- ===================== NOTIFICATIONS ===================== -->
      <section class="sd-section" id="sec-notifications">
        <div class="sd-section-head">
          <h2><i class="fa-solid fa-bell"></i> <span data-sd="notifTitle">Notifications</span></h2>
          <button class="sd-btn sd-btn-outline" onclick="sdMarkAllRead()"><span data-sd="markAllRead">Mark all as read</span></button>
        </div>
        <div id="sdNotifList"></div>
      </section>

      <!-- ===================== MY ACCOUNT ===================== -->
      <section class="sd-section" id="sec-account">
        <div class="sd-section-head"><h2><i class="fa-solid fa-user-gear"></i> <span data-sd="accountTitle">My Account</span></h2></div>

        <div class="sd-panel">
          <div class="sd-panel-title"><i class="fa-solid fa-id-card"></i> <span data-sd="accountDetailsTitle">Account Details</span></div>
          <div class="sd-form-grid">
            <div class="sd-form-row"><label data-sd="lblFullName">Full Name</label><input class="sd-input" id="sdAccName" value="<?php echo htmlspecialchars($accountRow['full_name'] ?? ''); ?>"></div>
            <div class="sd-form-row"><label data-sd="lblMobile">Mobile Number</label><input class="sd-input" id="sdAccMobile" value="<?php echo htmlspecialchars($accountRow['mobile'] ?? ''); ?>"></div>
          </div>
          <div class="sd-form-grid">
            <div class="sd-form-row"><label data-sd="lblEmail">Email Address</label><input type="email" class="sd-input" id="sdAccEmail" value="<?php echo htmlspecialchars($accountRow['email'] ?? ''); ?>"></div>
            <div class="sd-form-row"><label data-sd="lblAddress">Address (Village, Taluka, District)</label><input class="sd-input" id="sdAccAddress" value="<?php echo htmlspecialchars($accountAddress); ?>"></div>
          </div>
          <div class="sd-modal-actions" style="justify-content:flex-start;margin-top:6px">
            <button class="sd-btn sd-btn-green" id="sdAccSaveBtn" onclick="sdSaveAccountDetails()"><i class="fa-solid fa-floppy-disk"></i> <span data-sd="save">Save</span></button>
          </div>
        </div>

        <div class="sd-panel">
          <div class="sd-panel-title"><i class="fa-solid fa-lock"></i> <span data-sd="changePasswordTitle">Change Password</span></div>
          <div class="sd-form-grid">
            <div class="sd-form-row"><label data-sd="lblCurrentPassword">Current Password</label><input type="password" class="sd-input" id="sdAccCurPwd" autocomplete="current-password"></div>
            <div></div>
            <div class="sd-form-row"><label data-sd="lblNewPassword">New Password</label><input type="password" class="sd-input" id="sdAccNewPwd" autocomplete="new-password"></div>
            <div class="sd-form-row"><label data-sd="lblConfirmPassword">Confirm New Password</label><input type="password" class="sd-input" id="sdAccConfirmPwd" autocomplete="new-password"></div>
          </div>
          <p style="font-size:11.5px;color:var(--sd-muted);margin-top:4px" data-sd="passwordHint">Leave these blank if you don't want to change your password.</p>
          <div class="sd-modal-actions" style="justify-content:flex-start;margin-top:6px">
            <button class="sd-btn sd-btn-outline" id="sdAccPwdBtn" onclick="sdChangePassword()"><i class="fa-solid fa-key"></i> <span data-sd="updatePassword">Update Password</span></button>
          </div>
        </div>
      </section>

      <!-- ===================== INACTIVE LISTINGS ===================== -->
      <section class="sd-section" id="sec-inactive">
        <div class="sd-section-head"><h2><i class="fa-solid fa-box-archive"></i> <span data-sd="inactiveTitle">Inactive Listings</span></h2></div>
        <div class="note-box-sd" style="background:var(--sd-green-light);border-left:4px solid var(--sd-green);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--sd-muted);margin-bottom:18px">
          <i class="fa-solid fa-circle-info"></i> <span data-sd="inactiveNote">Deactivated products and equipment stay here — nothing is ever permanently deleted while it has order, booking, or review history. Restore any listing to make it active again.</span>
        </div>

        <div class="sd-panel-title"><i class="fa-solid fa-box-open"></i> <span data-sd="inactiveProductsTitle">Inactive Products</span></div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdInactiveProductsTable"></table></div>
        <div class="sd-pagination" id="sdInactiveProductsPager" style="margin-bottom:26px"></div>

        <div class="sd-panel-title"><i class="fa-solid fa-tractor"></i> <span data-sd="inactiveEquipmentTitle">Inactive Equipment</span></div>
        <div class="sd-table-wrap"><table class="sd-table" id="sdInactiveEquipmentTable"></table></div>
        <div class="sd-pagination" id="sdInactiveEquipmentPager"></div>
      </section>

    </main>
  </div>
</div>

<!-- Withdraw / Request Payout Modal -->
<div class="sd-modal-overlay" id="sdWithdrawModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-money-bill-transfer" style="color:var(--sd-green)"></i> <span data-sd="requestWithdrawal">Request Withdrawal</span></h3>
    <p style="font-size:13px;color:var(--sd-muted);margin-bottom:14px">
      <span data-sd="availableBalance">Available Balance</span>: <strong id="sdWithdrawAvailable">₹0.00</strong>
    </p>
    <div class="sd-form-row"><label data-sd="withdrawAmount">Amount to Withdraw (₹)</label>
      <input type="number" min="1" step="0.01" class="sd-input" id="sdWithdrawAmount">
    </div>
    <div class="sd-form-row"><label data-sd="withdrawMethod">Payout Method</label>
      <select class="sd-select" id="sdWithdrawMethod">
        <option value="bank" data-sd="methodBank">Bank Transfer</option>
        <option value="upi" data-sd="methodUpi">UPI</option>
      </select>
    </div>
    <p class="hint-sd" style="font-size:11.5px;color:var(--sd-muted)" data-sd="minWithdrawNote">Minimum withdrawal is ₹200. The amount will be held from your available balance until an admin approves it.</p>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdWithdrawModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" id="sdWithdrawSubmitBtn" onclick="sdSubmitWithdraw()"><span data-sd="submitWithdraw">Submit Request</span></button>
    </div>
  </div>
</div>

<!-- Edit Payout Details Modal -->
<div class="sd-modal-overlay" id="sdPayoutDetailsModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-building-columns" style="color:var(--sd-green)"></i> <span data-sd="editPayoutDetails">Edit Payout Details</span></h3>
    <div class="sd-form-row"><label data-sd="lblBusinessName">Business Name</label><input class="sd-input" id="sdPdBusinessName"></div>
    <div class="sd-form-row"><label data-sd="lblBankAccName">Account Holder Name</label><input class="sd-input" id="sdPdBankAccName"></div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblBankAccNo">Bank Account Number</label><input class="sd-input" id="sdPdBankAccNo"></div>
      <div class="sd-form-row"><label data-sd="lblBankIfsc">IFSC Code</label><input class="sd-input" id="sdPdBankIfsc"></div>
    </div>
    <div class="sd-form-row"><label data-sd="lblUpiId">UPI ID</label><input class="sd-input" id="sdPdUpiId" placeholder="name@bank"></div>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdPayoutDetailsModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" id="sdPdSaveBtn" onclick="sdSubmitPayoutDetails()"><span data-sd="saveDetails">Save Details</span></button>
    </div>
  </div>
</div>

<!-- Edit GST Details Modal -->
<div class="sd-modal-overlay" id="sdGstModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-file-invoice" style="color:var(--sd-green)"></i> <span data-sd="editGstDetails">Edit GST Details</span></h3>
    <div class="sd-form-row"><label data-sd="lblLegalBusinessName">Legal Business Name</label><input class="sd-input" id="sdGstLegalName"></div>
    <div class="sd-form-grid">
      <div class="sd-form-row">
        <label data-sd="lblGstStatus">GST Registration Status</label>
        <select class="sd-select" id="sdGstStatus" onchange="sdToggleGstinRequired()">
          <option value="registered" data-sd="gstRegistered">Registered</option>
          <option value="unregistered" data-sd="gstUnregistered">Unregistered</option>
          <option value="composition" data-sd="gstComposition">Composition Scheme</option>
          <option value="not_applicable" data-sd="gstNotApplicable">Not Applicable</option>
        </select>
      </div>
      <div class="sd-form-row"><label data-sd="lblGstin">GSTIN</label><input class="sd-input" id="sdGstin" maxlength="15" style="text-transform:uppercase" placeholder="27XXXXXXXXXXXXZ"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblPan">PAN</label><input class="sd-input" id="sdGstPan" maxlength="10" style="text-transform:uppercase"></div>
      <div class="sd-form-row"><label data-sd="lblBusinessType">Business Type</label><input class="sd-input" id="sdGstBusinessType" placeholder="Proprietorship / Partnership / Pvt Ltd"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row">
        <label data-sd="lblState">State</label>
        <select class="sd-select" id="sdGstState" onchange="sdGstSyncStateCode()">
          <option value="">Select State</option>
          <?php foreach (gstin_state_codes() as $code => $name): ?>
            <option value="<?php echo htmlspecialchars($name); ?>" data-code="<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sd-form-row"><label data-sd="lblStateCode">State Code</label><input class="sd-input" id="sdGstStateCode" maxlength="2" readonly></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblCity">City</label><input class="sd-input" id="sdGstCity"></div>
      <div class="sd-form-row"><label data-sd="lblPincode">Pincode</label><input class="sd-input" id="sdGstPincode" maxlength="10"></div>
    </div>
    <div class="sd-form-row"><label data-sd="lblRegAddress">Registered Address</label><input class="sd-input" id="sdGstAddress"></div>
    <p class="hint-sd" id="sdGstErr" style="font-size:11.5px;color:#c0392b;display:none"></p>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdGstModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" id="sdGstSaveBtn" onclick="sdSubmitGstDetails()"><span data-sd="saveDetails">Save Details</span></button>
    </div>
  </div>
</div>
<div class="sd-modal-overlay" id="sdSignatureModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-signature" style="color:var(--sd-green)"></i> <span data-sd="editSignatureStamp">Edit Signature &amp; Stamp</span></h3>
    <div class="sd-form-grid">
      <div class="sd-form-row">
        <label data-sd="lblDigitalSignature">Digital Signature</label>
        <input type="file" class="sd-input" id="sdSigFile" accept=".png,.jpg,.jpeg,.webp">
      </div>
      <div class="sd-form-row">
        <label data-sd="lblOfficialStamp">Official Stamp</label>
        <input type="file" class="sd-input" id="sdStampFile" accept=".png,.jpg,.jpeg,.webp">
      </div>
    </div>
    <div class="sd-form-row"><label data-sd="lblAuthSignatoryName">Authorized Signatory Name</label><input class="sd-input" id="sdSigName"></div>
    <div class="sd-form-row"><label data-sd="lblDesignation">Designation</label><input class="sd-input" id="sdSigDesignation"></div>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdSignatureModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" id="sdSigSaveBtn" onclick="sdSubmitSignature()"><span data-sd="saveDetails">Save Details</span></button>
    </div>
  </div>
</div>

<!-- Restore Product Confirmation Modal -->
<div class="sd-modal-overlay" id="sdRestoreModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-rotate-left" style="color:var(--sd-green)"></i> <span data-sd="confirmRestore">Restore this product?</span></h3>
    <p style="font-size:13px;color:var(--sd-muted)" data-sd="restoreWarning">This will make the product active again and it will reappear in your storefront and active listings.</p>
    <input type="hidden" id="sdRestoreId">
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdRestoreModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" onclick="sdConfirmRestoreProduct()"><i class="fa-solid fa-rotate-left"></i> <span data-sd="restore">Restore</span></button>
    </div>
  </div>
 </div>
</div>

<!-- Activate Equipment Confirmation Modal -->
<div class="sd-modal-overlay" id="sdEquipActivateModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-rotate-left" style="color:var(--sd-green)"></i> <span data-sd="confirmActivateEquip">Activate this equipment?</span></h3>
    <p style="font-size:13px;color:var(--sd-muted)" data-sd="activateEquipWarning">This will make the equipment bookable again on the Rental Hub.</p>
    <input type="hidden" id="sdEquipActivateId">
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdEquipActivateModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" onclick="sdConfirmActivateEquipment()"><i class="fa-solid fa-rotate-left"></i> <span data-sd="restore">Restore</span></button>
    </div>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="sd-modal-overlay" id="sdEditModal">
  <div class="sd-modal sd-modal-wide">
    <h3><i class="fa-solid fa-pen"></i> <span data-sd="editProduct">Edit Product</span></h3>
    <input type="hidden" id="sdEditId">
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblName">Product Name</label><input class="sd-input" id="sdEditName"></div>
      <div class="sd-form-row"><label data-sd="lblPrice">Price (₹)</label><input type="number" class="sd-input" id="sdEditPrice"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblCategory">Category</label><input class="sd-input" id="sdEditCategory"></div>
      <div class="sd-form-row"><label data-sd="lblBrand">Brand</label><input class="sd-input" id="sdEditBrand"></div>
    </div>
    <div class="sd-form-row"><label data-sd="lblDesc">Description</label><textarea class="sd-input" id="sdEditDesc" rows="3"></textarea></div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblCondition">Condition</label>
        <select class="sd-select" id="sdEditCondition"><option value="new" data-sd="condNew">New</option><option value="used" data-sd="condUsed">Used</option></select>
      </div>
      <div class="sd-form-row"><label data-sd="lblDelivery">Delivery Available</label>
        <select class="sd-select" id="sdEditDelivery"><option value="1" data-sd="yes">Yes</option><option value="0" data-sd="no">No</option></select>
      </div>
    </div>
    <div class="sd-form-row"><label data-sd="lblLowStockLimit">Low-Stock Alert Level</label>
      <input type="number" min="0" class="sd-input" id="sdEditLowStockLimit">
    </div>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdEditModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" onclick="sdSaveEditProduct()"><span data-sd="save">Save</span></button>
    </div>
  </div>
</div>

<!-- Add/Edit Equipment Modal -->
<div class="sd-modal-overlay" id="sdEquipModal">
  <div class="sd-modal sd-modal-wide">
    <h3><i class="fa-solid fa-tractor"></i> <span id="sdEquipModalTitle" data-sd="addEquipment">Add Equipment</span></h3>
    <input type="hidden" id="sdEquipId">
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblEquipName">Equipment Name</label><input class="sd-input" id="sdEquipName"></div>
      <div class="sd-form-row"><label data-sd="lblEquipType">Type</label>
        <select class="sd-select" id="sdEquipType">
          <option value="tractor" data-sd="typeTractor">Tractor</option>
          <option value="harvester" data-sd="typeHarvester">Harvester</option>
          <option value="rotavator" data-sd="typeRotavator">Rotavator</option>
          <option value="plough" data-sd="typePlough">Plough</option>
          <option value="cultivator" data-sd="typeCultivator">Cultivator</option>
          <option value="sprayer" data-sd="typeSprayer">Sprayer</option>
          <option value="thresher" data-sd="typeThresher">Thresher</option>
          <option value="other" data-sd="typeOther">Other</option>
        </select>
      </div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblRentPerDay">Rent per Day (₹)</label><input type="number" class="sd-input" id="sdEquipRent"></div>
      <div class="sd-form-row"><label data-sd="lblHp">HP (Horsepower)</label><input class="sd-input" id="sdEquipHp"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblBrand">Brand</label><input class="sd-input" id="sdEquipBrand"></div>
      <div class="sd-form-row"><label data-sd="lblModel">Model</label><input class="sd-input" id="sdEquipModel"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblCondition">Condition</label>
        <select class="sd-select" id="sdEquipCondition">
          <option value="excellent" data-sd="condExcellent">Excellent</option>
          <option value="good" data-sd="condGood">Good</option>
          <option value="average" data-sd="condAverage">Average</option>
        </select>
      </div>
      <div class="sd-form-row"><label data-sd="lblSecurityDeposit">Security Deposit (₹)</label><input type="number" class="sd-input" id="sdEquipDeposit"></div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblOperatorAvailable">Operator Available</label>
        <select class="sd-select" id="sdEquipOperator"><option value="1" data-sd="yes">Yes</option><option value="0" data-sd="no">No</option></select>
      </div>
      <div class="sd-form-row"><label data-sd="lblFuelIncluded">Fuel Included</label>
        <select class="sd-select" id="sdEquipFuel"><option value="1" data-sd="yes">Yes</option><option value="0" data-sd="no">No</option></select>
      </div>
    </div>
    <div class="sd-form-grid">
      <div class="sd-form-row"><label data-sd="lblCity">City</label><input class="sd-input" id="sdEquipCity"></div>
      <div class="sd-form-row"><label data-sd="lblAvailability">Listing Active</label>
        <select class="sd-select" id="sdEquipAvailability"><option value="1" data-sd="yes">Yes</option><option value="0" data-sd="no">No</option></select>
      </div>
    </div>
    <div class="sd-form-row"><label data-sd="lblDesc">Description</label><textarea class="sd-input" id="sdEquipDesc" rows="3"></textarea></div>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdEquipModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" onclick="sdSaveEquipment()"><span data-sd="save">Save</span></button>
    </div>
  </div>
</div>

<!-- Deactivate Equipment Confirmation Modal -->
<div class="sd-modal-overlay" id="sdEquipDeleteModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--sd-danger)"></i> <span data-sd="confirmDeactivateEquip">Deactivate this equipment?</span></h3>
    <p style="font-size:13px;color:var(--sd-muted)" data-sd="deactivateEquipWarning">This will hide it from the Rental Hub and stop new bookings. Existing bookings stay in your history, and you can activate it again anytime from Inactive Listings.</p>
    <input type="hidden" id="sdEquipDeleteId">
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdEquipDeleteModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-danger" onclick="sdConfirmDeleteEquipment()"><span data-sd="deactivate">Deactivate</span></button>
    </div>
  </div>
</div>

<!-- Update Stock Modal -->
<div class="sd-modal-overlay" id="sdStockModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-warehouse"></i> <span data-sd="updateStock">Update Stock</span></h3>
    <input type="hidden" id="sdStockProdId">
    <div class="sd-form-row"><label data-sd="currentStock">Current Stock</label><input class="sd-input" id="sdStockCurrent" disabled></div>
    <div class="sd-form-row">
      <label data-sd="stockMode">Mode</label>
      <select class="sd-select" id="sdStockMode"><option value="add" data-sd="addUnits">Add Units</option><option value="set" data-sd="setExact">Set Exact Value</option></select>
    </div>
    <div class="sd-form-row"><label data-sd="quantity">Quantity</label><input type="number" class="sd-input" id="sdStockValue" min="0"></div>
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdStockModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-green" onclick="sdSaveStock()"><span data-sd="save">Save</span></button>
    </div>
  </div>
</div>

<!-- Deactivate Product Confirmation Modal -->
<div class="sd-modal-overlay" id="sdDeleteModal">
  <div class="sd-modal">
    <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--sd-danger)"></i> <span data-sd="confirmDeactivate">Deactivate this product?</span></h3>
    <p style="font-size:13px;color:var(--sd-muted)" data-sd="deactivateWarning">This will hide it from your storefront and active listings. Nothing is deleted — you can restore it anytime from Inactive Listings.</p>
    <input type="hidden" id="sdDeleteId">
    <div class="sd-modal-actions">
      <button class="sd-btn sd-btn-outline" onclick="sdCloseModal('sdDeleteModal')"><span data-sd="cancel">Cancel</span></button>
      <button class="sd-btn sd-btn-danger" onclick="sdConfirmDelete()"><span data-sd="deactivate">Deactivate</span></button>
    </div>
  </div>
</div>

<!-- Invoice Modal -->
<div class="sd-modal-overlay" id="sdInvoiceModal">
  <div class="sd-modal" id="sdInvoiceContent"></div>
</div>

<!-- Customer Contact Modal -->
<div class="sd-modal-overlay" id="sdContactModal">
  <div class="sd-modal" id="sdContactContent"></div>
</div>

<div class="sd-toast" id="sdToast"><i class="fa-solid fa-circle-check"></i> <span id="sdToastMsg"></span></div>

<script>
const SD_CSRF = <?php echo json_encode($csrfToken); ?>;
const SD_SELLER_NAME = <?php echo json_encode($sellerName !== '' ? $sellerName : 'Seller'); ?>;
</script>
<script src="dashboard.js?v=<?php echo @filemtime(__DIR__ . '/dashboard.js') ?: time(); ?>"></script>
<script>
// Sidebar + main content are both sticky and independently scrollable.
// This measures the real space left below whatever sits above the shell
// (site header, banner, etc.) so they always fit the screen exactly —
// no clipped top, no clipped bottom, no double page-scroll.
(function () {
  function sdFitShell() {
    var main = document.querySelector('.sd-main');
    var sidebar = document.querySelector('.sd-sidebar');
    if (!main) return;
    var top = main.getBoundingClientRect().top;
    var avail = Math.max(window.innerHeight - Math.max(top, 0), 320) + 'px';
    main.style.height = avail;
    if (sidebar) sidebar.style.height = avail;
  }
  window.addEventListener('load', sdFitShell);
  window.addEventListener('resize', sdFitShell);
  document.addEventListener('DOMContentLoaded', sdFitShell);
  setTimeout(sdFitShell, 300); // catch late-loading header/banner content
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
