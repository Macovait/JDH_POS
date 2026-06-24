<?php
/**
 * Language Switcher Component
 * Include in header/footer
 */
require_once __DIR__ . '/../../src/Services/LanguageService.php';
$locales = \JDH_POS\Services\LanguageService::availableLocales();
$current = locale();
$names = ['en' => 'English', 'es' => 'Espanol', 'fr' => 'Francais', 'de' => 'Deutsch', 'pt' => 'Portugues', 'sw' => 'Kiswahili', 'hi' => 'Hindi', 'ar' => 'Arabic'];
?>
<div class="language-switcher dropdown d-inline-block ms-2">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($names[$current] ?? $current) ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($locales as $loc): ?>
        <li>
            <a class="dropdown-item <?= $loc === $current ? 'active' : '' ?>" href="?set_locale=<?= urlencode($loc) ?>">
                <?= htmlspecialchars($names[$loc] ?? $loc) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
