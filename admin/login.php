<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Administrative Portal Login
 * CDCApp Standard: Modern Inter Typography, Emerald Theme, High-Contrast Card
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('admin_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
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
    header('Location: login.php');
    exit;
}

// If already logged in, redirect to orders page
if (!empty($_SESSION['admin_id'])) {
    header('Location: orders.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
use PrakruthiSiri\Config\Database;

$error = null;
$phone = trim((string) ($_POST['phone_number'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? 'orders.php');

// Sanitize redirect URL to prevent open redirect vulnerabilities
if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $redirect) || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
    $redirect = 'orders.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($phone === '' || $password === '') {
        $error = 'Please enter both your registered phone number and password.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('
                SELECT `id`, `full_name`, `phone_number`, `auth_secret`, `is_active`
                FROM `staff_users`
                WHERE `phone_number` = :phone
                  AND `role` = \'admin\'
                  AND `is_active` = 1
                LIMIT 1
            ');
            $stmt->execute([':phone' => $phone]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, (string) $admin['auth_secret'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']    = (int) $admin['id'];
                $_SESSION['admin_name']  = (string) $admin['full_name'];
                $_SESSION['admin_phone'] = (string) $admin['phone_number'];

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid credentials or inactive administrator account.';
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administrator Login | Prakruthi Siri Operations</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0f172a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PS Admin">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('SW reg error:', err));
      });
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/admin-theme.css">
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">

  <!-- Top Language Switcher Bar & Install App -->
  <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 flex items-center justify-between mb-3">
    <button type="button" id="btn-pwa-install-login" onclick="triggerAdminPwaInstall()" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs flex items-center gap-1 cursor-pointer">
      <span>📲</span>
      <span id="login-install-label" data-i18n="install_app">Install App</span>
    </button>
    <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 select-none text-xs font-semibold ml-auto">
      <button type="button" id="admin-lang-en" class="px-2.5 py-1 rounded-md bg-emerald-600 text-white font-semibold transition shadow-xs">English</button>
      <button type="button" id="admin-lang-te" class="px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 font-medium transition">తెలుగు</button>
    </div>
  </div>
  
  <!-- Masthead Badge -->
  <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 text-center">
    <div class="inline-flex w-12 h-12 rounded-2xl bg-emerald-600 text-white font-bold text-xl items-center justify-center shadow-md mb-3">
      🌿
    </div>
    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight" data-i18n="portal_title">
      Prakruthi Siri
    </h1>
    <div class="mt-1 flex items-center justify-center gap-2">
      <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200" data-i18n="portal_badge">
        Operations Portal
      </span>
      <span class="text-xs text-slate-400">&bull; <span data-i18n="auth_badge">Admin Authentication</span></span>
    </div>
  </div>

  <!-- Login Card -->
  <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
    <div class="app-card py-8 px-6 sm:px-8 shadow-sm space-y-6">
      <div class="pb-3 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-900" data-i18n="login_title">Sign in to your account</h2>
        <p class="text-xs text-slate-500 mt-0.5" data-i18n="login_subtitle">Enter authorized mobile number and administrative password</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold flex items-center gap-2">
          <span>⚠️</span>
          <span><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="space-y-4">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>">

        <div>
          <label for="phone_number" class="block text-xs font-bold text-slate-700 mb-1.5" data-i18n="phone_label">Mobile Number</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-mono text-xs font-semibold">+91</span>
            <input 
              type="tel" 
              id="phone_number" 
              name="phone_number" 
              value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>" 
              required 
              placeholder="9876543210"
              class="w-full pl-12 pr-3.5 py-2.5 text-xs font-mono font-medium border border-slate-200 rounded-xl focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition bg-white text-slate-900"
            >
          </div>
        </div>

        <div>
          <label for="password" class="block text-xs font-bold text-slate-700 mb-1.5" data-i18n="password_label">Administrative Password</label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            required 
            placeholder="••••••••••••"
            class="w-full px-3.5 py-2.5 text-xs font-mono font-medium border border-slate-200 rounded-xl focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition bg-white text-slate-900"
          >
        </div>

        <div class="pt-2">
          <button 
            type="submit" 
            class="btn btn-primary w-full btn-large text-sm font-bold"
          >
            <span data-i18n="sign_in_btn">Authorize &amp; Continue</span>
            <span>&rarr;</span>
          </button>
        </div>
      </form>

      <div class="pt-4 border-t border-slate-100 text-center">
        <p class="text-[11px] text-slate-400" data-i18n="strict_notice">
          Strictly authorized personnel &bull; Warangal Organic Hub Operations
        </p>
      </div>
    </div>
  </div>

  <script src="assets/js/i18n-admin.js"></script>
  <script>
    (function() {
      function setAdminLoginLanguage(lang) {
        localStorage.setItem('ps_admin_lang', lang);
        const isTe = (lang === 'te');
        const btnEn = document.getElementById('admin-lang-en');
        const btnTe = document.getElementById('admin-lang-te');
        if (btnEn && btnTe) {
          if (isTe) {
            btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-semibold transition shadow-xs';
            btnEn.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 font-medium transition';
          } else {
            btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-semibold transition shadow-xs';
            btnTe.className = 'px-2.5 py-1 rounded-md text-slate-600 hover:text-slate-900 font-medium transition';
          }
        }

        if (typeof ADMIN_I18N === 'undefined') return;
        const dict = ADMIN_I18N[lang] || ADMIN_I18N.en;

        document.querySelectorAll('[data-i18n]').forEach(el => {
          const k = el.getAttribute('data-i18n');
          if (dict[k]) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
              if (el.hasAttribute('placeholder')) el.setAttribute('placeholder', dict[k]);
            } else {
              el.textContent = dict[k];
            }
          }
        });
      }

      function init() {
        const savedLang = localStorage.getItem('ps_admin_lang') || 'en';
        document.getElementById('admin-lang-en')?.addEventListener('click', () => setAdminLoginLanguage('en'));
        document.getElementById('admin-lang-te')?.addEventListener('click', () => setAdminLoginLanguage('te'));
        setAdminLoginLanguage(savedLang);
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
    })();

    // --------------------------------------------------------------------------
    // Admin PWA Installation Logic
    // --------------------------------------------------------------------------
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.error('Admin SW reg error:', err));
      });
    }

    let deferredAdminPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredAdminPrompt = e;
    });

    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
      document.getElementById('btn-pwa-install-login')?.classList.add('hidden');
    }

    function triggerAdminPwaInstall() {
      if (deferredAdminPrompt) {
        deferredAdminPrompt.prompt();
        deferredAdminPrompt.userChoice.then((choice) => {
          if (choice.outcome === 'accepted') {
            document.getElementById('btn-pwa-install-login')?.classList.add('hidden');
          }
          deferredAdminPrompt = null;
        });
      } else {
        const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIos) {
          alert('To install PS Admin on your iPhone/iPad:\n\n1. Tap the Share button 📤 in Safari.\n2. Scroll down & tap "Add to Home Screen" ➕.');
        } else {
          alert('To install PS Admin:\n\nIn Chrome/Edge menu (⋮), select "Install App" or "Add to Home Screen".');
        }
      }
    }
  </script>
</body>
</html>
