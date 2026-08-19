<?php
declare(strict_types=1);
/**
 * IndiaYatra — My Bookings Page
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

// Must be logged in
requireLogin();

$bookings = [];
$dbError  = '';

try {
    $stmt = $pdo->prepare(
        'SELECT
            b.booking_id,
            b.seats_booked,
            b.base_total,
            b.total_gst,
            b.final_payable,
            b.status,
            b.booking_date,
            p.package_id,
            p.title,
            p.image_url,
            p.zone,
            p.state,
            p.duration_days
         FROM bookings b
         JOIN packages p ON b.package_id = p.package_id
         WHERE b.user_id = :uid
         ORDER BY b.booking_date DESC'
    );
    $stmt->execute([':uid' => (int)$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[IndiaYatra][my-bookings] ' . $e->getMessage());
    $dbError = 'Unable to load your bookings. Please try again shortly.';
}

$jsBookings = json_encode($bookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsDbError  = json_encode($dbError, JSON_HEX_TAG);
$jsUserName = json_encode($_SESSION['name'] ?? 'Traveller', JSON_HEX_TAG);

$GLOBALS['pageTitle']       = 'My Bookings — IndiaYatra';
$GLOBALS['pageDescription'] = 'View and manage all your IndiaYatra travel bookings in one place.';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

  <script>
    window.__BOOKINGS__  = <?= $jsBookings ?>;
    window.__DB_ERROR__  = <?= $jsDbError ?>;
    window.__USER_NAME__ = <?= $jsUserName ?>;
  </script>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <div id="bookings-root"></div>

  </main>

  <script type="text/babel">
  (function () {
    const FALLBACK_IMG = 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80';

    function fmtINR(amount) {
      return '₹' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    function fmtDate(dateStr) {
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function StatusBadge({ status }) {
      const styles = {
        'Confirmed': 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'Pending':   'bg-amber-100 text-amber-800 border-amber-200',
        'Cancelled': 'bg-red-100 text-red-700 border-red-200',
      };
      const icons = {
        'Confirmed': '✓',
        'Pending':   '⏳',
        'Cancelled': '✗',
      };
      return (
        <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border ${styles[status] || 'bg-slate-100 text-slate-600 border-slate-200'}`}>
          {icons[status]} {status}
        </span>
      );
    }

    function BookingCard({ booking }) {
      const { React: { useState } } = { React: window.React };
      const [imgSrc, setImgSrc] = window.React.useState(booking.image_url || FALLBACK_IMG);

      return (
        <article className="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col sm:flex-row">

          {/* Thumbnail */}
          <div className="sm:w-40 md:w-52 flex-shrink-0">
            <img
              src={imgSrc}
              alt={booking.title}
              onError={() => setImgSrc(FALLBACK_IMG)}
              className="w-full h-40 sm:h-full object-cover"
              loading="lazy"
            />
          </div>

          {/* Content */}
          <div className="flex-1 p-5 flex flex-col justify-between">
            <div>
              <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                <div className="flex-1 min-w-0">
                  <a href={`package-detail.php?id=${booking.package_id}`} className="font-display text-lg font-bold text-slate-900 hover:text-brand-500 transition-colors leading-snug">
                    {booking.title}
                  </a>
                  <p className="text-xs text-slate-500 mt-0.5">
                    {booking.zone} · {booking.state}
                  </p>
                </div>
                <StatusBadge status={booking.status} />
              </div>

              {/* Meta grid */}
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                <div className="bg-slate-50 rounded-xl p-2.5">
                  <p className="text-xs text-slate-400 mb-0.5">Booked On</p>
                  <p className="text-sm font-semibold text-slate-700">{fmtDate(booking.booking_date)}</p>
                </div>
                <div className="bg-slate-50 rounded-xl p-2.5">
                  <p className="text-xs text-slate-400 mb-0.5">Seats</p>
                  <p className="text-sm font-semibold text-slate-700">{booking.seats_booked} {parseInt(booking.seats_booked) === 1 ? 'seat' : 'seats'}</p>
                </div>
                <div className="bg-slate-50 rounded-xl p-2.5">
                  <p className="text-xs text-slate-400 mb-0.5">Duration</p>
                  <p className="text-sm font-semibold text-slate-700">{booking.duration_days} days</p>
                </div>
              </div>
            </div>

            {/* Price row */}
            <div className="flex flex-wrap items-center justify-between gap-2 mt-4 pt-3 border-t border-slate-100">
              <div className="space-y-0.5">
                <div className="flex items-center gap-3 text-xs text-slate-500">
                  <span>Base: {fmtINR(booking.base_total)}</span>
                  <span>GST: {fmtINR(booking.total_gst)}</span>
                </div>
                <p className="text-base font-bold text-brand-500">{fmtINR(booking.final_payable)}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">
                  Booking #{booking.booking_id}
                </span>
                <a
                  href={`package-detail.php?id=${booking.package_id}`}
                  className="text-xs font-semibold text-brand-500 hover:text-brand-600 transition-colors"
                >
                  View Package →
                </a>
              </div>
            </div>
          </div>
        </article>
      );
    }

    function MyBookings() {
      const bookings = window.__BOOKINGS__ || [];
      const dbError  = window.__DB_ERROR__ || '';
      const userName = window.__USER_NAME__ || 'Traveller';

      const confirmedCount = bookings.filter(b => b.status === 'Confirmed').length;
      const totalSpent     = bookings
        .filter(b => b.status === 'Confirmed')
        .reduce((sum, b) => sum + parseFloat(b.final_payable), 0);

      return (
        <div>
          {/* Page header */}
          <div className="mb-8">
            <h1 className="font-display text-3xl font-bold text-slate-900 mb-1">My Bookings</h1>
            <p className="text-sm text-slate-500">Welcome back, <strong>{userName.split(' ')[0]}</strong>. Here are all your travel bookings.</p>
          </div>

          {/* Summary bar */}
          {bookings.length > 0 && (
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
              <div className="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
                <p className="text-xs text-slate-500 mb-1">Total Bookings</p>
                <p className="text-2xl font-bold text-slate-900">{bookings.length}</p>
              </div>
              <div className="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
                <p className="text-xs text-slate-500 mb-1">Confirmed</p>
                <p className="text-2xl font-bold text-emerald-600">{confirmedCount}</p>
              </div>
              <div className="bg-white rounded-2xl shadow-sm p-4 border border-slate-100 col-span-2 sm:col-span-1">
                <p className="text-xs text-slate-500 mb-1">Total Spent</p>
                <p className="text-xl font-bold text-brand-500">{fmtINR(totalSpent)}</p>
              </div>
            </div>
          )}

          {/* DB error */}
          {dbError && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2 mb-6" role="alert">
              <svg className="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/>
              </svg>
              {dbError}
            </div>
          )}

          {/* Empty state */}
          {bookings.length === 0 && !dbError && (
            <div className="flex flex-col items-center justify-center py-20 text-center bg-white rounded-2xl shadow-sm">
              <div className="text-7xl mb-5">✈️</div>
              <h2 className="font-display text-2xl font-bold text-slate-700 mb-2">No Bookings Yet</h2>
              <p className="text-sm text-slate-500 mb-6 max-w-xs">
                You haven't booked any packages yet. Explore our curated India travel packages and start your journey!
              </p>
              <a
                href="index.php"
                className="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-3 px-8 rounded-xl transition-colors text-sm"
              >
                Explore Packages →
              </a>
            </div>
          )}

          {/* Booking cards */}
          {bookings.length > 0 && (
            <div className="space-y-4">
              {bookings.map(b => <BookingCard key={b.booking_id} booking={b} />)}
            </div>
          )}
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('bookings-root')).render(<MyBookings />);
  })();
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>