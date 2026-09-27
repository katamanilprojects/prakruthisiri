<?php
declare(strict_types=1);
/**
 * Prakruthi Siri Storefront Layout Assembler
 */
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<?php require __DIR__ . '/../components/meta-head.php'; ?>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 pb-36">

  <?php require __DIR__ . '/../components/header-brand.php'; ?>

  <main class="flex-1 max-w-xl w-full mx-auto px-4 py-4 space-y-4 pb-36">

    <?php require __DIR__ . '/../components/banner-pwa.php'; ?>

    <!-- SEO fallback for crawlers / no-JS -->
    <noscript>
      <div class="seo-catalog-fallback p-4 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <h2 class="text-base font-bold text-slate-900">Fresh Organic Vegetables — Hanamkonda &amp; Warangal (తాజా సేంద్రీయ కూరగాయలు)</h2>
        <ul class="space-y-1.5 text-xs text-slate-700">
          <?php
          try {
              $seoStmt = $pdo->query("SELECT `name`, `telugu_name`, `price_per_half_kg` FROM `products` WHERE `is_active` = 1");
              while ($p = $seoStmt->fetch(PDO::FETCH_ASSOC)):
          ?>
            <li class="flex justify-between border-b border-slate-100 pb-1">
              <strong><?= htmlspecialchars($p['name']) ?></strong>
              <span class="font-mono font-semibold text-emerald-700">₹<?= number_format((float)$p['price_per_half_kg'], 2) ?> / 0.5 kg</span>
            </li>
          <?php
              endwhile;
          } catch (\Throwable $e) {}
          ?>
        </ul>
      </div>
    </noscript>

    <?php require __DIR__ . '/../components/card-onboarding.php'; ?>

    <?php require __DIR__ . '/../components/card-locked.php'; ?>

    <!-- Storefront Catalog Section (revealed after region + address confirmed) -->
    <div id="storefront-catalog-section" class="space-y-4 hidden">

      <?php require __DIR__ . '/../components/banner-delivery.php'; ?>

      <section class="space-y-3" id="vegetable-catalog">
        <div class="flex items-center justify-between px-1">
          <h2 class="text-sm sm:text-base font-extrabold text-slate-900" id="title-step2"></h2>
          <span class="text-xs font-semibold text-slate-500" id="catalog-count-label"></span>
        </div>
        <div class="catalog-grid" id="vegetables-container">
          <div class="col-span-2 app-card text-center py-10 text-slate-400 text-sm" id="catalog-loading-msg"></div>
        </div>
      </section>
    </div>

  </main>

  <?php require __DIR__ . '/../components/bar-floating-cart.php'; ?>
  <?php require __DIR__ . '/../modals/drawer-checkout.php'; ?>

  <script src="assets/js/i18n-customer.js"></script>
  <script>
    // -------------------------------------------------------------------------
    // Global State — currentRegion starts null; set by modal or localStorage
    // -------------------------------------------------------------------------
    let currentLang   = localStorage.getItem('ps_customer_lang') || 'te';
    let currentRegion = null;   // null = region not yet chosen
    let activeSchedule = null;
    let activeCatalog  = [];
    const regionSchedules = <?= json_encode($regionSchedules, JSON_UNESCAPED_UNICODE) ?>;
    const movThreshold       = <?= (float)$movThreshold ?>;
    const standardDeliveryFee = <?= (float)$deliveryFee ?>;
    const storeWhatsApp      = '<?= htmlspecialchars($storeWhatsApp, ENT_QUOTES) ?>';

    let customerProfile = null;
    let cart = {};
  </script>
  <script src="assets/js/storefront-app.js"></script>
</body>
</html>
