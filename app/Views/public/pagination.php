<?php if (($pagination['pages'] ?? 1) > 1): ?>
<?php $queryParams = $paginationQuery ?? []; $pageUrl = function (int $page) use ($paginationBase, $queryParams): string { $params = array_merge($queryParams, ['page' => $page]); return $paginationBase . '?' . http_build_query($params); }; ?>
<nav class="pagination" aria-label="Paginación">
    <?php if ($pagination['has_prev']): ?><a href="<?= e($pageUrl($pagination['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
    <?php for ($number = 1; $number <= $pagination['pages']; $number++): ?>
        <a class="<?= $number === $pagination['page'] ? 'active' : '' ?>" href="<?= e($pageUrl($number)) ?>" <?= $number === $pagination['page'] ? 'aria-current="page"' : '' ?>><?= $number ?></a>
    <?php endfor; ?>
    <?php if ($pagination['has_next']): ?><a href="<?= e($pageUrl($pagination['page'] + 1)) ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; ?>
