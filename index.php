<?php
declare(strict_types=1);

/**
 * IndiaYatra — Travel Booking Marketplace
 *
 * Multi-vertical React client shell with live filters, mock currency adjustments,
 * competitor comparison table, wishlist action menu, and gamified statistics.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';

$db = getPDO();
$initialPackages = [];
$wishlist = [];
$loadError = false;

try {
    $stmt = $db->prepare("SELECT * FROM packages WHERE vertical_type = 'package' ORDER BY base_price ASC LIMIT 60");
    $stmt->execute();
    $initialPackages = $stmt->fetchAll();

    require_once __DIR__ . '/config/mock-api.php';
    if (method_exists('MockTravelApiEngine', 'getLiveAdjustmentsBatch')) {
        $ids = array_map(static fn($p) => (int)$p['package_id'], $initialPackages);
        $adjustments = MockTravelApiEngine::getLiveAdjustmentsBatch($ids);
        foreach ($initialPackages as &$pkg) {
            $adj = $adjustments[(int)$pkg['package_id']] ?? null;
            $pkg['live_price']        = $adj['live_price'] ?? $pkg['base_price'];
            $pkg['live_availability'] = $adj['live_availability'] ?? $pkg['availability'];
            $pkg['drift_percent']     = $adj['drift_percent'] ?? 0;
        }
        unset($pkg);
    } else {
        foreach ($initialPackages as &$pkg) {
            $adj = MockTravelApiEngine::getLiveAdjustments(
                (int)$pkg['package_id'],
                (float)$pkg['base_price'],
                (int)$pkg['availability']
            );
            $pkg['live_price']        = $adj['live_price'];
            $pkg['live_availability'] = $adj['live_availability'];
            $pkg['drift_percent']     = $adj['drift_percent'];
        }
        unset($pkg);
    }

    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $wStmt = $db->prepare("SELECT package_id FROM wishlists WHERE user_id = :uid");
        $wStmt->execute(['uid' => $userId]);
        $wishlist = array_map('intval', $wStmt->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (PDOException $e) {
    error_log('[IndiaYatra] Initial package load failed: ' . $e->getMessage());
    $loadError = true;
    $initialPackages = [];
    $wishlist = [];
}

$featuredDestinations = [];
try {
    $fStmt = $db->prepare(
        "SELECT destination_id, name, description, image_url, map_embed_url,
                youtube_url, gallery_images, state
         FROM featured_destinations
         WHERE is_active = 1
         ORDER BY sort_order ASC
         LIMIT 12"
    );
    $fStmt->execute();
    $featuredDestinations = $fStmt->fetchAll();
    foreach ($featuredDestinations as &$d) {
        $decoded = json_decode($d['gallery_images'] ?? '[]', true);
        $d['gallery_images'] = is_array($decoded) ? $decoded : [];
    }
    unset($d);
} catch (PDOException $e) {
    error_log('[IndiaYatra] Featured destinations load failed: ' . $e->getMessage());
    $featuredDestinations = [];
}

$initialStateJson = json_encode([
    'packages'              => $initialPackages,
    'wishlist'              => $wishlist,
    'loadError'             => $loadError,
    'featuredDestinations'  => $featuredDestinations,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

if ($initialStateJson === false) {
    $initialStateJson = json_encode([
        'packages' => [], 'wishlist' => [], 'loadError' => true, 'featuredDestinations' => [],
    ]);
}

$GLOBALS['pageTitle'] = 'Discover Incredible India — Flights, Hotels & Tours';
require_once __DIR__ . '/includes/header.php';
?>

<script>
  window.__INITIAL_STATE__ = <?= $initialStateJson ?>;
</script>

<main class="min-h-screen pb-16" style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">

  <section class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-orange-950 text-white overflow-hidden py-16 md:py-24 border-b border-orange-500/20">
    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #f97316 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div class="absolute -right-24 top-0 bottom-0 w-2/3 hidden lg:block" style="background: linear-gradient(135deg, rgba(0,180,216,0.18), rgba(0,180,216,0)); clip-path: polygon(30% 0, 100% 0, 100% 100%, 0% 100%);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center md:text-left">
      <span class="inline-flex items-center gap-1.5 bg-orange-500/10 text-orange-400 border border-orange-500/30 text-xs font-semibold px-4.5 py-1.5 rounded-full mb-6">
        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
        Seamless Bookings across India
      </span>
      <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-bold leading-tight mb-4">
        Explore Every <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300">Destination</span>
      </h1>
      <p class="text-base sm:text-lg text-slate-300 leading-relaxed mb-6 max-w-2xl">
        Compare flight prices, book luxury boutique hotels, and reserve premium tour bundles featuring interactive maps and gamified rewards.
      </p>
    </div>
  </section>

  <div id="marketplace-root" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8"></div>

</main>

<script type="text/babel">
(function() {
  const { useState, useEffect, useRef, useMemo, useCallback } = React;

  const session = window.__SESSION__ || {};
  const initialState = window.__INITIAL_STATE__ || { packages: [], wishlist: [], loadError: false, featuredDestinations: [] };
  const csrfToken = window.__CSRF_TOKEN__ || '';

  const formatPrice = typeof window.formatPrice === 'function'
    ? window.formatPrice
    : (amount, currency) => `${currency || '₹'}${Number(amount).toLocaleString('en-IN')}`;

  const normalizePackages = (list) =>
    (list || []).map(p => ({ ...p, package_id: Number(p.package_id) }));

  const FILTER_GROUPS = {
    zones: ['North', 'South', 'East', 'West', 'North-East', 'Central'],
    grades: ['A+', 'A', 'B', 'C', 'D', 'F'],
    airlines: ['IndiGo', 'Air India'],
    roomTiers: ['Deluxe Suite', 'Executive Suite'],
  };

  function useToast() {
    const [toast, setToast] = useState(null);
    const timerRef = useRef(null);
    const show = useCallback((message, tone = 'info') => {
      clearTimeout(timerRef.current);
      setToast({ message, tone });
      timerRef.current = setTimeout(() => setToast(null), 3500);
    }, []);
    useEffect(() => () => clearTimeout(timerRef.current), []);
    return [toast, show];
  }

  function Toast({ toast }) {
    if (!toast) return null;
    const toneClasses = toast.tone === 'error'
      ? 'bg-red-600'
      : toast.tone === 'success'
        ? 'bg-emerald-600'
        : 'bg-slate-800';
    return (
      <div
        role="status"
        aria-live="polite"
        className={`fixed bottom-6 left-1/2 -translate-x-1/2 ${toneClasses} text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-lg z-50`}
      >
        {toast.message}
      </div>
    );
  }

  function Marketplace() {
    const [activeTab, setActiveTab] = useState('package');

    const featuredModalRef = useRef(null);
    const [featuredModalOpen, setFeaturedModalOpen] = useState(false);
    const [featuredModalDest, setFeaturedModalDest] = useState(null);

    useEffect(() => {
      document.body.style.overflow = featuredModalOpen ? 'hidden' : '';
      return () => { document.body.style.overflow = ''; };
    }, [featuredModalOpen]);

    const [packages, setPackages] = useState(() => normalizePackages(initialState.packages));
    const [wishlist, setWishlist] = useState(() => (initialState.wishlist || []).map(Number));
    const [featuredDestinations] = useState(() => initialState.featuredDestinations || []);
    const [loading, setLoading] = useState(false);
    const [fetchError, setFetchError] = useState(initialState.loadError ? 'We had trouble loading listings. Please retry.' : null);
    const [toast, showToast] = useToast();

    const [currency, setCurrency] = useState(window.__CURRENCY__);
    useEffect(() => {
      const handleCurrencyChange = (e) => setCurrency(e.detail);
      window.addEventListener('currencyChange', handleCurrencyChange);
      return () => window.removeEventListener('currencyChange', handleCurrencyChange);
    }, []);

    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [maxPrice, setMaxPrice] = useState(50000);
    const [selectedZones, setSelectedZones] = useState([]);
    const [selectedGrades, setSelectedGrades] = useState([]);
    const [selectedAirlines, setSelectedAirlines] = useState([]);
    const [selectedRoomTiers, setSelectedRoomTiers] = useState([]);

    const [openCardMenuId, setOpenCardMenuId] = useState(null);
    const cardMenuRef = useRef(null);
    const inFlightController = useRef(null);

    useEffect(() => {
      const closeMenu = (e) => {
        if (cardMenuRef.current && !cardMenuRef.current.contains(e.target)) {
          setOpenCardMenuId(null);
        }
      };
      const closeOnEscape = (e) => { if (e.key === 'Escape') setOpenCardMenuId(null); };
      document.addEventListener('mousedown', closeMenu);
      document.addEventListener('keydown', closeOnEscape);
      return () => {
        document.removeEventListener('mousedown', closeMenu);
        document.removeEventListener('keydown', closeOnEscape);
      };
    }, []);

    useEffect(() => {
      const t = setTimeout(() => setDebouncedSearch(search), 350);
      return () => clearTimeout(t);
    }, [search]);

    const fetchFilteredPackages = useCallback(async () => {
      inFlightController.current?.abort();
      const controller = new AbortController();
      inFlightController.current = controller;

      setLoading(true);
      setFetchError(null);
      try {
        const queryParams = new URLSearchParams({
          vertical_type: activeTab,
          search: debouncedSearch,
          max_price: String(maxPrice),
          zones: selectedZones.join(','),
          grades: selectedGrades.join(','),
          airlines: selectedAirlines.join(','),
          room_tiers: selectedRoomTiers.join(','),
        });
        const res = await fetch(`api/get-packages.php?${queryParams.toString()}`, {
          signal: controller.signal,
          headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error(`Request failed (${res.status})`);
        const data = await res.json();
        if (data.status === 'success') {
          setPackages(normalizePackages(data.packages));
        } else {
          setFetchError(data.message || 'Could not load listings.');
        }
      } catch (err) {
        if (err.name === 'AbortError') return;
        console.error('Failed to load inventory packages.', err);
        setFetchError('Could not reach the inventory service. Please try again.');
      } finally {
        if (inFlightController.current === controller) setLoading(false);
      }
    }, [activeTab, debouncedSearch, maxPrice, selectedZones, selectedGrades, selectedAirlines, selectedRoomTiers]);

    useEffect(() => {
      fetchFilteredPackages();
      return () => inFlightController.current?.abort();
    }, [fetchFilteredPackages]);

    const handleToggleWishlist = async (pkgId) => {
      if (!session.loggedIn) {
        showToast('Please log in to add items to your wishlist.', 'info');
        window.location.href = 'login.php';
        return;
      }

      try {
        const formData = new FormData();
        formData.append('package_id', String(pkgId));
        formData.append('csrf_token', csrfToken);

        const res = await fetch('api/toggle-wishlist.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
          const nextWishlist = (data.wishlist || []).map(Number);
          setWishlist(nextWishlist);
          window.__SESSION__.wishlist = nextWishlist;
        } else {
          showToast(data.message || 'Failed to update wishlist', 'error');
        }
      } catch (err) {
        console.error('Error toggling wishlist', err);
        showToast('Failed to update wishlist. Please try again.', 'error');
      } finally {
        setOpenCardMenuId(null);
      }
    };

    const handleSaveTrip = (title) => {
      showToast(`"${title}" saved to your trip planner draft.`, 'success');
      setOpenCardMenuId(null);
    };

    const makeToggleHandler = (setter) => (value) => {
      setter(prev => prev.includes(value) ? prev.filter(v => v !== value) : [...prev, value]);
    };
    const handleZoneToggle = makeToggleHandler(setSelectedZones);
    const handleGradeToggle = makeToggleHandler(setSelectedGrades);
    const handleAirlineToggle = makeToggleHandler(setSelectedAirlines);
    const handleRoomTierToggle = makeToggleHandler(setSelectedRoomTiers);

    const clearAllFilters = () => {
      setSearch('');
      setDebouncedSearch('');
      setMaxPrice(50000);
      setSelectedZones([]);
      setSelectedGrades([]);
      setSelectedAirlines([]);
      setSelectedRoomTiers([]);
    };

    const wishlistTitleById = useMemo(() => {
      const map = new Map();
      packages.forEach(p => map.set(p.package_id, p.title));
      return map;
    }, [packages]);

    return (
      <div className="flex flex-col gap-8">
        <Toast toast={toast} />

        {/* Netflix-style featured hero banner — top package, dark gradient overlay */}
        {packages[0] && (
          <div className="relative rounded-3xl overflow-hidden h-72 md:h-96 shadow-xl">
            <img
              src={packages[0].image_url || 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=1200&q=80'}
              alt={packages[0].title}
              className="absolute inset-0 w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
            <div className="absolute bottom-0 left-0 right-0 p-6 md:p-10 text-white max-w-2xl">
              <span className="inline-block bg-orange-500 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md mb-3">Featured</span>
              <h2 className="font-display text-2xl md:text-4xl font-bold mb-2">{packages[0].title}</h2>
              <p className="text-sm md:text-base text-slate-200 line-clamp-2 mb-4">{packages[0].description}</p>
              <a
                href={`package-detail.php?id=${packages[0].package_id}`}
                className="inline-flex items-center gap-2 bg-white text-slate-900 font-bold text-sm px-5 py-2.5 rounded-xl hover:bg-slate-100 transition-colors"
              >
                View Details <i className="fa-solid fa-arrow-right"></i>
              </a>
            </div>
          </div>
        )}

        <div
          ref={featuredModalRef}
          id="featuredDestinationModal"
          className={`fixed inset-0 z-[1000] flex items-center justify-center transition-all duration-300 ${featuredModalOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'}`}
          aria-hidden={featuredModalOpen ? 'false' : 'true'}
        >
          <div
            className="absolute inset-0 bg-black/40 backdrop-blur-md"
            onClick={() => setFeaturedModalOpen(false)}
            role="presentation"
          ></div>
          <div
            className={`relative z-10 bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl p-8 max-w-2xl w-[92%] max-h-[85vh] overflow-y-auto transition-all duration-300 ${featuredModalOpen ? 'scale-100 translate-y-0 opacity-100' : 'scale-95 translate-y-4 opacity-0'}`}
            role="dialog" aria-modal="true" aria-labelledby="featuredModalTitle"
          >
            <button className="absolute top-4 right-4 w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-800 text-xl transition-colors" type="button" aria-label="Close" onClick={() => setFeaturedModalOpen(false)}>×</button>

            <div className="modal-header">
              <h2 id="featuredModalTitle">{featuredModalDest?.name || 'Featured Destination'}</h2>
              <p id="featuredModalSubtitle" className="text-sm text-slate-500 mt-2">
                {featuredModalDest?.state ? `📍 ${featuredModalDest.state}` : ''}
              </p>
            </div>

            <div className="modal-body" style={{ gap: '1.25rem' }}>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="rounded-2xl overflow-hidden border border-slate-200 bg-slate-50">
                  <img
                    src={featuredModalDest?.image_url || featuredModalDest?.hero_image_url || ''}
                    alt={featuredModalDest?.name || 'Destination'}
                    className="w-full h-64 object-cover"
                  />
                </div>
                <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white p-4">
                  <h3 className="text-sm font-extrabold uppercase tracking-wider text-orange-500 mb-2">Description</h3>
                  <p className="text-sm text-slate-600 leading-relaxed">{featuredModalDest?.description || ''}</p>

                  <div className="mt-4">
                    <h3 className="text-sm font-extrabold uppercase tracking-wider text-orange-500 mb-2">Verified Map</h3>
                    {featuredModalDest?.map_embed_url ? (
                      <div className="w-full rounded-xl overflow-hidden border border-slate-100 bg-slate-50" style={{ height: 240 }}>
                        <iframe
                          src={featuredModalDest.map_embed_url}
                          className="w-full h-full border-0"
                          loading="lazy"
                          title={`Map - ${featuredModalDest.name}`}
                        ></iframe>
                      </div>
                    ) : (
                      <div className="text-xs text-slate-400">Map unavailable.</div>
                    )}
                  </div>
                </div>
              </div>

              <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white">
                <h3 className="px-5 pt-5 text-sm font-extrabold uppercase tracking-wider text-orange-500">YouTube</h3>
                <div className="p-5">
                  <div
                    className="relative w-full"
                    style={{ paddingTop: '56.25%' }}
                  >
                    {featuredModalDest?.youtube_url ? (
                      <iframe
                        className="absolute top-0 left-0 w-full h-full"
                        src={(() => {
                          const u = String(featuredModalDest.youtube_url || '');
                          const idMatch1 = u.match(/v=([\w-]{6,})/);
                          const idMatch2 = u.match(/youtu\.be\/([\w-]{6,})/);
                          const id = (idMatch1 && idMatch1[1]) || (idMatch2 && idMatch2[1]) || '';
                          if (!id) return u;
                          return `https://www.youtube-nocookie.com/embed/${id}?autoplay=0&rel=0`;
                        })()}
                        title={`YouTube - ${featuredModalDest.name}`}
                        frameBorder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                        loading="lazy"
                      ></iframe>
                    ) : (
                      <div className="text-xs text-slate-400">Video unavailable.</div>
                    )}
                  </div>
                </div>
              </div>

              <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white">
                <h3 className="px-5 pt-5 text-sm font-extrabold uppercase tracking-wider text-orange-500">Gallery</h3>
                <div className="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                  {(featuredModalDest?.gallery_images || []).slice(0, 9).map((src, idx) => (
                    <div key={`${src}-${idx}`} className="rounded-xl overflow-hidden border border-slate-100 bg-slate-50" style={{ height: 110 }}>
                      <img src={src} alt={`${featuredModalDest.name} gallery ${idx + 1}`} className="w-full h-full object-cover" loading="lazy" />
                    </div>
                  ))}
                  {(featuredModalDest?.gallery_images || []).length === 0 && (
                    <div className="col-span-3 text-xs text-slate-400">Gallery unavailable.</div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {featuredDestinations.length > 0 && (
          <FeaturedDestinations
            destinations={featuredDestinations}
            onOpenDestination={(dest) => {
              setFeaturedModalDest(dest);
              setFeaturedModalOpen(true);
            }}
          />
        )}

        <div className="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 flex justify-center md:justify-start gap-2 max-w-xl mx-auto md:mx-0" role="tablist" aria-label="Booking vertical">
          {[
            { key: 'flight', label: 'Flights', icon: 'fa-plane', emoji: '✈️' },
            { key: 'hotel', label: 'Hotels', icon: 'fa-hotel', emoji: '🏨' },
            { key: 'package', label: 'Packages', icon: 'fa-cubes', emoji: '📦' },
          ].map(tab => (
            <button
              key={tab.key}
              role="tab"
              aria-selected={activeTab === tab.key}
              onClick={() => { setActiveTab(tab.key); clearAllFilters(); }}
              className={`flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 ${activeTab === tab.key ? 'bg-orange-500 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100'}`}
            >
              <i className={`fa-solid ${tab.icon}`}></i> {tab.emoji} {tab.label}
            </button>
          ))}
        </div>

        <div className="flex flex-col lg:flex-row gap-8 items-start">

          <aside className="w-full lg:w-72 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex-shrink-0 lg:sticky lg:top-24">
            <div className="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
              <span className="font-bold text-slate-800 flex items-center gap-2 text-base">
                <i className="fa-solid fa-sliders text-orange-500"></i> Marketplace Filters
              </span>
              <button onClick={clearAllFilters} className="text-xs text-orange-500 hover:text-orange-600 font-bold transition-colors">
                Clear All
              </button>
            </div>

            <div className="mb-5">
              <label htmlFor="search-input" className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Search</label>
              <div className="relative">
                <i className="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input
                  id="search-input"
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder={activeTab === 'flight' ? 'Airline or City...' : 'Destination, hotel tier...'}
                  className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                />
              </div>
            </div>

            <div className="mb-5">
              <div className="flex justify-between items-center text-xs font-bold text-slate-400 uppercase mb-2">
                <span>Max Budget</span>
                <span className="text-orange-500 text-sm font-extrabold">{formatPrice(maxPrice, currency)}</span>
              </div>
              <input
                type="range"
                min="1000"
                max="50000"
                step="500"
                value={maxPrice}
                aria-valuetext={formatPrice(maxPrice, currency)}
                onChange={(e) => setMaxPrice(Number(e.target.value))}
                className="w-full accent-orange-500 cursor-pointer h-1.5 bg-slate-200 rounded-lg appearance-none"
              />
              <div className="flex justify-between text-[10px] font-bold text-slate-400 mt-1">
                <span>{formatPrice(1000, currency)}</span>
                <span>{formatPrice(50000, currency)}</span>
              </div>
            </div>

            <div className="mb-5">
              <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Zone Region</span>
              <div className="space-y-2">
                {FILTER_GROUPS.zones.map(zone => (
                  <label key={zone} className="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-800 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={selectedZones.includes(zone)}
                      onChange={() => handleZoneToggle(zone)}
                      className="rounded border-slate-300 text-orange-500 focus:ring-orange-500 w-4 h-4 accent-orange-500"
                    />
                    <span>{zone}</span>
                  </label>
                ))}
              </div>
            </div>

            <div className="mb-5">
              <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Quality Grade</span>
              <div className="grid grid-cols-3 gap-2">
                {FILTER_GROUPS.grades.map(grade => {
                  const isChecked = selectedGrades.includes(grade);
                  return (
                    <button
                      key={grade}
                      type="button"
                      aria-pressed={isChecked}
                      onClick={() => handleGradeToggle(grade)}
                      className={`py-1 rounded-lg border text-xs font-bold transition-all ${isChecked ? 'bg-orange-500 border-orange-500 text-white shadow-sm' : 'border-slate-200 hover:border-slate-300 text-slate-600'}`}
                    >
                      {grade}
                    </button>
                  );
                })}
              </div>
            </div>

            {activeTab === 'flight' && (
              <div className="mb-5 pt-4 border-t border-slate-100">
                <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Airlines</span>
                <div className="space-y-2">
                  {FILTER_GROUPS.airlines.map(airline => (
                    <label key={airline} className="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-800 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={selectedAirlines.includes(airline)}
                        onChange={() => handleAirlineToggle(airline)}
                        className="rounded border-slate-300 text-orange-500 focus:ring-orange-500 w-4 h-4 accent-orange-500"
                      />
                      <span>{airline}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            {activeTab === 'hotel' && (
              <div className="mb-5 pt-4 border-t border-slate-100">
                <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Room Tiers</span>
                <div className="space-y-2">
                  {FILTER_GROUPS.roomTiers.map(tier => (
                    <label key={tier} className="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-800 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={selectedRoomTiers.includes(tier)}
                        onChange={() => handleRoomTierToggle(tier)}
                        className="rounded border-slate-300 text-orange-500 focus:ring-orange-500 w-4 h-4 accent-orange-500"
                      />
                      <span>{tier}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            {session.loggedIn && (
              <div className="mt-6 pt-5 border-t border-slate-200">
                <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
                  ❤️ My Wishlist ({wishlist.length})
                </span>
                {wishlist.length > 0 ? (
                  <div className="space-y-2 max-h-44 overflow-y-auto pr-1">
                    {wishlist.map(id => {
                      const title = wishlistTitleById.get(id) || `Package #${id}`;
                      return (
                        <div key={id} className="flex justify-between items-center gap-2 bg-slate-50 p-2 rounded-lg border border-slate-150">
                          <a href={`package-detail.php?id=${id}`} className="text-xs font-semibold text-slate-700 hover:text-orange-500 truncate block flex-1">
                            {title}
                          </a>
                          <button
                            onClick={() => handleToggleWishlist(id)}
                            className="text-slate-400 hover:text-red-500 text-xs focus:outline-none"
                            aria-label={`Remove ${title} from wishlist`}
                          >
                            <i className="fa-solid fa-trash"></i>
                          </button>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-xs text-slate-400 italic">No wishlisted items yet.</p>
                )}
              </div>
            )}
          </aside>

          <div className="flex-1 w-full" aria-live="polite">
            {fetchError && (
              <div className="bg-red-50 border border-red-200 text-red-700 text-sm font-semibold rounded-xl p-4 mb-4 flex items-center justify-between gap-4">
                <span>{fetchError}</span>
                <button onClick={fetchFilteredPackages} className="underline font-bold whitespace-nowrap">Retry</button>
              </div>
            )}

            {loading ? (
              <div className="flex flex-col items-center justify-center py-24 text-slate-400">
                <div className="w-10 h-10 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                <p className="font-bold text-sm">Searching inventory...</p>
              </div>
            ) : packages.length > 0 ? (
              <div className="flex gap-6 overflow-x-auto pb-3 snap-x snap-mandatory scroll-smooth -mx-1 px-1">
                {packages.map(pkg => (
                  <div key={pkg.package_id} className="w-80 flex-shrink-0 snap-start">
                    <PackageCard
                      pkg={pkg}
                      isWishlisted={wishlist.includes(pkg.package_id)}
                      isMenuOpen={openCardMenuId === pkg.package_id}
                      cardMenuRef={cardMenuRef}
                      currency={currency}
                      formatPrice={formatPrice}
                      onToggleMenu={() => setOpenCardMenuId(prev => prev === pkg.package_id ? null : pkg.package_id)}
                      onToggleWishlist={() => handleToggleWishlist(pkg.package_id)}
                      onSaveTrip={() => handleSaveTrip(pkg.title)}
                    />
                  </div>
                ))}
              </div>
            ) : (
              <div className="bg-white border border-slate-200 rounded-2xl p-12 text-center shadow-sm">
                <div className="text-4xl mb-3">📍</div>
                <h3 className="font-bold text-slate-700 mb-1">No Listings Found</h3>
                <p className="text-sm text-slate-400 max-w-md mx-auto">
                  No matching {activeTab} inventory matches your criteria. Try widening your price filter.
                </p>
              </div>
            )}
          </div>

        </div>

      </div>
    );
  }

  function FeaturedDestinations({ destinations, onOpenDestination }) {
    const [expandedId, setExpandedId] = useState(null);

    return (
      <section aria-label="Featured destinations" className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div className="flex items-center justify-between mb-5">
          <h2 className="font-display text-xl font-bold text-slate-800">Featured Destinations</h2>
          <span className="text-xs text-slate-400 font-semibold hidden sm:block">Scroll for more →</span>
        </div>
        <div className="flex gap-5 overflow-x-auto pb-3 -mx-1 px-1 snap-x snap-mandatory scroll-smooth">
          {destinations.map(dest => {
            const isExpanded = expandedId === dest.destination_id;
            return (
              <div key={dest.destination_id} className="w-72 flex-shrink-0 snap-start border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <button
                  type="button"
                  onClick={() => onOpenDestination && onOpenDestination(dest)}
                  className="block w-full text-left group"
                  aria-label={`Open featured destination: ${dest.name}`}
                >
                  <div className="h-40 w-full overflow-hidden bg-slate-100 relative">
                    <img
                      src={dest.image_url || 'https://images.unsplash.com/photo-1524492412937-b28074a5d7da?auto=format&fit=crop&w=600&q=80'}
                      alt={dest.name}
                      loading="lazy"
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                    <span className="absolute bottom-2 left-2 bg-black/55 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md">
                      {dest.state}
                    </span>
                  </div>
                  <div className="p-4 pb-0">
                    <h3 className="font-display font-bold text-slate-800 truncate">{dest.name}</h3>
                    <p className="text-xs text-slate-500 mt-1.5 line-clamp-2 leading-relaxed">{dest.description}</p>
                  </div>
                </button>

                {dest.map_embed_url && (
                  <div className="px-4 pb-4">
                    <button
                      type="button"
                      onClick={() => setExpandedId(isExpanded ? null : dest.destination_id)}
                      className="mt-2.5 text-xs font-bold text-orange-500 hover:text-orange-600 inline-flex items-center gap-1"
                    >
                      {isExpanded ? 'Hide map' : 'View map'} <i className={`fa-solid fa-chevron-${isExpanded ? 'up' : 'down'} text-[9px]`}></i>
                    </button>

                    {isExpanded && (
                      <div className="w-full h-48 rounded-xl overflow-hidden relative mt-2 border border-slate-200">
                        <iframe
                          src={dest.map_embed_url}
                          className="w-full h-full border-0"
                          loading="lazy"
                          title={`Map of ${dest.name}`}
                        ></iframe>
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </section>
    );
  }

  function PackageCard({ pkg, isWishlisted, isMenuOpen, cardMenuRef, currency, formatPrice, onToggleMenu, onToggleWishlist, onSaveTrip }) {
    const base = Number(pkg.live_price ?? pkg.base_price);
    const [showMap, setShowMap] = useState(false);

    const competitors = [
      { label: 'MakeMyTrip', price: pkg.comp_price_makemytrip != null ? Number(pkg.comp_price_makemytrip) : null },
      { label: 'Yatra', price: pkg.comp_price_yatra != null ? Number(pkg.comp_price_yatra) : null },
    ].filter(c => c.price != null && Number.isFinite(c.price));

    return (
      <article className="bg-white rounded-3xl border border-slate-200/70 overflow-hidden transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:border-orange-200 flex flex-col relative group shadow-sm">
        <div className="relative h-44 overflow-hidden bg-slate-100 flex-shrink-0">
          <img
            src={pkg.image_url || 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80'}
            alt={pkg.title}
            loading="lazy"
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
          <div className="absolute top-3 left-3 flex gap-1.5">
            <span className="bg-slate-900/80 backdrop-blur-sm text-white font-semibold text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md">
              {pkg.zone}
            </span>
            <span className="bg-orange-500 text-white font-bold text-[10px] px-2 py-0.5 rounded-md shadow-sm">
              Grade {pkg.letter_grade}
            </span>
          </div>

          <div className="absolute top-3 right-3" ref={isMenuOpen ? cardMenuRef : null}>
            <button
              onClick={(e) => { e.preventDefault(); e.stopPropagation(); onToggleMenu(); }}
              className="w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm shadow-sm flex items-center justify-center text-slate-700 hover:text-orange-500 transition-colors focus:outline-none"
              aria-haspopup="menu"
              aria-expanded={isMenuOpen}
              aria-label={`Actions for ${pkg.title}`}
            >
              <i className="fa-solid fa-ellipsis-vertical"></i>
            </button>

            {isMenuOpen && (
              <div role="menu" className="absolute right-0 mt-1 w-40 bg-white border border-slate-200 rounded-xl shadow-xl py-1 z-30 animate-fadeIn text-slate-700">
                <button role="menuitem" onClick={onToggleWishlist} className="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-slate-50 flex items-center gap-2">
                  {isWishlisted ? '💔 Remove Wishlist' : '❤️ Add to Wishlist'}
                </button>
                <button role="menuitem" onClick={onSaveTrip} className="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-slate-50 flex items-center gap-2">
                  💾 Save Trip Draft
                </button>
              </div>
            )}
          </div>

          {isWishlisted && (
            <div className="absolute top-3 right-12 w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center text-xs shadow-md">
              <i className="fa-solid fa-heart"></i>
            </div>
          )}
        </div>

        <div className="p-5 flex-1 flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2 mb-1.5">
              <span className="text-[10px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded uppercase tracking-wider">
                {pkg.vertical_type}
              </span>
              {pkg.vertical_type === 'flight' && (
                <span className="text-xs font-bold text-slate-500">{pkg.airline_name}</span>
              )}
              {pkg.vertical_type === 'hotel' && (
                <span className="text-xs font-bold text-slate-500">{pkg.room_tier}</span>
              )}
            </div>

            <h3 className="font-display text-base font-bold text-slate-800 mb-2 truncate" title={pkg.title}>
              {pkg.title}
            </h3>
            <p className="text-xs text-slate-400 mb-3 flex items-center gap-1">
              <i className="fa-solid fa-location-dot"></i> {pkg.state} &bull; {pkg.duration_days} Days
              {pkg.map_coordinates && (
                <button
                  type="button"
                  onClick={() => setShowMap(prev => !prev)}
                  className="ml-1 text-orange-500 font-bold hover:text-orange-600"
                >
                  {showMap ? 'Hide map' : 'View map'}
                </button>
              )}
            </p>

            {showMap && pkg.map_coordinates && (
              <div className="w-full h-48 rounded-lg overflow-hidden relative mb-3">
                <iframe
                  src={pkg.map_coordinates}
                  className="w-full h-full border-0"
                  loading="lazy"
                  title={`Map for ${pkg.title}`}
                ></iframe>
              </div>
            )}

            {competitors.length > 0 && (
              <div className="mb-4 bg-slate-50 p-2.5 rounded-xl border border-slate-150 text-[10px]">
                <div className="font-bold text-slate-400 uppercase tracking-wider mb-1.5">Price Comparison</div>
                <div className="space-y-1">
                  <div className="flex justify-between items-center bg-orange-50 text-orange-800 font-extrabold px-1.5 py-0.5 rounded border border-orange-200">
                    <span>Our Platform</span>
                    <span>{formatPrice(base, currency)}</span>
                  </div>
                  {competitors.map(c => (
                    <div key={c.label} className="flex justify-between items-center text-slate-500 px-1.5">
                      <span>{c.label}</span>
                      <span className={c.price > base ? 'line-through' : ''}>{formatPrice(c.price, currency)}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-2">
            <div>
              <span className="text-[10px] text-slate-400 block font-semibold uppercase">Base Price</span>
              <span className="text-base font-extrabold text-orange-500">{formatPrice(base, currency)}</span>
            </div>

            <a
              href={`package-detail.php?id=${pkg.package_id}`}
              className="bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs py-2 px-4 rounded-xl shadow-sm transition-colors flex items-center gap-1.5"
            >
              Book Details <i className="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </article>
    );
  }

  const rootEl = document.getElementById('marketplace-root');
  if (!rootEl) {
    console.error('React root element #marketplace-root not found');
    return;
  }
  ReactDOM.createRoot(rootEl).render(<Marketplace />);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>