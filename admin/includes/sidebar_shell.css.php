<?php
// =====================================================================
// admin/includes/sidebar_shell.css.php
// Shared sidebar-nav / profile-chip / dropdown CSS used by BOTH:
//   - admin/index.php (tab-based dashboard shell)
//   - admin/includes/team_layout_top.php (multi-page Team Mgmt shell)
// Pulled in via <?php include ...; ?> inside each page's <style> block
// so colors/sizes/animations here only ever need to change in one place.
// Requires the including page to already define the --accent, --primary
// etc. CSS variables in its own :root before this is included.
// =====================================================================
?>
.sidebar-nav::-webkit-scrollbar{width:5px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.18);border-radius:10px}
.sidebar-nav::-webkit-scrollbar-track{background:transparent}
.sidebar-nav{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.18) transparent}
.nav-item{
    display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,0.82);
    cursor:pointer;font-size:13.5px;margin-bottom:2px;transition:background .2s ease, transform .15s ease, padding-left .2s ease;
    animation:navIn .4s ease both;text-decoration:none;
}
.nav-item:nth-child(1){animation-delay:.03s} .nav-item:nth-child(2){animation-delay:.06s}
.nav-item:nth-child(3){animation-delay:.09s} .nav-item:nth-child(4){animation-delay:.12s}
.nav-item:nth-child(5){animation-delay:.15s} .nav-item:nth-child(6){animation-delay:.18s}
.nav-item:nth-child(7){animation-delay:.21s} .nav-item:nth-child(8){animation-delay:.24s}
@keyframes navIn{ from{opacity:0; transform:translateX(-10px)} to{opacity:1; transform:translateX(0)} }
.nav-item i{width:16px;text-align:center;transition:transform .2s ease}
.nav-item:hover{background:rgba(255,255,255,0.08); padding-left:18px;}
.nav-item:hover i{transform:scale(1.15)}
.nav-item.active{background:rgba(255,255,255,0.16);color:#fff;font-weight:600}
.nav-section-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.45);padding:14px 14px 6px}
.sidebar-foot{padding:16px;border-top:1px solid rgba(255,255,255,0.12); position:relative;}
.admin-chip{
    display:flex;align-items:center;gap:10px;font-size:12.5px;
    cursor:pointer; padding:8px; border-radius:10px; transition:background .2s ease;
    position:relative;
}
.admin-chip:hover{background:rgba(255,255,255,0.08)}
.admin-chip .av{width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.admin-chip .chip-info{flex:1}
.admin-chip .chevron{transition:transform .2s ease; opacity:.7; font-size:11px;}
.admin-chip.open .chevron{transform:rotate(180deg)}
.profile-dropdown{
    position:absolute; bottom:calc(100% + 8px); left:16px; right:16px;
    background:#16241f; border:1px solid rgba(255,255,255,0.12); border-radius:10px;
    padding:6px; box-shadow:0 10px 28px rgba(0,0,0,0.4);
    display:none; flex-direction:column; gap:2px;
    z-index:20;
}
.profile-dropdown.open{ display:flex; animation:dropUp .2s cubic-bezier(.22,.8,.36,1) both; }
@keyframes dropUp{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
.profile-dropdown a{
    display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:7px;
    color:rgba(255,255,255,0.85); text-decoration:none; font-size:13px;
    transition:background .15s ease, color .15s ease, padding-left .15s ease;
}
.profile-dropdown a:hover{background:rgba(255,255,255,0.1); color:#fff; padding-left:14px;}
.profile-dropdown a i{width:16px; text-align:center;}
.profile-dropdown a.danger:hover{background:rgba(155,59,55,0.25); color:#f0a09c;}
.profile-dropdown .divider{height:1px; background:rgba(255,255,255,0.1); margin:4px 2px;}
