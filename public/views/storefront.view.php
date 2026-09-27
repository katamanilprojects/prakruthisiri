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

    <!-- Pre-Rendered Semantic Catalog Fallback for Search Crawlers & No-JS -->
    <noscript>
      <div class="seo-catalog-fallback p-4 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <h2 class="text-base font-bold text-slate-900">Fresh Organic Vegetables Available for Delivery in Hanamkonda &amp; Warangal (తాజా సేంద్రీయ కూరగాయలు)</h2>
        <ul class="space-y-1.5 text-xs text-slate-700">
          <?php
          try {
              $seoStmt = $pdo->query("SELECT `name`, `telugu_name`, `price_per_half_kg` FROM `products` WHERE `is_active` = 1");
              while ($p = $seoStmt->fetch(PDO::FETCH_ASSOC)):
          ?>
            <li class="flex justify-between border-b border-slate-100 pb-1">
              <strong><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['telugu_name']) ?>)</strong>
              <span class="font-mono font-semibold text-emerald-700">₹<?= number_format((float)$p['price_per_half_kg'], 2) ?> per 0.5 kg packet</span>
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

    <!-- Storefront Catalog Section (Revealed once phone and address are verified) -->
    <div id="storefront-catalog-section" class="space-y-4 hidden">

      <?php require __DIR__ . '/../components/banner-delivery.php'; ?>

      <!-- STEP 2: SCANNABLE 2-COLUMN VEGETABLE CATALOG GRID -->
      <section class="space-y-3" id="vegetable-catalog">
        <div class="flex items-center justify-between px-1">
          <h2 class="text-sm sm:text-base font-extrabold text-slate-900" id="title-step2">Farm-Fresh Harvest Selection</h2>
          <span class="text-xs font-semibold text-slate-500" id="catalog-count-label"></span>
        </div>

        <div class="catalog-grid" id="vegetables-container">
          <?php if (!empty($initialCatalog)): ?>
            <?php foreach ($initialCatalog as $p): ?>
              <?php require __DIR__ . '/../components/card-product.php'; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-span-2 app-card text-center py-10 text-slate-400 text-sm" id="catalog-loading-msg"></div>
          <?php endif; ?>
        </div>
      </section>
    </div>

  </main>

  <?php require __DIR__ . '/../components/bar-floating-cart.php'; ?>

  <?php require __DIR__ . '/../modals/drawer-checkout.php'; ?>

  <script src="assets/js/i18n-customer.js"></script>
  <script>
    // Global State
    let currentLang = localStorage.getItem('ps_customer_lang') || 'te';
    let currentRegion = '<?= $defaultRegion ?>';
    let activeSchedule = <?= json_encode($activeSchedule) ?>;
    let activeCatalog = <?= json_encode($initialCatalog) ?>;
    const regionSchedules = <?= json_encode($regionSchedules) ?>;
    const movThreshold = <?= (float)$movThreshold ?>;
    const standardDeliveryFee = <?= (float)$deliveryFee ?>;
    const storeWhatsApp = '<?= $storeWhatsApp ?>';
    
    // Customer profile state
    let customerProfile = null;

    // Cart representation: { productId: quantityInPackets }
    let cart = {};
  </script>
  <script src="assets/js/storefront-app.js"></script>
</body>
</html>
