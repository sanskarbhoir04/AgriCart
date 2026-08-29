        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<!-- Shared reusable Action-menu ("⋮") engine — see includes/action_menu.php -->
<script src="assets/js/action-menu.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/action-menu.js') ?: time(); ?>"></script>
<script>
function showToast(message, isError){
    const wrap = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.textContent = message;
    wrap.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(), 300); }, 3200);
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
// Close modal on backdrop click.
document.querySelectorAll('.modal-overlay').forEach(function(ov){
    ov.addEventListener('click', function(e){ if (e.target === ov) ov.classList.remove('open'); });
});

/* ---- Profile dropdown (Edit My Name / Login as User / Logout) — same as the dashboard sidebar ---- */
function toggleProfileMenu(){
    document.getElementById('profileTrigger').classList.toggle('open');
    document.getElementById('profileDropdown').classList.toggle('open');
}
function editMyName(e){
    e.preventDefault();
    const current = <?php echo json_encode($currentAdminName); ?>;
    const name = prompt('Enter your name:', current === 'Admin' ? '' : current);
    if (name === null) return;
    const trimmed = name.trim();
    if (!trimmed) { alert('Name cannot be empty.'); return; }
    fetch('<?php echo (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'actions') ? '../' : ''; ?>update_admin_name.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ full_name: trimmed })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.error || 'Could not update name.'); }
    })
    .catch(() => alert('Network error — please try again.'));
}
document.addEventListener('click', function(e){
    const trigger = document.getElementById('profileTrigger');
    const dropdown = document.getElementById('profileDropdown');
    if (!trigger || !dropdown) return;
    if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        trigger.classList.remove('open');
        dropdown.classList.remove('open');
    }
});

/* ---- Global Search (spec §16) ---- */
let gsDebounce = null;
function gsHandleInput(){
    clearTimeout(gsDebounce);
    const q = document.getElementById('gsInput').value.trim();
    const box = document.getElementById('gsResults');
    if (q.length < 2) { box.classList.remove('open'); return; }
    gsDebounce = setTimeout(function(){
        fetch('global_search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(d => {
                if (!d.success) { box.innerHTML = '<div class="empty-state" style="padding:16px">' + (d.error || 'No matches.') + '</div>'; box.classList.add('open'); return; }
                const cats = Object.keys(d.results);
                if (!cats.length) {
                    box.innerHTML = '<div class="empty-state" style="padding:16px"><i class="fa-solid fa-magnifying-glass"></i> No matches for "' + q.replace(/</g,'&lt;') + '".</div>';
                } else {
                    box.innerHTML = cats.map(function(cat){
                        return '<div class="gs-cat">' + cat + '</div>' + d.results[cat].map(function(item){
                            return '<a href="' + item.url + '" class="gs-item">' + item.label.replace(/</g,'&lt;') + '</a>';
                        }).join('');
                    }).join('');
                }
                box.classList.add('open');
            })
            .catch(() => {});
    }, 250);
}
document.addEventListener('click', function(e){
    const wrap = document.querySelector('.gs-search-wrap');
    if (!wrap) return;
    if (!wrap.contains(e.target)) { document.getElementById('gsResults')?.classList.remove('open'); }
});

/* ---- Notification Center bell (spec §14) ---- */
const NOTIF_ICONS = {
    new_order: 'fa-cart-shopping', new_seller: 'fa-store', seller_verification: 'fa-user-shield',
    gst_verification: 'fa-file-shield', payment_received: 'fa-money-bill-wave', payout_request: 'fa-hand-holding-dollar',
    refund_request: 'fa-rotate-left', low_stock: 'fa-triangle-exclamation', new_complaint: 'fa-envelope-open-text',
    system_alert: 'fa-circle-exclamation'
};
function notifTimeAgo(iso){
    const d = new Date(iso.replace(' ', 'T'));
    const diffMin = Math.max(0, Math.floor((Date.now() - d.getTime()) / 60000));
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return diffMin + 'm ago';
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return diffHr + 'h ago';
    return Math.floor(diffHr / 24) + 'd ago';
}
function loadNotifications(){
    fetch('notifications_action.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const badge = document.getElementById('notifBadge');
            if (d.unread_count > 0) { badge.style.display = 'flex'; badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count; }
            else { badge.style.display = 'none'; }
            const list = document.getElementById('notifList');
            if (!d.notifications.length) {
                list.innerHTML = '<div class="empty-state" style="padding:24px 12px"><i class="fa-solid fa-bell-slash"></i> No notifications yet.</div>';
                return;
            }
            list.innerHTML = d.notifications.map(function(n){
                const icon = NOTIF_ICONS[n.type] || 'fa-bell';
                const href = n.link || '#';
                return '<a href="' + href.replace(/"/g, '&quot;') + '" class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" onclick="markNotifRead(' + n.id + ')">' +
                    '<div class="t"><i class="fa-solid ' + icon + '" style="margin-right:6px;color:var(--primary)"></i>' + n.title.replace(/</g,'&lt;') + '</div>' +
                    (n.message ? '<div class="m">' + n.message.replace(/</g,'&lt;') + '</div>' : '') +
                    '<div class="d">' + notifTimeAgo(n.created_at) + '</div></a>';
            }).join('');
        })
        .catch(() => {});
}
function toggleNotifDropdown(){
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) { loadNotifications(); }
}
function markNotifRead(id){
    fetch('notifications_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'mark_read', id }) }).catch(() => {});
}
function markAllNotifsRead(e){
    e.preventDefault();
    fetch('notifications_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'mark_all_read' }) })
        .then(() => loadNotifications())
        .catch(() => {});
}
document.addEventListener('click', function(e){
    const wrap = document.querySelector('.notif-bell-wrap');
    if (!wrap) return;
    if (!wrap.contains(e.target)) { document.getElementById('notifDropdown')?.classList.remove('open'); }
});
if (document.getElementById('notifBadge')) {
    loadNotifications();
    setInterval(loadNotifications, 60000); // refresh unread count every minute
}

/* ---- Responsive tables: wrap every <table> so wide report/inventory
   tables scroll horizontally inside themselves on tablets/phones instead
   of breaking the page layout, and pick up a sticky header (see
   .agri-table-wrap in assets/css/responsive.css). Also catches tables
   rendered later via AJAX (report filters, pagination, etc). ---- */
(function () {
    var SKIP_PATTERN = /wrap|scroll|responsive/i;
    function wrapTable(table) {
        if (table.closest('.agri-table-wrap')) return;
        var parent = table.parentElement;
        if (!parent) return;
        var classes = parent.className ? parent.className.split(/\s+/) : [];
        for (var i = 0; i < classes.length; i++) { if (SKIP_PATTERN.test(classes[i])) return; }
        var wrap = document.createElement('div');
        wrap.className = 'agri-table-wrap';
        parent.insertBefore(wrap, table);
        wrap.appendChild(table);
    }
    function wrapAll(root) { (root || document).querySelectorAll('table').forEach(wrapTable); }
    wrapAll();
    new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'TABLE') wrapTable(node);
                else if (node.querySelectorAll) wrapAll(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
<!-- Global smart form validation: auto-scrolls to + focuses the first
     invalid field on any form on this page. See assets/js/form-scroll-validate.js -->
<script src="../assets/js/form-scroll-validate.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/form-scroll-validate.js') ?: time(); ?>"></script>
</body>
</html>
