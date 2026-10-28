<?php
// =====================================================================
//  index_mo.php — Sangbad Sankalan Android App Entry Point
//  ★ SELF-CONTAINED — মূল index.php কে সম্পূর্ণ অপরিবর্তিত রাখে।
//
//  কাজের পদ্ধতি:
//    1. ob_start() দিয়ে index.php-এর HTML output capture করে
//    2. Capture করা HTML-এ অ্যাপ-অনলি CSS + JS inject করে
//    3. Modified HTML output করে
//
//  ★ AJAX / Redirect / Binary Endpoint সাপোর্ট:
//    index.php-তে exit() হলে (AJAX JSON, TTS audio, redirects), সেই
//    output এখানে capture হয় এবং অপরিবর্তিত পাঠানো হয়। কোনো CSS/JS
//    inject করা হয় না।
//
//  অ্যাপ-অনলি ফিচার:
//    • ৩-ডট (⋮) মেনুতে সব ২০টি ক্যাটাগরি (আর্কাইভ সহ)
//    • মোবাইলে ইনলাইন ক্যাটাগরি লুকানো
//    • Sidebar খোলা/বন্ধ (overlay সহ)
//    • Panel header "সব ক্যাটাগরি"
//
//  ★ NEWS FLOW: অ্যাপ যেহেতু index.php কেই include করে, তাই সাইটের সব
//    নিউজ (হোম, ক্যাটাগরি, সিঙ্গেল, সার্চ, আর্কাইভ) সরাসরি index.php থেকে
//    আসে। কোনো নিউজ স্টোরেজ বা ডুপ্লিকেশন নেই।
// =====================================================================

// ── Step 1: Capture index.php output ──────────────────────────────────
// ob_start এর ভেতরে index.php চলবে। যদি exit() হয় (AJAX), তখন PHP
// স্বয়ংক্রিয়ভাবে output buffer flush করে দেয়। সেক্ষেত্রে আমাদের
// ob_get_clean() খালি পাবে এবং আমরা `return` করব — কোনো inject নয়।
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

// খালি output = index.php-তে exit() হয়েছে (AJAX, redirect, binary audio)।
// সেই output ইতিমধ্যেই ব্রাউজারে চলে গেছে — আমাদের কিছু করার নেই।
if ($html === '' || $html === null || $html === false) {
    return;
}

// যদি output এ <body> নেই, তাহলে এটা JSON/AJAX response। অপরিবর্তিত পাঠাই।
if (strpos($html, '<body') === false) {
    echo $html;
    return;
}

// ── Step 2: Add body class "app-view" ─────────────────────────────────
$html = preg_replace('/<body(\s[^>]*)?>/s', '<body$1 class="app-view">', $html, 1);

// ── Step 3: Inject CSS before last </style> ──────────────────────────
$appCSS = <<<'CSS'

/* ════════════════════════════════════════════════════════════════════
   APP-ONLY STYLES (injected by index_mo.php — does not modify index.php)
   ════════════════════════════════════════════════════════════════════ */

/* মোবাইলে ইনলাইন ক্যাটাগরি লুকানো — শুধু হোম, ভিডিও, ই-পেপার, আর্কাইভ, ⋮ দেখাবে */
@media (max-width: 768px) {
    .kn-cats-wrap { display: none !important; }

    /* ৩-ডট বাটন — শুধু আইকন দেখাবে, label ও chevron লুকানো */
    .kn-more-btn .more-label,
    .kn-more-btn .more-chevron { display: none; }
    .kn-more-btn { padding: 12px 14px; }
    .kn-more-btn i.fa-ellipsis-v { font-size: 18px; opacity: 0.9; }

    /* প্যানেল বড় + হেডার দেখানো */
    .kn-panel {
        min-width: 280px;
        max-width: calc(100vw - 24px);
        max-height: 85vh;
        right: 0;
    }
    .kn-panel-header {
        display: block !important;
        padding: 10px 14px 8px;
        background: linear-gradient(180deg, rgba(212,149,67,.18) 0%, transparent 100%);
        border-bottom: 1px solid rgba(255,255,255,.08);
        margin-bottom: 4px;
        font-family: 'Noto Serif Bengali', serif;
        font-size: 13px;
        font-weight: 700;
        color: #D4950A;
        text-align: center;
    }
    .kn-panel-header i { margin-right: 6px; }
}

