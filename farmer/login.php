<?php

declare(strict_types=1);

/**
 * Prakruthi Siri - Farmer Portal Login
 * Mobile-First, High Contrast, English/Telugu Language Switcher
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('farmer_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

if (!empty($_SESSION['farmer_id']) && ($_SESSION['farmer_role'] ?? '') === 'farmer') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
use PrakruthiSiri\Config\Database;

$error = null;
$phone = trim((string) ($_POST['phone_number'] ?? ''));
$pin   = trim((string) ($_POST['pin'] ?? ''));
$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php');

if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $redirect) || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cleanPhone = substr(preg_replace('/\D/', '', $phone), -10);
    if (strlen($cleanPhone) < 10 || $pin === '') {
        $error = 'Please enter your registered 10-digit mobile number and PIN.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                SELECT `id`, `full_name`, `phone_number`, `auth_secret`, `is_active`, `role`
                FROM `staff_users`
                WHERE RIGHT(REPLACE(REPLACE(`phone_number`, '+91', ''), ' ', ''), 10) = :phone
                  AND `role` = 'farmer'
                  AND `is_active` = 1
                LIMIT 1
            ");
            $stmt->execute([':phone' => $cleanPhone]);
            $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($farmer && password_verify($pin, (string)$farmer['auth_secret'])) {
                session_regenerate_id(true);
                $_SESSION['farmer_id']    = (int) $farmer['id'];
                $_SESSION['farmer_name']  = (string) $farmer['full_name'];
                $_SESSION['farmer_phone'] = (string) $farmer['phone_number'];
                $_SESSION['farmer_role']  = 'farmer';

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid mobile number or PIN. Please verify your credentials.';
            }
        } catch (\Throwable $e) {
            $error = 'Login service temporarily unavailable: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="te" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Farmer Login | Prakruthi Siri</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../public/assets/css/theme.css">
</head>
<body class="min-h-full flex flex-col antialiased text-slate-900 bg-slate-50 p-4">

  <!-- Header & Language Toggle -->
  <div class="max-w-sm mx-auto w-full flex items-center justify-between py-3">
    <div class="flex items-center gap-2">
      <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg shadow-xs">
        🌾
      </div>
      <div>
        <span class="font-extrabold text-sm text-slate-900 leading-tight block">Prakruthi Siri</span>
        <span class="text-[11px] text-slate-500 font-medium block" id="lbl-login-sub">Farmer Portal</span>
      </div>
    </div>

    <!-- Language Toggle Switch -->
    <div class="flex items-center bg-slate-200/80 p-0.5 rounded-lg border border-slate-300 select-none text-xs font-bold">
      <button type="button" id="lang-btn-te" class="px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition shadow-xs">తెలుగు</button>
      <button type="button" id="lang-btn-en" class="px-2.5 py-1 rounded-md text-slate-700 hover:text-slate-900 font-medium transition">English</button>
    </div>
  </div>

  <!-- Login Card -->
  <div class="max-w-sm mx-auto w-full my-auto space-y-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
      <div class="text-center space-y-1">
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight" id="lbl-login-title">రైతు లాగిన్</h1>
        <p class="text-xs text-slate-500" id="lbl-login-desc">మీ 10-అంకెల ఫోన్ నంబర్ మరియు పిన్ నమోదు చేయండి</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
          <?= htmlspecialchars($error, ENT_QUOTES) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="space-y-4">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>">

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-field-phone">మొబైల్ నంబర్</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">📞</span>
            <input 
              type="tel" 
              name="phone_number" 
              required 
              maxlength="10" 
              pattern="[0-9]{10}"
              placeholder="9876543212"
              value="<?= htmlspecialchars($phone, ENT_QUOTES) ?>" 
              class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition"
            >
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1" id="lbl-field-pin">రహస్య పిన్ (PIN)</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔒</span>
            <input 
              type="password" 
              name="pin" 
              required 
              maxlength="8" 
              inputmode="numeric" 
              placeholder="••••••"
              class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold tracking-widest text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none transition"
            >
          </div>
        </div>

        <button 
          type="submit" 
          class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs transition active:scale-[0.98] text-sm flex items-center justify-center gap-2"
          id="btn-login-submit"
        >
          <span>🚜</span>
          <span id="btn-login-text">ప్రవేశించండి (Login)</span>
        </button>
      </form>

      <div class="pt-2 border-t border-slate-100 text-center">
        <p class="text-[11px] text-slate-400 font-medium">Prakruthi Siri Organic Farmland &copy; <?= date('Y') ?></p>
      </div>
    </div>
  </div>

  <script src="assets/js/i18n-farmer.js"></script>
  <script>
    (() => {
      let currentLang = localStorage.getItem('ps_farmer_lang') || 'te';

      function applyLanguage(lang) {
        currentLang = lang;
        localStorage.setItem('ps_farmer_lang', lang);
        const isTe = (lang === 'te');

        const btnTe = document.getElementById('lang-btn-te');
        const btnEn = document.getElementById('lang-btn-en');
        if (btnTe && btnEn) {
          if (isTe) {
            btnTe.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition shadow-xs';
            btnEn.className = 'px-2.5 py-1 rounded-md text-slate-700 hover:text-slate-900 font-medium transition';
          } else {
            btnEn.className = 'px-2.5 py-1 rounded-md bg-emerald-600 text-white font-bold transition shadow-xs';
            btnTe.className = 'px-2.5 py-1 rounded-md text-slate-700 hover:text-slate-900 font-medium transition';
          }
        }

        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

        if (isTe) {
          set('lbl-login-sub', 'రైతు పోర్టల్');
          set('lbl-login-title', 'రైతు లాగిన్');
          set('lbl-login-desc', 'మీ 10-అంకెల ఫోన్ నంబర్ మరియు పిన్ నమోదు చేయండి');
          set('lbl-field-phone', 'మొబైల్ నంబర్');
          set('lbl-field-pin', 'రహస్య పిన్ (PIN)');
          set('btn-login-text', 'ప్రవేశించండి');
        } else {
          set('lbl-login-sub', 'Farmer Portal');
          set('lbl-login-title', 'Farmer Login');
          set('lbl-login-desc', 'Enter your registered 10-digit mobile number and PIN');
          set('lbl-field-phone', 'Mobile Number');
          set('lbl-field-pin', 'Secret PIN');
          set('btn-login-text', 'Sign In');
        }
      }

      document.getElementById('lang-btn-te')?.addEventListener('click', () => applyLanguage('te'));
      document.getElementById('lang-btn-en')?.addEventListener('click', () => applyLanguage('en'));

      applyLanguage(currentLang);
    })();
  </script>
</body>
</html>
