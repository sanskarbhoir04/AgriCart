<?php
// =====================================================================
// admin/includes/team_layout_top.php
// Shared page shell (sidebar + topbar) for the new Team Management
// section pages (team_members.php, roles.php, activity_logs.php, ...).
// Keeps the same AgriCart Admin color palette / fonts as admin/index.php
// without duplicating its full 3000+ line stylesheet. Include this at
// the top of a page (after admin_guard.php + requirePermission), set
// $pageTitle and $activeTeamTab first, then include team_layout_bottom.php
// at the end.
// =====================================================================
$pageTitle       = $pageTitle ?? 'Team Management';
$activeTeamTab   = $activeTeamTab ?? '';
$currentAdminName = $_SESSION['admin_name'] ?? 'Admin';
$currentRoleName  = $_SESSION['admin_role_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> — AgriCart Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Shared reusable Action-menu ("⋮") component — see includes/action_menu.php -->
<link rel="stylesheet" href="assets/css/action-menu.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/action-menu.css') ?: time(); ?>">
<style>
:root{
    --primary:#2F4F44; --primary-dark:#1B2F29; --accent:#FFC107;
    --bg-soft:#EEF1EC; --text:#26292B; --muted:#68706B;
    --danger:#9B3B37; --danger-bg:#F5E8E7; --success:#2E7D32; --success-bg:#E8F3E8;
    --warn:#B8860B; --warn-bg:#FBF3E0; --border:#E3E7E2;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',sans-serif;background:var(--bg-soft);color:var(--text);min-height:100vh}
a{text-decoration:none;color:inherit}
.admin-shell{display:flex;min-height:100vh}
.sidebar{
    width:250px;background:linear-gradient(180deg,var(--primary-dark),var(--primary));
    color:#fff;flex-shrink:0;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;
}
.sidebar-brand{display:flex;align-items:center;padding:20px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.12)}
.brand-badge{display:inline-flex;align-items:center;gap:11px}
.brand-badge .fern{flex-shrink:0}
.brand-badge .txt{font-size:24px;font-weight:800;letter-spacing:-0.4px}
.brand-badge .txt .agri{color:#fff}
.brand-badge .txt .cart{color:#5A9802;margin-left:1px}
.sidebar-nav{flex:1;min-height:0;padding:14px 12px;overflow-y:auto;overflow-x:hidden}
.sidebar-nav::-webkit-scrollbar{width:5px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:10px}
.sidebar-nav::-webkit-scrollbar-track{background:transparent}
.sidebar-nav{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}

/* Themed scrollbar for everything else (light-background content/table
   areas) so wide tables etc. don't fall back to the fat native gray bar. */
.content, .content *{scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.content ::-webkit-scrollbar{height:8px;width:8px}
.content ::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}
.content ::-webkit-scrollbar-thumb:hover{background:var(--muted)}
.content ::-webkit-scrollbar-track{background:transparent}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;font-size:13.5px;color:rgba(255,255,255,.82);cursor:pointer;margin-bottom:2px;transition:.15s;text-decoration:none;animation:navIn .4s ease both}
.nav-item:nth-child(1){animation-delay:.03s} .nav-item:nth-child(2){animation-delay:.06s}
.nav-item:nth-child(3){animation-delay:.09s} .nav-item:nth-child(4){animation-delay:.12s}
.nav-item:nth-child(5){animation-delay:.15s} .nav-item:nth-child(6){animation-delay:.18s}
.nav-item:nth-child(7){animation-delay:.21s} .nav-item:nth-child(8){animation-delay:.24s}
@keyframes navIn{ from{opacity:0; transform:translateX(-10px)} to{opacity:1; transform:translateX(0)} }
.nav-item i{width:16px;text-align:center}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.16);color:#fff;font-weight:600}
.nav-section-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.45);padding:14px 14px 6px}
.sidebar-foot{padding:16px;border-top:1px solid rgba(255,255,255,.12);position:relative}
.admin-chip{display:flex;align-items:center;gap:10px;font-size:12.5px;cursor:pointer;padding:8px;border-radius:10px;transition:background .2s ease;position:relative}
.admin-chip:hover{background:rgba(255,255,255,0.08)}
.admin-chip .av{width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.admin-chip .chip-info{flex:1;color:#fff}
.admin-chip .chevron{transition:transform .2s ease;opacity:.7;font-size:11px;color:#fff}
.admin-chip.open .chevron{transform:rotate(180deg)}
.profile-dropdown{position:absolute;bottom:calc(100% + 8px);left:16px;right:16px;background:#16241f;border:1px solid rgba(255,255,255,0.12);border-radius:10px;padding:6px;box-shadow:0 10px 28px rgba(0,0,0,0.4);display:none;flex-direction:column;gap:2px;z-index:20}
.profile-dropdown.open{display:flex;animation:dropUp .2s cubic-bezier(.22,.8,.36,1) both}
@keyframes dropUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.profile-dropdown a{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:7px;color:rgba(255,255,255,0.85);text-decoration:none;font-size:13px;transition:background .15s ease,color .15s ease,padding-left .15s ease}
.profile-dropdown a:hover{background:rgba(255,255,255,0.1);color:#fff;padding-left:14px}
.profile-dropdown a i{width:16px;text-align:center}
.profile-dropdown a.danger:hover{background:rgba(155,59,55,0.25);color:#f0a09c}
.profile-dropdown .divider{height:1px;background:rgba(255,255,255,0.1);margin:4px 2px}
.main{flex:1;margin-left:250px;min-height:100vh;display:flex;flex-direction:column}
/* Page header: matches the AgriCart Dashboard's own header pattern
   (admin/index.php .topbar/.hello-greet/.stat-card) — Title + one-line
   description, no separate sticky white bar/role-pill; role + logout
   live in the sidebar profile chip only, same as the Dashboard. */
.content{padding:28px 32px 60px;flex:1}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px}
.page-header .titles{display:flex;align-items:flex-start;gap:12px}
.crumb{font-size:12px;font-weight:600;color:var(--primary);margin-bottom:2px}
.crumb a{color:var(--primary)}
.page-header h1{font-size:19px;font-weight:700;margin:0}
.page-header .sub{color:var(--muted);font-size:13px;margin-top:2px}
.page-header .action{font-size:13px;color:var(--primary);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:6px;transition:transform .15s ease, color .15s ease}
.page-header .action:hover{transform:translateX(3px); color:var(--primary-dark)}
.notif-bell-wrap{position:relative}
.gs-search-wrap{position:relative;flex:1;max-width:360px;margin:0 12px}
.gs-search-box{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:8px 12px;font-size:13px;color:var(--muted)}
.gs-search-box input{border:none;outline:none;flex:1;font-size:13px;color:var(--text);background:transparent}
.gs-results{display:none;position:absolute;left:0;top:44px;width:100%;min-width:300px;max-height:420px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:200}
.gs-results.open{display:block}
.gs-cat{padding:8px 14px 4px;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700}
.gs-item{display:block;padding:9px 14px;text-decoration:none;color:inherit;font-size:13px;border-bottom:1px solid var(--border)}
.gs-item:hover{background:var(--bg-soft)}
@media (max-width:900px){ .gs-search-wrap{display:none} }
.notif-bell{position:relative;background:#fff;border:1px solid var(--border);border-radius:10px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:15px;color:var(--text)}
.notif-bell:hover{background:var(--bg-soft)}
.notif-badge{position:absolute;top:-5px;right:-5px;background:#E53935;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 3px}
.notif-dropdown{display:none;position:absolute;right:0;top:46px;width:340px;max-width:88vw;max-height:420px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:200}
.notif-dropdown.open{display:block}
.notif-dropdown-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border);font-weight:700;font-size:13.5px}
.notif-dropdown-head a{font-size:11.5px;font-weight:600;color:var(--primary);text-decoration:none}
.notif-item{display:block;padding:11px 14px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit}
.notif-item:hover{background:var(--bg-soft)}
.notif-item.unread{background:#F0F7EE}
.notif-item .t{font-size:12.5px;font-weight:700}
.notif-item .m{font-size:11.5px;color:var(--muted);margin-top:2px}
.notif-item .d{font-size:10.5px;color:var(--muted);margin-top:4px}
@media (max-width:768px){
    .notif-dropdown{position:fixed;right:10px;left:10px;top:64px;width:auto;max-width:none}
}
.card{background:#fff;border-radius:14px;border:1px solid var(--border);padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.card-head h2{font-size:16px}
.btn{display:inline-flex;align-items:center;gap:7px;background:var(--primary);color:#fff;border:none;padding:10px 18px;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;transition:.15s}
.btn:hover{background:var(--primary-dark)}
.btn.outline{background:transparent;color:var(--primary);border:1.5px solid var(--primary)}
.btn.danger{background:var(--danger)}.btn.danger:hover{background:#7a2e2b}
.btn.success{background:var(--success)}.btn.success:hover{background:#245c27}
.btn.sm{padding:6px 12px;font-size:12px}
.btn:disabled{opacity:.5;cursor:not-allowed}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:var(--muted);font-weight:600;padding:10px 12px;border-bottom:1.5px solid var(--border);white-space:nowrap;font-size:11.5px;text-transform:uppercase;letter-spacing:.03em}
td{padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover{background:var(--bg-soft)}
/* Shared "StatCard" component — ported verbatim from the Dashboard's
   .stats-row/.stat-card (admin/index.php) so every Admin page with
   summary numbers (Accounts, Seller Payouts, Payment Verification, ...)
   renders the exact same round colored-icon card instead of each page
   inventing its own variant (section 5 / section 22 of the design spec). */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
.stat-card{position:relative;overflow:hidden;background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 1px 3px rgba(20,30,25,0.06);display:flex;align-items:center;gap:14px;transition:transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s ease;animation:cardIn .45s ease both}
a.stat-card,.stat-card[onclick]{cursor:pointer}
.stat-card:hover{transform:translateY(-5px);box-shadow:0 12px 26px rgba(0,0,0,0.12)}
.stats-row .stat-card:nth-child(1){animation-delay:.05s} .stats-row .stat-card:nth-child(2){animation-delay:.1s}
.stats-row .stat-card:nth-child(3){animation-delay:.15s} .stats-row .stat-card:nth-child(4){animation-delay:.2s}
.stats-row .stat-card:nth-child(5){animation-delay:.25s}
@keyframes cardIn{ from{opacity:0; transform:translateY(14px) scale(.97)} to{opacity:1; transform:translateY(0) scale(1)} }
.stat-card .icn{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0;transition:transform .3s cubic-bezier(.34,1.56,.64,1);position:relative;z-index:1}
.stat-card .icn::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:inherit;opacity:.14;z-index:-1}
.stat-card:hover .icn{transform:scale(1.12) rotate(-6deg)}
.stat-card>div{position:relative;z-index:1}
.stat-card .val{font-size:21px;font-weight:800;line-height:1.1}
.stat-card .lbl{font-size:12px;color:var(--muted)}
.stat-card .trend{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;margin-top:3px}
.stat-card .trend.up{color:#2E7D46}
.stat-card .trend.down{color:var(--danger)}
.stat-card .trend span{color:var(--muted);font-weight:500}
.dash-kebab{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:15px;flex-shrink:0;cursor:default;position:absolute;top:14px;right:14px;z-index:1}
.tag{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:600}
.tag.active,.tag.approved{background:var(--success-bg);color:var(--success)}
.tag.inactive{background:#EEE;color:#777}
.tag.suspended,.tag.rejected{background:var(--danger-bg);color:var(--danger)}
.tag.expired{background:var(--warn-bg);color:var(--warn)}
.tag.pending{background:#E3ECF5;color:#2C5B8F}
.role-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:600;background:var(--bg-soft);color:var(--primary)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group.full{grid-column:1/-1}
label{display:block;font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:6px}
input[type=text],input[type=email],input[type=password],input[type=tel],input[type=date],input[type=number],select,textarea{
    width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:13.5px;color:var(--text);background:#fff;
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}
.hint{font-size:11.5px;color:var(--muted);margin-top:5px}
.err{font-size:11.5px;color:var(--danger);margin-top:5px}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.filters select,.filters input{width:auto;min-width:150px}
.empty-state{text-align:center;padding:40px 20px;color:var(--muted);font-size:13.5px}
.empty-state i{font-size:34px;color:var(--border);display:block;margin-bottom:10px}
.pagination{display:flex;gap:6px;justify-content:flex-end;margin-top:14px;flex-wrap:wrap}
.pagination a,.pagination span{padding:6px 12px;border-radius:8px;font-size:12.5px;border:1px solid var(--border);color:var(--text)}
.pagination a:hover{background:var(--bg-soft)}
.pagination .active{background:var(--primary);color:#fff;border-color:var(--primary)}
.perm-group{border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:12px}
.perm-group h4{font-size:13px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between}
.perm-group h4 .mini-actions{display:flex;gap:8px}
.perm-group h4 .mini-actions a{font-size:11px;color:var(--primary);font-weight:600;cursor:pointer}
.perm-checks{display:flex;flex-wrap:wrap;gap:14px}
.perm-check{display:flex;align-items:center;gap:6px;font-size:12.5px}
.toast-wrap{position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px}
.toast{background:#fff;border-left:4px solid var(--success);border-radius:8px;padding:12px 16px;box-shadow:0 8px 24px rgba(0,0,0,.12);font-size:13px;min-width:240px;animation:toastIn .2s ease}
.toast.error{border-left-color:var(--danger)}
@keyframes toastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.modal-overlay{position:fixed;inset:0;background:rgba(27,47,41,.5);display:none;align-items:center;justify-content:center;z-index:999;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:16px;max-width:460px;width:100%;padding:26px;max-height:88vh;overflow-y:auto}
.modal-box h3{font-size:16px;margin-bottom:6px}
.modal-box p{font-size:13px;color:var(--muted);margin-bottom:18px}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
.avatar-circle{width:34px;height:34px;border-radius:50%;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;overflow:hidden}
.avatar-circle img{width:100%;height:100%;object-fit:cover}
.menu-toggle{display:none;background:none;border:none;font-size:20px;color:var(--primary);cursor:pointer}
/* Wide report/inventory/team tables scroll inside themselves instead of
   breaking the page layout (wrapper div added automatically by the
   script in team_layout_bottom.php). */
.agri-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:12px}
.agri-table-wrap table{width:100%;border-collapse:collapse}
.agri-table-wrap thead th,.agri-table-wrap th{position:sticky;top:0;z-index:2}
@media(max-width:600px){.agri-table-wrap table{min-width:480px}.agri-table-wrap th,.agri-table-wrap td{padding:8px 10px;font-size:12.5px}}
@media(max-width:900px){
    .sidebar{left:-260px;transition:left .2s ease}
    .sidebar.open{left:0}
    .main{margin-left:0}
    .form-grid{grid-template-columns:1fr}
    .menu-toggle{display:block}
    .content{padding:20px 16px 40px}
    .stats-row{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
}
/* Shared "kebab" (three-dot) actions menu — replaces rows of separate
   action buttons in table Action/Actions columns across the admin
   panel (accounts, team members, invoices, payouts, roles, etc). Same
   pattern as admin/inventory.php's product table actions. */
.action-menu-wrap{position:relative;display:inline-block}
.kebab-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover{background:var(--bg-soft);border-color:var(--primary)}
.action-menu{position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.14);min-width:200px;padding:6px;z-index:60;display:none}
.action-menu.open{display:block}
.action-menu button,.action-menu a{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:none;background:none;text-align:left;font-size:13px;border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none;white-space:nowrap}
.action-menu button:hover,.action-menu a:hover{background:var(--bg-soft)}
.action-menu i{width:16px;text-align:center;color:var(--muted)}
.action-menu hr{border:none;border-top:1px solid var(--border);margin:6px 2px}
.action-menu .menu-danger{color:#c0392b}
.action-menu .menu-danger i{color:#c0392b}
.action-menu .menu-success{color:#1a7f37}
.action-menu .menu-success i{color:#1a7f37}
</style>
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<script>
// Auto-attach the CSRF token to every same-origin fetch() POST/PUT/DELETE/PATCH,
// so existing admin AJAX calls to *_action.php work without per-call edits.
(function () {
  var token = document.querySelector('meta[name="csrf-token"]').content;
  var origFetch = window.fetch;
  window.fetch = function (input, init) {
    init = init || {};
    var method = (init.method || 'GET').toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      init.headers = new Headers(init.headers || {});
      if (!init.headers.has('X-CSRF-Token')) init.headers.set('X-CSRF-Token', token);
      if (init.body instanceof FormData && !init.body.has('csrf_token')) init.body.append('csrf_token', token);
    }
    return origFetch(input, init);
  };
  // Also auto-fill any plain <form method="post"> that doesn't already have a csrf field.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.tagName === 'FORM' && form.method && form.method.toLowerCase() === 'post' && !form.querySelector('input[name="csrf_token"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'csrf_token'; inp.value = token;
      form.appendChild(inp);
    }
  }, true);
})();
</script>
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-badge">
                <img src="../assets/images/agricart-logo.png" alt="AgriCart" class="fern" style="width:56px;height:56px;object-fit:contain;border-radius:50%;flex-shrink:0">
                <span class="txt"><span class="agri">Agri</span><span class="cart">Cart</span></span>
            </div>
        </div>
        <nav class="sidebar-nav" id="sidebarNav">
            <?php $sidebarIsIndex = false; include __DIR__ . '/sidebar_nav.php'; ?>
        </nav>
        <script>
        // Keep the sidebar scrolled to the current page's active menu item
        // instead of resetting to the top (Dashboard) on every navigation.
        (function () {
            function scrollToActive() {
                var nav = document.getElementById('sidebarNav');
                var active = nav && nav.querySelector('.nav-item.active');
                if (!nav || !active) return;
                var target = active.offsetTop - (nav.clientHeight / 2) + (active.clientHeight / 2);
                nav.scrollTop = Math.max(0, target);
            }
            // Run once ASAP, then again after everything (images, fonts) has
            // finished loading and settled, since layout shifts after the
            // first pass were pushing the scroll position back up.
            scrollToActive();
            window.addEventListener('load', function () {
                requestAnimationFrame(function () { requestAnimationFrame(scrollToActive); });
            });
        })();
        </script>
        <div class="sidebar-foot">
            <div class="admin-chip" id="profileTrigger" onclick="toggleProfileMenu()">
                <div class="av"><?php echo strtoupper(substr($currentAdminName,0,1)); ?></div>
                <div class="chip-info"><?php echo htmlspecialchars($currentAdminName); ?><br><span style="opacity:0.7"><?php echo htmlspecialchars($currentRoleName ?: 'Administrator'); ?></span></div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="#" onclick="editMyName(event)"><i class="fa-solid fa-pen"></i> Edit My Name</a>
                <a href="switch_to_user.php"><i class="fa-solid fa-user"></i> Login as User</a>
                <div class="divider"></div>
                <a href="logout.php" class="danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </aside>

    <div class="main">
        <div class="content">
        <div class="page-header">
            <div class="titles">
                <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <div class="crumb">
                    <?php if (!empty($pageBreadcrumb) && is_array($pageBreadcrumb)): ?>
                        <a href="index.php">Dashboard</a>
                        <?php foreach ($pageBreadcrumb as $bc): ?>
                            / <?php echo !empty($bc['url']) ? '<a href="' . htmlspecialchars($bc['url']) . '">' . htmlspecialchars($bc['label']) . '</a>' : htmlspecialchars($bc['label']); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a href="index.php">Dashboard</a> / <?php echo htmlspecialchars($pageTitle); ?>
                    <?php endif; ?>
                    </div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <?php if (!empty($pageSubtitle)): ?><div class="sub"><?php echo htmlspecialchars($pageSubtitle); ?></div><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($pageAction)): ?>
            <a href="<?php echo htmlspecialchars($pageAction['url']); ?>" class="action"<?php echo !empty($pageAction['newTab']) ? ' target="_blank"' : ''; ?>><i class="fa-solid <?php echo htmlspecialchars($pageAction['icon'] ?? 'fa-arrow-right'); ?>"></i> <?php echo htmlspecialchars($pageAction['label']); ?></a>
            <?php endif; ?>
            <div class="gs-search-wrap">
                <div class="gs-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="gsInput" placeholder="Search sellers, orders, buyers, GSTIN…" autocomplete="off" oninput="gsHandleInput()" onfocus="gsHandleInput()">
                </div>
                <div class="gs-results" id="gsResults"></div>
            </div>
            <div class="notif-bell-wrap">
                <button class="notif-bell" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-head">
                        <span>Notifications</span>
                        <a href="#" onclick="markAllNotifsRead(event)">Mark all read</a>
                    </div>
                    <div class="notif-list" id="notifList"><div class="empty-state" style="padding:24px 12px">Loading…</div></div>
                </div>
            </div>
        </div>
