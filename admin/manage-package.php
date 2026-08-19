<?php
declare(strict_types=1);
/**
 * IndiaYatra — Admin Manage Package (Create / Edit)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';

// ── Auth ───────────────────────────────────────────────────────────────────
checkRole('admin');

$validZones = ['North','South','East','West','North-East','Central'];
$mode       = 'create';
$package    = null;
$formError  = '';

// ── Load existing package if editing ──────────────────────────────────────
if (isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    if ($editId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM packages WHERE package_id = :id LIMIT 1');
            $stmt->execute([':id' => $editId]);
            $package = $stmt->fetch();
            if ($package) {
                $mode = 'update';
            }
        } catch (PDOException $e) {
            error_log('[IndiaYatra][manage-package] ' . $e->getMessage());
            $formError = 'Could not load package data.';
        }
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $formError = 'Security token mismatch. Please reload and try again.';
    } else {
// Sanitise
        $title        = sanitizeInput($_POST['title']         ?? '');
        $verticalType = sanitizeInput($_POST['vertical_type'] ?? 'package');
        $zone         = sanitizeInput($_POST['zone']          ?? '');
        $state        = sanitizeInput($_POST['state']         ?? '');
        $description  = sanitizeInput($_POST['description']   ?? '');
        $highlights   = sanitizeInput($_POST['highlights']    ?? '');
        $basePrice    = (float)($_POST['base_price']          ?? 0);
        $durationDays = (int)($_POST['duration_days']         ?? 0);
        $availability = (int)($_POST['availability']          ?? 0);
        $imageUrl     = sanitizeInput($_POST['image_url']     ?? '');
        $postMode     = $_POST['mode'] ?? 'create';
        $postId       = (int)($_POST['package_id'] ?? 0);

        // Server-side validation
        if ($title === '') {
            $formError = 'Package title is required.';
        } elseif (!in_array($verticalType, ['flight','hotel','package'], true)) {
            $formError = 'Please select a valid listing type.';
        } elseif (!in_array($zone, $validZones, true)) {
            $formError = 'Please select a valid travel zone.';
        } elseif ($state === '') {
            $formError = 'State is required.';
        } elseif ($description === '') {
            $formError = 'Description is required.';
        } elseif ($basePrice <= 0) {
            $formError = 'Base price must be greater than 0.';
        } elseif ($durationDays <= 0) {
            $formError = 'Duration must be at least 1 day.';
        } elseif ($availability < 0) {
            $formError = 'Availability cannot be negative.';
        } else {
            // Validate image URL format if provided
            if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $formError = 'Please provide a valid image URL (starting with https://).';
            }
        }


        if ($formError === '') {
            try {
if ($postMode === 'update' && $postId > 0) {
                    $sql = 'UPDATE packages SET
                                title         = :title,
                                vertical_type = :vertical_type,
                                zone          = :zone,
                                state         = :state,
                                description   = :description,
                                highlights    = :highlights,
                                base_price    = :base_price,
                                duration_days = :duration_days,
                                availability  = :availability,
                                image_url     = :image_url
                            WHERE package_id  = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':title'          => $title,
                        ':vertical_type' => $verticalType,
                        ':zone'           => $zone,
                        ':state'          => $state,
                        ':description'    => $description,
                        ':highlights'     => $highlights,
                        ':base_price'     => $basePrice,
                        ':duration_days'  => $durationDays,
                        ':availability'   => $availability,
                        ':image_url'      => $imageUrl ?: null,
                        ':id'             => $postId,
                    ]);
                } else {
                    $sql = 'INSERT INTO packages
                                (title, vertical_type, zone, state, description, highlights, base_price, duration_days, availability, image_url)
                            VALUES
                                (:title, :vertical_type, :zone, :state, :description, :highlights, :base_price, :duration_days, :availability, :image_url)';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':title'          => $title,
                        ':vertical_type' => $verticalType,
                        ':zone'           => $zone,
                        ':state'          => $state,
                        ':description'    => $description,
                        ':highlights'     => $highlights,
                        ':base_price'     => $basePrice,
                        ':duration_days'  => $durationDays,
                        ':availability'   => $availability,
                        ':image_url'      => $imageUrl ?: null,
                    ]);
                }


                header('Location: dashboard.php');
                exit;

            } catch (PDOException $e) {
                error_log('[IndiaYatra][manage-package] ' . $e->getMessage());
                $formError = 'Database error while saving the package. Please try again.';
            }
        }

        // Re-populate form data on error
        $package = [
            'package_id'     => $postId,
            'title'          => $title,
            'vertical_type'  => $verticalType,
            'zone'           => $zone,
            'state'          => $state,
            'description'    => $description,
            'highlights'     => $highlights,
            'base_price'     => $basePrice,
            'duration_days'  => $durationDays,
            'availability'   => $availability,
            'image_url'      => $imageUrl,
        ];
        $mode = $postMode;

    }
}

$csrfToken  = generateCsrfToken();
$jsPackage  = json_encode($package, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsCsrf     = json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsMode     = json_encode($mode, JSON_HEX_TAG);
$jsError    = json_encode($formError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

$GLOBALS['pageTitle'] = ($mode === 'update' ? 'Edit Package' : 'Add New Package') . ' — IndiaYatra Admin';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

  <script>
    window.__PACKAGE__    = <?= $jsPackage ?>;
    window.__CSRF_TOKEN__ = <?= $jsCsrf ?>;
    window.__MODE__       = <?= $jsMode ?>;
    window.__FORM_ERROR__ = <?= $jsError ?>;
  </script>

  <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
      <a href="dashboard.php" class="hover:text-brand-500 transition-colors">Dashboard</a>
      <span class="text-slate-300">/</span>
      <span class="text-slate-700 font-medium"><?= $mode === 'update' ? 'Edit Package' : 'Add New Package' ?></span>
    </nav>

    <div id="manage-root"></div>

  </main>

  <script type="text/babel">
  (function () {
    const { useState, useEffect, useRef } = React;

    const FALLBACK_IMG = 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80';
    const ZONES        = ['North','South','East','West','North-East','Central'];

    function FieldError({ msg }) {
      if (!msg) return null;
      return (
        <p className="mt-1 text-xs text-red-500 flex items-center gap-1" role="alert">
          <svg className="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/>
          </svg>
          {msg}
        </p>
      );
    }

    function ManagePackage() {
      const pkg       = window.__PACKAGE__    || {};
      const mode      = window.__MODE__       || 'create';
      const csrfToken = window.__CSRF_TOKEN__ || '';
      const serverErr = window.__FORM_ERROR__ || '';

      const isUpdate = mode === 'update';

      // Form state — initialised from PHP-injected package data
      const [title,        setTitle]        = useState(pkg.title         || '');
      const [verticalType, setVerticalType] = useState(pkg.vertical_type || 'package');
      const [zone,         setZone]         = useState(pkg.zone          || '');
      const [state,        setState]        = useState(pkg.state         || '');
      const [description,  setDescription]  = useState(pkg.description   || '');
      const [highlights,   setHighlights]   = useState(pkg.highlights    || '');

      const [basePrice,    setBasePrice]    = useState(pkg.base_price    || '');
      const [durationDays, setDurationDays] = useState(pkg.duration_days || '');
      const [availability, setAvailability] = useState(pkg.availability  ?? '');
      const [imageUrl,     setImageUrl]     = useState(pkg.image_url     || '');
      const [imgPreviewSrc,setImgPreviewSrc]= useState(pkg.image_url     || '');
      const [loading,      setLoading]      = useState(false);
      const [errs,         setErrs]         = useState({});
      const [serverError,  setServerError]  = useState(serverErr);

      // Update image preview with debounce
      useEffect(() => {
        const t = setTimeout(() => {
          setImgPreviewSrc(imageUrl || '');
        }, 500);
        return () => clearTimeout(t);
      }, [imageUrl]);

      const validate = () => {
        const e = {};
        if (!title.trim()) e.title = 'Title is required.';
        if (!zone) e.zone = 'Please select a zone.';
        if (!state.trim()) e.state = 'State is required.';
        if (!description.trim()) e.description = 'Description is required.';
        if (!basePrice || parseFloat(basePrice) <= 0) e.basePrice = 'Price must be greater than 0.';
        if (!durationDays || parseInt(durationDays) <= 0) e.durationDays = 'Duration must be at least 1 day.';
        if (availability === '' || parseInt(availability) < 0) e.availability = 'Availability must be 0 or more.';
        if (imageUrl && !/^https?:\/\/.+/.test(imageUrl)) e.imageUrl = 'Please enter a valid URL starting with https://';
        setErrs(e);
        return Object.keys(e).length === 0;
      };

      const handleSubmit = (e) => {
        if (!validate()) { e.preventDefault(); return; }
        setLoading(true);
      };

      const inputCls = (field) =>
        `w-full border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 transition-shadow
         ${errs[field] ? 'border-red-400 focus:ring-red-300 bg-red-50' : 'border-slate-300 focus:ring-brand-400 focus:border-transparent'}`;

      return (
        <div className="bg-white rounded-2xl shadow-md overflow-hidden">
          <div className="h-1.5 bg-gradient-to-r from-brand-500 to-amber-400" />
          <div className="px-8 py-8">

            <h1 className="font-display text-2xl font-bold text-slate-900 mb-1">
              {isUpdate ? '✏️ Edit Package' : '✨ Add New Package'}
            </h1>
            <p className="text-sm text-slate-500 mb-7">
              {isUpdate ? 'Update the details of this travel package.' : 'Fill in the details to create a new travel package.'}
            </p>

            {serverError && (
              <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-2 animate-fadeIn" role="alert">
                <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/>
                </svg>
                <span className="flex-1 text-sm">{serverError}</span>
                <button onClick={() => setServerError('')} className="text-red-400 hover:text-red-600 transition-colors">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            )}

            <form method="POST" action="manage-package.php" onSubmit={handleSubmit} noValidate id="manage-pkg-form">
              <input type="hidden" name="csrf_token"  value={csrfToken} />
              <input type="hidden" name="mode"        value={mode} />
              {isUpdate && <input type="hidden" name="package_id" value={pkg.package_id || ''} />}

              {/* Row 1: Title */}
              <div className="mb-5">
                <label htmlFor="pkg-title" className="block text-sm font-medium text-slate-700 mb-1.5">Package Title <span className="text-red-500">*</span></label>
                <input id="pkg-title" name="title" type="text" required maxLength={150}
                  value={title} onChange={e => { setTitle(e.target.value); setErrs(p => ({...p, title:''})); }}
                  placeholder="e.g. Majestic Houseboat Journey — Alleppey"
                  className={inputCls('title')} />
                <FieldError msg={errs.title} />
              </div>

              {/* Row 2: Listing Type + Zone */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                  <label htmlFor="pkg-vertical" className="block text-sm font-medium text-slate-700 mb-1.5">Listing Type <span className="text-red-500">*</span></label>
                  <select id="pkg-vertical" name="vertical_type" required
                    value={verticalType}
                    onChange={e => { setVerticalType(e.target.value); setErrs(p => ({...p, verticalType:''})); }}
                    className={inputCls('verticalType') + ' bg-white'}>
                    <option value="flight">✈️ Flight</option>
                    <option value="hotel">🏨 Hotel</option>
                    <option value="package">📦 Package</option>
                  </select>
                  <FieldError msg={errs.verticalType} />
                </div>
                <div>
                  <label htmlFor="pkg-zone" className="block text-sm font-medium text-slate-700 mb-1.5">Zone <span className="text-red-500">*</span></label>
                  <select id="pkg-zone" name="zone" required
                    value={zone} onChange={e => { setZone(e.target.value); setErrs(p => ({...p, zone:''})); }}
                    className={inputCls('zone') + ' bg-white'}>
                    <option value="">Select Zone</option>
                    {ZONES.map(z => <option key={z} value={z}>{z}</option>)}
                  </select>
                  <FieldError msg={errs.zone} />
                </div>
              </div>

              {/* Row 3: State */}
              <div className="mb-5">
                <div>
                  <label htmlFor="pkg-state" className="block text-sm font-medium text-slate-700 mb-1.5">State <span className="text-red-500">*</span></label>
                  <input id="pkg-state" name="state" type="text" required maxLength={100}
                    value={state} onChange={e => { setState(e.target.value); setErrs(p => ({...p, state:''})); }}
                    placeholder="e.g. Kerala"
                    className={inputCls('state')} />
                  <FieldError msg={errs.state} />
                </div>
              </div>


              {/* Row 3: Description */}
              <div className="mb-5">
                <label htmlFor="pkg-desc" className="block text-sm font-medium text-slate-700 mb-1.5">Description <span className="text-red-500">*</span></label>
                <textarea id="pkg-desc" name="description" rows={4} required
                  value={description} onChange={e => { setDescription(e.target.value); setErrs(p => ({...p, description:''})); }}
                  placeholder="Detailed description of the travel package…"
                  className={inputCls('description')} />
                <FieldError msg={errs.description} />
              </div>

              {/* Row 4: Highlights */}
              <div className="mb-5">
                <label htmlFor="pkg-highlights" className="block text-sm font-medium text-slate-700 mb-1.5">Highlights</label>
                <input id="pkg-highlights" name="highlights" type="text"
                  value={highlights} onChange={e => setHighlights(e.target.value)}
                  placeholder="Houseboat stay|Spice tour|Lake sunset"
                  className={inputCls('highlights')} />
                <p className="mt-1 text-xs text-slate-400">Separate individual highlights with a <strong>|</strong> pipe character.</p>
              </div>

              {/* Row 5: Price, Duration, Availability */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                <div>
                  <label htmlFor="pkg-price" className="block text-sm font-medium text-slate-700 mb-1.5">Base Price (₹) <span className="text-red-500">*</span></label>
                  <input id="pkg-price" name="base_price" type="number" required min="1" step="0.01"
                    value={basePrice} onChange={e => { setBasePrice(e.target.value); setErrs(p => ({...p, basePrice:''})); }}
                    placeholder="18500.00"
                    className={inputCls('basePrice')} />
                  <FieldError msg={errs.basePrice} />
                </div>
                <div>
                  <label htmlFor="pkg-duration" className="block text-sm font-medium text-slate-700 mb-1.5">Duration (days) <span className="text-red-500">*</span></label>
                  <input id="pkg-duration" name="duration_days" type="number" required min="1"
                    value={durationDays} onChange={e => { setDurationDays(e.target.value); setErrs(p => ({...p, durationDays:''})); }}
                    placeholder="4"
                    className={inputCls('durationDays')} />
                  <FieldError msg={errs.durationDays} />
                </div>
                <div>
                  <label htmlFor="pkg-avail" className="block text-sm font-medium text-slate-700 mb-1.5">Seats Available <span className="text-red-500">*</span></label>
                  <input id="pkg-avail" name="availability" type="number" required min="0"
                    value={availability} onChange={e => { setAvailability(e.target.value); setErrs(p => ({...p, availability:''})); }}
                    placeholder="12"
                    className={inputCls('availability')} />
                  <FieldError msg={errs.availability} />
                </div>
              </div>

              {/* Row 6: Image URL + preview */}
              <div className="mb-7">
                <label htmlFor="pkg-image" className="block text-sm font-medium text-slate-700 mb-1.5">Image URL (optional)</label>
                <input id="pkg-image" name="image_url" type="url"
                  value={imageUrl} onChange={e => { setImageUrl(e.target.value); setErrs(p => ({...p, imageUrl:''})); }}
                  placeholder="https://images.unsplash.com/…"
                  className={inputCls('imageUrl')} />
                <FieldError msg={errs.imageUrl} />

                {/* Live preview */}
                {imgPreviewSrc && (
                  <div className="mt-3 relative rounded-xl overflow-hidden h-36 border border-slate-200">
                    <img
                      src={imgPreviewSrc}
                      alt="Package image preview"
                      onError={e => { e.target.src = FALLBACK_IMG; }}
                      className="w-full h-full object-cover"
                    />
                    <span className="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-0.5 rounded-full">Preview</span>
                  </div>
                )}
              </div>

              {/* Buttons */}
              <div className="flex flex-col sm:flex-row gap-3">
                <button
                  type="submit"
                  id="save-pkg-btn"
                  disabled={loading}
                  className="flex-1 bg-brand-500 hover:bg-brand-600 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-xl transition-colors duration-200 text-sm flex items-center justify-center gap-2"
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                      </svg>
                      Saving…
                    </>
                  ) : (
                    isUpdate ? '✓ Update Package' : '+ Create Package'
                  )}
                </button>
                <a
                  href="dashboard.php"
                  id="cancel-btn"
                  className="sm:w-40 text-center border border-slate-300 hover:bg-slate-100 text-slate-700 font-medium py-3 px-6 rounded-xl transition-colors duration-200 text-sm"
                >
                  Cancel
                </a>
              </div>

            </form>
          </div>
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('manage-root')).render(<ManagePackage />);
  })();
  </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
