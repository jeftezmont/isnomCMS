<?php function site_footer(array $config): void { ?>
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<?php
try {
    $settings = (new \App\Models\Setting(\App\Core\Database::connect($config)))->all();
} catch (\Throwable) {
    $settings = [];
}
$socialLinks = json_decode($settings['social_links'] ?? '', true);
if (!is_array($socialLinks)) {
    $socialLinks = [
        ['label' => 'Instagram', 'url' => ($settings['instagram_url'] ?? '') ?: $config['site']['social']['Instagram']],
        ['label' => 'SoundCloud', 'url' => ($settings['soundcloud_url'] ?? '') ?: $config['site']['social']['SoundCloud']],
        ['label' => 'Threads', 'url' => ($settings['threads_url'] ?? '') ?: $config['site']['social']['Threads']],
        ['label' => 'Blog', 'url' => '/blog'],
    ];
}
$socialLinks = array_values(array_filter($socialLinks, fn ($link) => !empty($link['label']) && !empty($link['url'])));
?>
<footer class="site-footer">
    <nav class="social-row">
        <?php foreach ($socialLinks as $index => $link): ?>
            <?php $label = (string) $link['label']; $href = (string) $link['url']; ?>
            <?php $isActive = $label === 'Blog' && str_starts_with($currentPath, '/blog'); ?>
            <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= e($href) ?>" <?= str_starts_with($href, 'http') ? 'target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a><?= $index < count($socialLinks) - 1 ? ' / ' : '' ?>
        <?php endforeach; ?>
    </nav>
    <div class="copyright">© jefté montenegro — 1999 - 2026 <a href="https://isnom.org" target="_blank" rel="noopener">GRUPO ISNOM</a></div>
</footer>
<?php } ?>
