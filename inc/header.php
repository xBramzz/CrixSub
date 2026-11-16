<?php
$PAGE_TITLE  = $PAGE_TITLE  ?? 'Player';
$PAGE_ACTIVE = $PAGE_ACTIVE ?? 'index';
$icons = [
    'play'    => '<polygon points="6 4 20 12 6 20 6 4"/>',
    'music'   => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'list'    => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
];
$sections = [
    'MAIN' => [
        ['id'=>'index',     'label'=>'Player',    'href'=>'index.php',     'icon'=>'play'],
        ['id'=>'library',   'label'=>'Library',   'href'=>'library.php',   'icon'=>'music'],
        ['id'=>'playlists', 'label'=>'Playlists', 'href'=>'playlists.php', 'icon'=>'list'],
    ],
    'SYSTEM' => [
        ['id'=>'upload', 'label'=>'Upload', 'href'=>'upload.php', 'icon'=>'upload'],
        ['id'=>'admin',  'label'=>'Admin',  'href'=>'admin.php',  'icon'=>'sliders'],
    ],
];
?>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">🔊</div>
        <div class="brand-name">Crixoofer</div>
    </div>
    <?php foreach ($sections as $label => $items): ?>
        <div class="nav-label"><?= $label ?></div>
        <?php foreach ($items as $it): ?>
            <a class="nav-item <?= $PAGE_ACTIVE === $it['id'] ? 'active' : '' ?>" href="<?= $it['href'] ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$it['icon']] ?></svg>
                <span><?= $it['label'] ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="sidebar-footer">› Crixoofer v1.1.0</div>
</aside>
<div class="content-wrap">
<header class="topbar">
    <h1><?= $PAGE_TITLE ?></h1>
    <div class="topbar-right">
        <form class="global-search" action="library.php" method="get">
            <input type="text" name="q" placeholder="Search tracks...">
        </form>
        <a class="topbar-btn" href="index.php">▶ Now Playing</a>
    </div>
</header>