<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Unified Database Migration & Seeding Runner
 * 
 * Can be executed via:
 * 1. CLI: php database/migrate.php
 * 2. Web Browser: http://localhost/hostinger/prakruthisiri/database/migrate.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TimeWindow.php';
require_once __DIR__ . '/../src/DatabaseMigration.php';

use PrakruthiSiri\Config\Database;
use PrakruthiSiri\DatabaseMigration;

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('admin_session');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }

    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;background:#0F172A;color:#fff;">';
        echo '<h2 style="color:#F43F5E;">403 Forbidden - Administrative Session Required</h2>';
        echo '<p>Database migration scripts cannot be executed via the public web without an active administrator session.</p>';
        echo '<p>Please log in via the <a href="../admin/login.php" style="color:#34D399;">Admin Portal</a> or execute via CLI: <code>php database/migrate.php</code></p>';
        echo '</body></html>';
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

$startTime = microtime(true);
$errors = [];
$logs = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $logs = DatabaseMigration::runFullMigrationAndSeed($pdo);
} catch (\Throwable $e) {
    $errors[] = $e->getMessage();
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

if ($isCli) {
    echo "====================================================\n";
    echo "Prakruthi Siri - Database Migration & Seeder\n";
    echo "====================================================\n\n";

    if (!empty($errors)) {
        echo "❌ MIGRATION FAILED with errors:\n";
        foreach ($errors as $err) {
            echo "   - $err\n";
        }
        exit(1);
    }

    echo "✅ ALL MIGRATIONS & SEEDS COMPLETED SUCCESSFULLY ({$elapsedMs} ms):\n\n";
    foreach ($logs as $log) {
        echo "   [✓] {$log}\n";
    }
    echo "\nPlatform is ready for checkout, dispatch, and driver manifest operations.\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Migration & Seeder | Prakruthi Siri</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-stone-50 min-h-screen py-10 px-4 text-stone-900">
  <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl border border-stone-200 p-8">
    <div class="flex items-center gap-3 mb-6 border-b border-stone-100 pb-5">
      <div class="w-12 h-12 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-extrabold text-2xl shadow-md">
        🌿
      </div>
      <div>
        <h1 class="text-xl font-bold text-stone-900">Prakruthi Siri Database Migration</h1>
        <p class="text-xs text-stone-500 font-medium">Automatic Schema Alignment & Initial Seed</p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800">
        <h3 class="font-bold flex items-center gap-2 mb-2 text-rose-900">
          <span>❌</span> Migration Failed
        </h3>
        <ul class="list-disc list-inside text-sm space-y-1">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900">
        <div class="flex items-center justify-between">
          <h3 class="font-bold flex items-center gap-2 text-emerald-950">
            <span>✅</span> All Migrations & Seeds Succeeded
          </h3>
          <span class="text-xs font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-semibold">
            <?= $elapsedMs ?> ms
          </span>
        </div>
        <p class="text-xs text-emerald-700 mt-1">
          All tables, columns, constraints, system settings, staff accounts, vegetables items, and active delivery schedules have been verified.
        </p>
      </div>

      <div class="space-y-2 mb-8">
        <h4 class="text-xs font-bold uppercase tracking-wider text-stone-400 mb-2">Execution Log</h4>
        <?php foreach ($logs as $log): ?>
          <div class="flex items-start gap-2.5 text-sm text-stone-700 bg-stone-50 px-3.5 py-2.5 rounded-lg border border-stone-150">
            <span class="text-emerald-600 font-bold mt-0.5">✓</span>
            <span class="font-medium"><?= htmlspecialchars($log, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="flex flex-wrap gap-3 pt-4 border-t border-stone-100">
        <a href="../public/index.php" class="flex-1 text-center px-5 py-3 rounded-xl bg-emerald-700 text-white font-bold text-sm shadow hover:bg-emerald-800 transition">
          Go to Storefront (public/index.php)
        </a>
        <a href="../admin/dashboard.php" class="flex-1 text-center px-5 py-3 rounded-xl bg-stone-800 text-white font-bold text-sm shadow hover:bg-stone-900 transition">
          Admin Dashboard
        </a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
