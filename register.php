<?php
declare(strict_types=1);
/**
 * IndiaYatra — Register Page
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Security token mismatch. Please reload the page and try again.';
    } else {
        $name     = sanitizeInput($_POST['name']             ?? '');
        $email    = sanitizeInput($_POST['email']            ?? '');
        $password = trim($_POST['password']          ?? '');
        $confirm  = trim($_POST['confirm_password']  ?? '');

        // Server-side validation
        if ($name === '') {
            $error = 'Full name is required.';
        } elseif (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Check duplicate email
                $check = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
                $check->execute([':email' => $email]);

                if ($check->fetch()) {
                    $error = 'An account with this email already exists. Please log in instead.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins  = $pdo->prepare(
                        'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
                    );
                    $ins->execute([
                        ':name'     => $name,
                        ':email'    => $email,
                        ':password' => $hash,
                        ':role'     => 'customer',
                    ]);

                    // Auto-login
                    $newId = (int) $pdo->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newId;
                    $_SESSION['name']    = $name;
                    $_SESSION['email']   = $email;
                    $_SESSION['role']    = 'customer';

                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[IndiaYatra][register] ' . $e->getMessage());
                $error = 'Registration failed due to a server error. Please try again.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$jsError   = json_encode($error,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsCsrf    = json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

$GLOBALS['pageTitle']       = 'Create Account — IndiaYatra';
$GLOBALS['pageDescription'] = 'Join IndiaYatra for free. Discover and book curated travel packages across all regions of India.';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="min-h-[calc(100vh-8rem)] flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md" id="register-root"></div>
  </main>

  <script>
    window.__REGISTER_ERROR__ = <?= $jsError ?>;
    window.__CSRF_TOKEN__     = <?= $jsCsrf ?>;
  </script>

  <script type="text/babel">
  (function () {
    const { useState, useEffect } = React;

    function FieldError({ msg }) {
      if (!msg) return null;
      return (
        <p className="mt-1 text-xs text-red-500 flex items-center gap-1" role="alert">
          <svg className="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          {msg}
        </p>
      );
    }

    function RegisterPage() {
      const [name,     setName]     = useState('');
      const [email,    setEmail]    = useState('');
      const [password, setPassword] = useState('');
      const [confirm,  setConfirm]  = useState('');
      const [showPwd,  setShowPwd]  = useState(false);
      const [showConf, setShowConf] = useState(false);
      const [loading,  setLoading]  = useState(false);
      const [serverErr,setServerErr]= useState(window.__REGISTER_ERROR__ || '');

      // Field-level errors
      const [errs, setErrs] = useState({});

      useEffect(() => {
        if (!serverErr) return;
        const t = setTimeout(() => setServerErr(''), 7000);
        return () => clearTimeout(t);
      }, [serverErr]);

      // Live password strength
      const strength = (() => {
        if (!password) return 0;
        let s = 0;
        if (password.length >= 8)           s++;
        if (/[A-Z]/.test(password))         s++;
        if (/[0-9]/.test(password))         s++;
        if (/[^A-Za-z0-9]/.test(password))  s++;
        return s;
      })();
      const strengthLabel = ['', 'Weak', 'Fair', 'Good', 'Strong'][strength];
      const strengthColor = ['', 'bg-red-400', 'bg-amber-400', 'bg-orange-400', 'bg-emerald-500'][strength];

      const validate = () => {
        const e = {};
        if (!name.trim() || name.trim().length < 2) e.name = 'Full name must be at least 2 characters.';
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) e.email = 'Please enter a valid email.';
        if (password.length < 8) e.password = 'Password must be at least 8 characters.';
        if (confirm !== password) e.confirm = 'Passwords do not match.';
        setErrs(e);
        return Object.keys(e).length === 0;
      };

      const handleSubmit = (e) => {
        if (!validate()) { e.preventDefault(); return; }
        setLoading(true);
      };

      const inputClass = (field) =>
        `w-full px-4 py-2.5 border rounded-xl focus:outline-none focus:ring-2 transition-shadow text-sm
         ${errs[field]
           ? 'border-red-400 focus:ring-red-300 bg-red-50'
           : 'border-slate-300 focus:ring-brand-400 focus:border-transparent'}`;

      return (
        <div className="bg-white rounded-2xl shadow-xl overflow-hidden">
          <div className="h-2 bg-gradient-to-r from-brand-500 via-orange-400 to-amber-400" />

          <div className="px-8 py-10">
            <div className="text-center mb-8">
              <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-brand-50 text-3xl mb-4 shadow-sm">
                🌏
              </div>
              <h1 className="font-display text-3xl font-bold text-slate-900">Create Account</h1>
              <p className="mt-1.5 text-sm text-slate-500">Join IndiaYatra and start exploring incredible India</p>
            </div>

            {serverErr && (
              <div
                className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-2 animate-fadeIn"
                role="alert"
              >
                <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
                <span className="text-sm flex-1">{serverErr}</span>
                <button onClick={() => setServerErr('')} className="text-red-400 hover:text-red-600 transition-colors" aria-label="Dismiss">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            )}

            <form method="POST" action="register.php" onSubmit={handleSubmit} noValidate id="register-form">
              <input type="hidden" name="csrf_token" value={window.__CSRF_TOKEN__} />

              {/* Full Name */}
              <div className="mb-4">
                <label htmlFor="reg-name" className="block text-sm font-medium text-slate-700 mb-1.5">Full Name</label>
                <input
                  id="reg-name" name="name" type="text" required autoComplete="name"
                  placeholder="e.g. Raj Sharma"
                  value={name} onChange={e => { setName(e.target.value); setErrs(p => ({...p, name: ''})); }}
                  className={inputClass('name')}
                />
                <FieldError msg={errs.name} />
              </div>

              {/* Email */}
              <div className="mb-4">
                <label htmlFor="reg-email" className="block text-sm font-medium text-slate-700 mb-1.5">Email Address</label>
                <input
                  id="reg-email" name="email" type="email" required autoComplete="email"
                  placeholder="you@example.com"
                  value={email} onChange={e => { setEmail(e.target.value); setErrs(p => ({...p, email: ''})); }}
                  className={inputClass('email')}
                />
                <FieldError msg={errs.email} />
              </div>

              {/* Password */}
              <div className="mb-2">
                <label htmlFor="reg-password" className="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                <div className="relative">
                  <input
                    id="reg-password" name="password"
                    type={showPwd ? 'text' : 'password'} required autoComplete="new-password"
                    placeholder="At least 8 characters"
                    value={password} onChange={e => { setPassword(e.target.value); setErrs(p => ({...p, password: ''})); }}
                    className={inputClass('password') + ' pr-12'}
                  />
                  <button type="button" onClick={() => setShowPwd(!showPwd)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    aria-label={showPwd ? 'Hide' : 'Show'}
                  >
                    {showPwd
                      ? <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                      : <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    }
                  </button>
                </div>
                <FieldError msg={errs.password} />
              </div>

              {/* Password strength meter */}
              {password && (
                <div className="mb-4">
                  <div className="flex gap-1 mb-1">
                    {[1,2,3,4].map(i => (
                      <div key={i} className={`h-1 flex-1 rounded-full transition-all duration-300 ${i <= strength ? strengthColor : 'bg-slate-200'}`} />
                    ))}
                  </div>
                  <p className="text-xs text-slate-500">Strength: <span className="font-medium">{strengthLabel}</span></p>
                </div>
              )}

              {/* Confirm Password */}
              <div className="mb-6">
                <label htmlFor="reg-confirm" className="block text-sm font-medium text-slate-700 mb-1.5">Confirm Password</label>
                <div className="relative">
                  <input
                    id="reg-confirm" name="confirm_password"
                    type={showConf ? 'text' : 'password'} required autoComplete="new-password"
                    placeholder="Re-enter your password"
                    value={confirm} onChange={e => { setConfirm(e.target.value); setErrs(p => ({...p, confirm: ''})); }}
                    className={inputClass('confirm') + ' pr-12'}
                  />
                  <button type="button" onClick={() => setShowConf(!showConf)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    aria-label={showConf ? 'Hide' : 'Show'}
                  >
                    {showConf
                      ? <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                      : <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    }
                  </button>
                </div>
                {confirm && password && confirm === password && (
                  <p className="mt-1 text-xs text-emerald-600 flex items-center gap-1">
                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                    Passwords match
                  </p>
                )}
                <FieldError msg={errs.confirm} />
              </div>

              <button
                type="submit"
                id="register-submit-btn"
                disabled={loading}
                className="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2 text-sm"
              >
                {loading ? (
                  <>
                    <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Creating account…
                  </>
                ) : (
                  <>
                    Create My Account
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </>
                )}
              </button>

            </form>

            <p className="mt-6 text-center text-sm text-slate-500">
              Already have an account?{' '}
              <a href="login.php" className="text-brand-500 hover:text-brand-600 font-semibold transition-colors">
                Sign in →
              </a>
            </p>
          </div>
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('register-root')).render(<RegisterPage />);
  })();
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
