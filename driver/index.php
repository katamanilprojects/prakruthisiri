<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Delivery Driver Authentication
 * Clean CDCApp Design System Standard - Native Input with System Numpad
 */

session_name('driver_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require_once __DIR__ . '/../config/database.php';
use PrakruthiSiri\Config\Database;

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 86400,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?: '',
                'secure'   => $params['secure'] ?? false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
        setcookie(session_name(), '', time() - 86400, '/');
        setcookie(session_name(), '', time() - 86400, '');
        setcookie('PHPSESSID', '', time() - 86400, '/');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
}

// Redirect if already authenticated
if (!empty($_SESSION['driver_id'])) {
    header('Location: route.php');
    exit;
}

$error = null;
$phone = trim((string) ($_POST['phone_number'] ?? ''));
$pin   = trim((string) ($_POST['pin'] ?? ''));

// Fetch registered drivers
$driversList = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("
        SELECT `id`, `full_name`, `phone_number` 
        FROM `staff_users` 
        WHERE `role` = 'driver' AND `is_active` = 1 
        ORDER BY `full_name` ASC
    ");
    $driversList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
    $error = 'System offline: Unable to reach database. Please contact farm dispatch.';
    $driversList = [];
}

// Process Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($phone === '' || $pin === '') {
        $error = 'Please select your phone number and enter your 6-digit PIN.';
    } elseif (strlen($pin) !== 6 || !ctype_digit($pin)) {
        $error = 'PIN must be exactly 6 digits.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                SELECT `id`, `full_name`, `phone_number`, `auth_secret`, `is_active`
                FROM `staff_users`
                WHERE `phone_number` = :phone AND `role` = 'driver' AND `is_active` = 1
                LIMIT 1
            ");
            $stmt->execute([':phone' => $phone]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($driver && password_verify($pin, $driver['auth_secret'])) {
                session_regenerate_id(true);
                $_SESSION['driver_id']    = (int) $driver['id'];
                $_SESSION['driver_name']  = $driver['full_name'];
                $_SESSION['driver_phone'] = $driver['phone_number'];

                header('Location: route.php');
                exit;
            } else {
                $error = 'Invalid Phone Number or PIN. Please check and retry.';
            }
        } catch (\Throwable $e) {
            $error = 'Authentication service error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Driver Login | Prakruthi Siri</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#059669">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PS Driver">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('SW reg error:', err));
      });
    }
  </script>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body class="min-h-full font-sans text-slate-900 bg-slate-50 antialiased flex flex-col justify-between p-4">

  <!-- Top Language Switcher Bar & Install App -->
  <div class="max-w-sm mx-auto w-full flex items-center justify-between pt-2">
    <button type="button" id="btn-pwa-install-driver-login" onclick="triggerDriverPwaInstall()" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs flex items-center gap-1 cursor-pointer">
      <span>📲</span>
      <span id="driver-login-install-label">Install App</span>
    </button>
    <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 select-none text-xs font-bold ml-auto">
      <button type="button" id="driver-lang-te" class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
      <button type="button" id="driver-lang-en" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition">English</button>
    </div>
  </div>

  <!-- Main Login Card Container -->
  <div class="max-w-sm mx-auto w-full my-auto space-y-4">
    
    <!-- Brand Header Section -->
    <div class="text-center space-y-1.5">
      <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold text-2xl flex items-center justify-center mx-auto shadow-xs">
        🚚
      </div>
      <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Prakruthi Siri</h1>
      <p class="text-xs text-slate-500 font-medium" id="driver-app-subtitle">Delivery Partner Portal</p>
      <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>Hanamkonda &amp; Warangal Morning Run</span>
      </div>
    </div>

    <!-- Login Card -->
    <div class="app-card space-y-4">
      <?php if ($error): ?>
        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
          <span class="text-base">⚠️</span>
          <span><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
        </div>
      <?php endif; ?>

      <form id="driver-login-form" method="POST" action="index.php" class="space-y-4">
        
        <!-- Driver Selection Dropdown -->
        <div>
          <label for="driver-select" class="block text-xs font-bold text-slate-700 mb-1" id="label-select-driver">
            Select Driver
          </label>
          <select 
            id="driver-select" 
            name="phone_number" 
            required 
            class="app-select font-bold"
          >
            <?php if (empty($driversList)): ?>
              <option value="">No drivers registered</option>
            <?php else: ?>
              <?php foreach ($driversList as $d): ?>
                <option value="<?= htmlspecialchars($d['phone_number'], ENT_QUOTES) ?>" <?= $phone === $d['phone_number'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['full_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($d['phone_number'], ENT_QUOTES) ?>)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <!-- Native Password / Numeric PIN Input (Triggers Native System Numpad) -->
        <div>
          <label for="pin-input" class="block text-xs font-bold text-slate-700 mb-1" id="label-enter-pin">
            Enter 6-Digit PIN
          </label>
          <input 
            type="password" 
            inputmode="numeric" 
            pattern="[0-9]*" 
            maxlength="6" 
            id="pin-input" 
            name="pin" 
            required 
            placeholder="••••••"
            autocomplete="current-password"
            class="app-input text-center font-mono font-extrabold text-2xl tracking-widest text-slate-900"
          >
          <p class="text-xs text-slate-400 mt-1.5 text-center" id="label-pin-hint">
            Default seed PIN for drivers: 123456
          </p>
        </div>

        <!-- Submit Route Button -->
        <div class="pt-1">
          <button 
            type="submit" 
            id="btn-submit-login" 
            class="btn btn-primary btn-large btn-full text-base shadow-md"
          >
            <span id="btn-submit-login-text">Open Delivery Manifest</span>
            <span>→</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="text-center text-xs text-slate-400 pb-2">
    Prakruthi Siri Logistics &bull; Warangal &amp; Hanamkonda
  </footer>

  <script src="assets/js/i18n-driver.js"></script>
  <script>
    (() => {
      let currentLang = localStorage.getItem('ps_driver_lang') || 'te';
      const btnLangTe = document.getElementById('driver-lang-te');
      const btnLangEn = document.getElementById('driver-lang-en');

      function updateLanguage(lang) {
        currentLang = lang;
        localStorage.setItem('ps_driver_lang', lang);
        if (typeof DRIVER_I18N === 'undefined') return;

        const isTe = (lang === 'te');
        const t = DRIVER_I18N[lang] || DRIVER_I18N.te;

        if (isTe) {
          btnLangTe.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
          btnLangEn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
        } else {
          btnLangEn.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow-xs';
          btnLangTe.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition';
        }

        document.getElementById('driver-app-subtitle').textContent = t.app_bar_subtitle;
        document.getElementById('label-select-driver').textContent = t.select_driver;
        document.getElementById('label-enter-pin').textContent = t.enter_pin;
        document.getElementById('label-pin-hint').textContent = t.pin_hint;
        document.getElementById('btn-submit-login-text').textContent = t.open_manifest;
        if (t.install_app && document.getElementById('driver-login-install-label')) {
          document.getElementById('driver-login-install-label').textContent = t.install_app;
        }
      }

      btnLangTe?.addEventListener('click', () => updateLanguage('te'));
      btnLangEn?.addEventListener('click', () => updateLanguage('en'));

      updateLanguage(currentLang);
    })();

    // --------------------------------------------------------------------------
    // Driver PWA Installation Logic
    // --------------------------------------------------------------------------
    let deferredDriverPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredDriverPrompt = e;
    });

    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
      document.getElementById('btn-pwa-install-driver-login')?.classList.add('hidden');
    }

    function triggerDriverPwaInstall() {
      if (deferredDriverPrompt) {
        deferredDriverPrompt.prompt();
        deferredDriverPrompt.userChoice.then((choice) => {
          if (choice.outcome === 'accepted') {
            document.getElementById('btn-pwa-install-driver-login')?.classList.add('hidden');
          }
          deferredDriverPrompt = null;
        });
      } else {
        const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIos) {
          alert('To install PS Driver on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
        } else {
          alert('To install PS Driver:\n\nIn Chrome/Edge menu (⋮), select "Install App" or "Add to Home Screen".');
        }
      }
    }
  </script>
</body>
</html>
