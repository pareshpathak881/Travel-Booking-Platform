<?php
declare(strict_types=1);
/**
 * IndiaYatra — Global Header
 *
 * Responsibilities:
 *   1. Start/resume session.
 *   2. Handle the evaluator role-toggle shortcut.
 *   3. Retrieve user loyalty levels, badges, and wishlist statistics.
 *   4. Emit the <head> with CDNs.
 *   5. Render the React-powered gamified navigation bar.
 *
 * MUST be the first include in every page.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

// ── Evaluator Role-Toggle Shortcut ─────────────────────────────────────
if (isset($_GET['toggle_role'])) {
    $requestedRole = $_GET['toggle_role'];

    if ($requestedRole === 'admin') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role']    = 'admin';
        $_SESSION['name']    = 'Paresh';
    } elseif ($requestedRole === 'customer') {
        $_SESSION['user_id'] = 2;
        $_SESSION['role']    = 'customer';
        $_SESSION['name']    = 'Raj Sharma';
    } elseif ($requestedRole === 'guest') {
        session_destroy();
        session_start();
    }

    $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $cleanUrl);
    exit;
}

// ── Fetch user gamification details & wishlist from Database ───────────
$userId = $_SESSION['user_id'] ?? null;
$loyaltyPoints = 0;
$userLevel = 1;
$bookingCount = 0;
$totalSpent = 0.00;
$badgeFlags = '';
$wishlist = [];

if ($userId !== null) {
    try {
        $db = getPDO();
        $stmt = $db->prepare("SELECT loyalty_points, user_level, booking_count, total_spent, badge_flags FROM users WHERE user_id = :id");
        $stmt->execute(['id' => $userId]);
        $userData = $stmt->fetch();
        if ($userData) {
            $loyaltyPoints = (int) $userData['loyalty_points'];
            $userLevel     = (int) $userData['user_level'];
            $bookingCount  = (int) $userData['booking_count'];
            $totalSpent    = (float) $userData['total_spent'];
            $badgeFlags    = (string) $userData['badge_flags'];
        }

        // Fetch user's wishlist
        $wStmt = $db->prepare("SELECT package_id FROM wishlists WHERE user_id = :id");
        $wStmt->execute(['id' => $userId]);
        $wishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Safe failover
    }
}

$sessionJson = json_encode([
    'loggedIn'       => !empty($_SESSION['user_id']),
    'userId'         => $userId,
    'role'           => $_SESSION['role']  ?? null,
    'name'           => $_SESSION['name']  ?? null,
    'loyalty_points' => $loyaltyPoints,
    'user_level'     => $userLevel,
    'booking_count'  => $bookingCount,
    'total_spent'    => $totalSpent,
    'badge_flags'    => $badgeFlags,
    'wishlist'       => $wishlist
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$currentPath = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');
$pageTitle   = $GLOBALS['pageTitle']       ?? 'IndiaYatra — Discover Incredible India';
$pageDesc    = $GLOBALS['pageDescription'] ?? 'Book flights, hotels, and packages with IndiaYatra.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

  <!-- FontAwesome for UI Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Tailwind CSS 3.4 -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- React 18.2.0 + ReactDOM -->
  <script src="https://unpkg.com/react@18.2.0/umd/react.production.min.js" crossorigin></script>
  <script src="https://unpkg.com/react-dom@18.2.0/umd/react-dom.production.min.js" crossorigin></script>

  <!-- Babel Standalone -->
  <script src="https://unpkg.com/@babel/standalone@7.23.2/babel.min.js" crossorigin></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans:    ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            display: ['Playfair Display', 'Georgia', 'serif'],
          },
          colors: {
            brand: {
              50:  '#fff7ed',
              100: '#ffedd5',
              200: '#fed7aa',
              300: '#fdba74',
              400: '#fb923c',
              500: '#f97316',
              600: '#ea580c',
              700: '#c2410c',
              800: '#9a3412',
              900: '#7c2d12',
            },
          },
          keyframes: {
            fadeIn:    { from: { opacity: '0', transform: 'translateY(-8px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
            slideDown: { from: { opacity: '0', maxHeight: '0' }, to: { opacity: '1', maxHeight: '400px' } },
          },
          animation: {
            fadeIn:    'fadeIn 0.25s ease-out',
            slideDown: 'slideDown 0.3s ease-out',
          },
        },
      },
    };
  </script>

  <!-- Compiled Assets (Vite build) -->
  <link rel="stylesheet" href="assets/css/app.min.css">

  <!-- Currency Utility Configuration -->
  <script>
    window.__SESSION__ = <?= $sessionJson ?>;
    window.__CURRENT_PATH__ = <?= json_encode($_SERVER['PHP_SELF']) ?>;
    window.__CSRF_TOKEN__ = <?= json_encode(generateCsrfToken()) ?>;
    
    // Save currency configuration
    window.__CURRENCY__ = localStorage.getItem('currency') || 'INR';
    window.__CURRENCY_RATES__ = {
      INR: 1,
      USD: 1 / 85.0,
      EUR: 1 / 92.0,
      GBP: 1 / 110.0
    };
    window.__CURRENCY_SYMBOLS__ = {
      INR: '₹',
      USD: '$',
      EUR: '€',
      GBP: '£'
    };

    window.formatPrice = function(priceInINR, currency = window.__CURRENCY__) {
      const rate = window.__CURRENCY_RATES__[currency] || 1;
      const symbol = window.__CURRENCY_SYMBOLS__[currency] || '₹';
      const converted = priceInINR * rate;
      if (currency === 'INR') {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(converted);
      } else {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency, maximumFractionDigits: 0 }).format(converted);
      }
    };

    window.setGlobalCurrency = function(newCurrency) {
      localStorage.setItem('currency', newCurrency);
      window.__CURRENCY__ = newCurrency;
      window.dispatchEvent(new CustomEvent('currencyChange', { detail: newCurrency }));
    };
  </script>

  <link rel="stylesheet" href="assets/css/design-system.css">
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">

<!-- Pinned Header / Role Switcher and Global Navigation -->
<div id="nav-root"></div>

<script src="assets/js/app.min.js"></script>