<?php
declare(strict_types=1);
/**
 * IndiaYatra — Login Page
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

// Already logged in → go home
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

// ── Rate-limiting helpers ──────────────────────────────────────────────────
// Thresholds: 5 failures per email OR 20 failures per IP within 15 minutes.
// The IP ceiling is intentionally high to survive shared NAT / campus WiFi.
(static function () use ($pdo): void {

    // ── Resolve client IP (handle common reverse-proxy headers) ───────────
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
    // Take the first address if comma-separated proxy chain
    $ip = trim(explode(',', $ip)[0]);
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';

    define('CLIENT_IP', $ip);
})();

// Rate-limit thresholds (defined at file scope — const is invalid inside if blocks in PHP)
define('RATE_EMAIL_MAX',   5);   // max per-email failures before lockout
define('RATE_IP_MAX',      20);  // max per-IP failures (high to protect shared NAT / campus WiFi)
define('RATE_WINDOW_SECS', 900); // 15-minute sliding window

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF gate
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Security token mismatch. Please reload the page and try again.';
    } else {
        $email    = sanitizeInput($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Please fill in both your email and password.';
        } else {
            try {
                $windowStart = date('Y-m-d H:i:s', time() - RATE_WINDOW_SECS);

                // Check per-email failure count
                $stmtEmail = $pdo->prepare(
                    'SELECT COUNT(*) FROM login_attempts
                     WHERE email = :email AND attempt_time > :since'
                );
                $stmtEmail->execute([':email' => $email, ':since' => $windowStart]);
                $emailCount = (int) $stmtEmail->fetchColumn();

                // Check per-IP failure count
                $stmtIp = $pdo->prepare(
                    'SELECT COUNT(*) FROM login_attempts
                     WHERE ip_address = :ip AND attempt_time > :since'
                );
                $stmtIp->execute([':ip' => CLIENT_IP, ':since' => $windowStart]);
                $ipCount = (int) $stmtIp->fetchColumn();

                if ($emailCount >= RATE_EMAIL_MAX) {
                    $error = 'Too many failed attempts for this account. Please wait 15 minutes and try again.';
                } elseif ($ipCount >= RATE_IP_MAX) {
                    $error = 'Too many login attempts from your network. Please wait 15 minutes and try again.';
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT user_id, name, email, password, role FROM users WHERE email = :email LIMIT 1'
                    );
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password'])) {
                        // Success — clear this email's failed attempts and start a fresh session
                        $pdo->prepare('DELETE FROM login_attempts WHERE email = :email')
                            ->execute([':email' => $email]);

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['email']   = $user['email'];
                        $_SESSION['role']    = $user['role'];
                        header('Location: index.php');
                        exit;
                    }

                    // Record the failed attempt
                    $pdo->prepare(
                        'INSERT INTO login_attempts (ip_address, email) VALUES (:ip, :email)'
                    )->execute([':ip' => CLIENT_IP, ':email' => $email]);

                    $remaining = RATE_EMAIL_MAX - ($emailCount + 1);
                    if ($remaining > 0) {
                        // Intentionally vague to prevent user enumeration; remaining hint helps UX
                        $error = "Invalid email address or password. {$remaining} attempt(s) remaining before temporary lockout.";
                    } else {
                        $error = 'Too many failed attempts for this account. Please wait 15 minutes and try again.';
                    }
                }

            } catch (PDOException $e) {
                error_log('[IndiaYatra][login] ' . $e->getMessage());
                $error = 'A database error occurred. Please try again shortly.';
            }
        }
    }
}


// ── Template variables ─────────────────────────────────────────────────────
$csrfToken = generateCsrfToken();
$loginError = $error;

// Inject into JS
$jsError = json_encode($loginError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsCsrf  = json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsEmail = json_encode($email,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

$GLOBALS['pageTitle']       = 'Login — IndiaYatra';
$GLOBALS['pageDescription'] = 'Sign in to your IndiaYatra account to manage bookings and explore travel packages across India.';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="min-h-[calc(100vh-8rem)] flex items-center justify-center py-12 px-4">

    <div class="w-full max-w-md" id="login-root"></div>

  </main>

  <!-- Inject PHP data into page -->
  <script>
    window.__LOGIN_ERROR__ = <?= $jsError ?>;
    window.__LOGIN_EMAIL__  = <?= $jsEmail ?>;
    window.__CSRF_TOKEN__  = <?= $jsCsrf ?>;
  </script>

  <script type="text/babel">
  (function () {
    const { useState, useEffect } = React;

    const FALLBACK_IMG = 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80';

    function LoginPage() {
      const [showPassword, setShowPassword] = useState(false);
      const [errorMsg,     setErrorMsg]     = useState(window.__LOGIN_ERROR__ || '');
      const [loading,      setLoading]      = useState(false);

      // Auto-dismiss server error after 6 seconds
      useEffect(() => {
        if (!errorMsg) return;
        const t = setTimeout(() => setErrorMsg(''), 6000);
        return () => clearTimeout(t);
      }, [errorMsg]);

      const handleSubmit = (e) => {
        const form     = e.target;
        const email    = form.email.value.trim();
        const password = form.password.value;

        if (!email || !password) {
          e.preventDefault();
          setErrorMsg('Please enter your email and password.');
          return;
        }
        setLoading(true);
      };

      return (
        <div className="bg-white rounded-2xl shadow-xl overflow-hidden">

          {/* Gradient banner */}
          <div className="h-2 bg-gradient-to-r from-brand-500 via-orange-400 to-amber-400" />

          <div className="px-8 py-10">

            {/* Header */}
            <div className="text-center mb-8">
              <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-brand-50 text-3xl mb-4 shadow-sm">
                🔥
              </div>
              <h1 className="font-display text-3xl font-bold text-slate-900">Welcome Back</h1>
              <p className="mt-1.5 text-sm text-slate-500">Sign in to continue your journey with IndiaYatra</p>
            </div>

            {/* Error banner */}
            {errorMsg && (
              <div
                className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-2 animate-fadeIn"
                role="alert"
              >
                <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
                <span className="text-sm flex-1">{errorMsg}</span>
                <button
                  onClick={() => setErrorMsg('')}
                  className="text-red-400 hover:text-red-600 transition-colors ml-1"
                  aria-label="Dismiss error"
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            )}

            {/* Login form */}
            <form
              method="POST"
              action="login.php"
              onSubmit={handleSubmit}
              noValidate
              id="login-form"
            >
              <input type="hidden" name="csrf_token" value={window.__CSRF_TOKEN__} />

              {/* Email */}
              <div className="mb-5">
                <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1.5">
                  Email Address
                </label>
                <div className="relative">
                  <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </span>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autoComplete="email"
                    defaultValue={window.__LOGIN_EMAIL__ || ''}
                    placeholder="you@example.com"
                    className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent transition-shadow text-sm"
                  />
                </div>
              </div>

              {/* Password */}
              <div className="mb-6">
                <label htmlFor="password" className="block text-sm font-medium text-slate-700 mb-1.5">
                  Password
                </label>
                <div className="relative">
                  <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </span>
                  <input
                    id="password"
                    name="password"
                    type={showPassword ? 'text' : 'password'}
                    required
                    autoComplete="current-password"
                    placeholder="••••••••"
                    className="w-full pl-10 pr-12 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-400 focus:border-transparent transition-shadow text-sm"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? (
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    ) : (
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    )}
                  </button>
                </div>
              </div>

              {/* Submit */}
              <button
                type="submit"
                id="login-submit-btn"
                disabled={loading}
                className="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2 text-sm"
              >
                {loading ? (
                  <>
                    <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Signing in…
                  </>
                ) : (
                  <>
                    Sign In
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </>
                )}
              </button>

            </form>

            {/* Divider */}
            <div className="my-6 flex items-center gap-3">
              <div className="flex-1 h-px bg-slate-200" />
              <span className="text-xs text-slate-400 font-medium">OR</span>
              <div className="flex-1 h-px bg-slate-200" />
            </div>

            {/* Demo credentials hint */}
            <div className="bg-amber-50 border border-amber-200 rounded-xl p-3.5 mb-4">
              <p className="text-xs font-semibold text-amber-800 mb-1">🔑 Demo Credentials</p>
              <div className="space-y-1 text-xs text-amber-700">
                <p><strong>Admin:</strong> admin@travel.com / password123</p>
                <p><strong>Customer:</strong> customer@travel.com / password123</p>
              </div>
            </div>

            {/* Register link */}
            <p className="text-center text-sm text-slate-500">
              New to IndiaYatra?{' '}
              <a href="register.php" className="text-brand-500 hover:text-brand-600 font-semibold transition-colors">
                Create a free account →
              </a>
            </p>

          </div>
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('login-root')).render(<LoginPage />);
  })();
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>