/* সাইডবার overlay (মোবাইলে খোলা অবস্থায় ব্যাকগ্রাউন্ড অন্ধকার) */
@media (max-width: 1024px) {
    body.sidebar-open .kol-layout::after {
        content: '';
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.5);
        z-index: 150;
        pointer-events: none;
    }
    body.sidebar-open { overflow: hidden; }
}

CSS;

$html = preg_replace('/<\/style>(?!.*<\/style>)/s', $appCSS . "</style>", $html, 1);

// ── Step 4: Inject JS before </body> ─────────────────────────────────
$appJS = <<<'JS'
<script>
(function() {
    'use strict';

    // ═══ 1. Wrap inline cats in .kn-cats-wrap for mobile hiding ═══
    var nav = document.querySelector('.kol-nav .container');
    if (nav) {
        var children = Array.prototype.slice.call(nav.children);
        var startIndex = -1, endIndex = -1;

        // Find e-Paper link — inline cats start after it (next sibling)
        for (var i = 0; i < children.length; i++) {
            var t = children[i].textContent || '';
            if (children[i].tagName === 'A' && t.indexOf('ই-পেপার') !== -1) {
                startIndex = i + 1;
                break;
            }
        }

        // Find #archiveMenu — inline cats end before it
        for (var j = startIndex >= 0 ? startIndex : 0; j < children.length; j++) {
            if (children[j].id === 'archiveMenu') {
                endIndex = j;
                break;
            }
        }

        // Wrap everything between e-Paper and archiveMenu
        if (startIndex >= 0 && endIndex > startIndex) {
            var wrapper = document.createElement('div');
            wrapper.className = 'kn-cats-wrap';
            nav.insertBefore(wrapper, children[startIndex]);
            for (var k = startIndex; k < endIndex; k++) {
                wrapper.appendChild(children[k]);
            }
        }
    }

    // ═══ 2. Change More button to 3-dot (⋮) icon ═══
    var moreBtn = document.getElementById('knMoreBtn');
    if (moreBtn) {
        moreBtn.innerHTML = '<i class="fas fa-ellipsis-v"></i> <span class="more-label">আরও</span> <i class="fas fa-chevron-down more-chevron"></i>';
    }

    // ═══ 3. Add panel header "সব ক্যাটাগরি" ═══
    var panel = document.getElementById('knPanel');
    if (panel && !panel.querySelector('.kn-panel-header')) {
        var header = document.createElement('div');
        header.className = 'kn-panel-header';
        header.innerHTML = '<i class="fas fa-layer-group"></i> সব ক্যাটাগরি';
        panel.insertBefore(header, panel.firstChild);
    }

    // ═══ 4. Inject missing categories into panel ═══
    // Original index.php skips inline cats + আর্কাইভ in the panel.
    // We read them from the sidebar (which shows ALL 20) and inject them.
    if (panel) {
        var existingCats = {};
        panel.querySelectorAll('.kn-pl-link').forEach(function(item) {
            var c = item.getAttribute('data-pcat');
            if (c) existingCats[c] = true;
        });

        var iconMap = {
            'প্রধান খবর':'fa-fire','জাতীয়':'fa-flag','রাজনীতি':'fa-landmark',
            'আন্তর্জাতিক':'fa-globe','অর্থনীতি':'fa-chart-line','খেলাধুলা':'fa-futbol',
            'বিনোদন':'fa-film','শিক্ষা':'fa-graduation-cap','প্রযুক্তি':'fa-microchip',
            'স্বাস্থ্য':'fa-heart-pulse','বিশেষ সংবাদ':'fa-star','লাইফস্টাইল':'fa-spa',
            'ধর্ম':'fa-mosque','সংস্কৃতি':'fa-masks-theater','মতামত':'fa-comment-dots',
            'ক্রাইম':'fa-gavel','কৃষি':'fa-seedling','ভ্রমণ':'fa-plane',
            'চাকরি':'fa-briefcase','আর্কাইভ':'fa-clock-rotate-left'
        };

        var sidebarCats = document.querySelectorAll('.sb-cat-list .sb-ci');
        sidebarCats.forEach(function(sbItem) {
            var link = sbItem.querySelector('a');
            if (!link) return;
            var span = link.querySelector('span');
            if (!span) return;
            var catName = span.textContent.trim();
            if (!catName || existingCats[catName]) return;

            var href = link.getAttribute('href');
            var icon = iconMap[catName] || 'fa-folder';
            var countEl = link.querySelector('.sb-cc');
            var countHtml = countEl ? '<span class="kpc">' + countEl.textContent + '</span>' : '';

            var item = document.createElement('div');
            item.className = 'kn-pl-link';
            item.setAttribute('data-pcat', catName);
            item.innerHTML =
                '<div class="kpi"><i class="fas ' + icon + '"></i></div>' +
                '<a href="' + href + '" class="kpn">' + catName + '</a>' +
                countHtml;
            panel.appendChild(item);
        });
    }

    // ═══ 5. Fix sidebar toggle — add .open class ═══
    // Original index.php only toggles .sb-hidden on kolLayout, which doesn't
    // affect the sidebar's position:fixed;left:-280px on mobile.
    // We add .open class to .kol-sidebar (CSS: left:0) + body.sidebar-open.
    var sbToggle = document.getElementById('sbToggle');
    var kolSidebar = document.getElementById('kolSidebar');

    if (sbToggle && kolSidebar) {
        // Capture phase — runs BEFORE original handler
        sbToggle.addEventListener('click', function() {
            kolSidebar.classList.toggle('open');
            var isOpen = kolSidebar.classList.contains('open');
            document.body.classList.toggle('sidebar-open', isOpen);
        }, true);

        // Bubble phase — runs AFTER original, fix icon to match .open state
        sbToggle.addEventListener('click', function() {
            setTimeout(function() {
                var icon = sbToggle.querySelector('i');
                var isOpen = kolSidebar.classList.contains('open');
                if (icon) {
                    icon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
                    sbToggle.title = isOpen ? 'Hide Sidebar' : 'Show Sidebar';
                }
            }, 0);
        }, false);

        // Fix initial icon on mobile (original sets it wrong)
        if (window.innerWidth <= 1024) {
            var initIcon = sbToggle.querySelector('i');
            if (initIcon && !kolSidebar.classList.contains('open')) {
                initIcon.className = 'fas fa-bars';
            }
        }
    }

    // ═══ 6. Close sidebar on overlay click ═══
    document.addEventListener('click', function(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (!kolSidebar) return;
        if (kolSidebar.contains(e.target)) return;
        if (e.target === sbToggle || (sbToggle && sbToggle.contains(e.target))) return;
        kolSidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
        if (sbToggle) {
            var icon = sbToggle.querySelector('i');
            if (icon) icon.className = 'fas fa-bars';
        }
    });

    // ═══ 7. Close sidebar when clicking a link inside it ═══
    if (kolSidebar) {
        kolSidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    kolSidebar.classList.remove('open');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    }

    console.log('[index_mo] App-only modifications applied.');
})();
</script>
JS;

$html = str_replace('</body>', $appJS . "\n</body>", $html);

// ── Step 5: Output modified HTML ──────────────────────────────────────
echo $html;
