<?php
declare(strict_types=1);
/**
 * IndiaYatra — Admin Dashboard
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';

// ── Auth ───────────────────────────────────────────────────────────────────
checkRole('admin');

// ── Data queries ───────────────────────────────────────────────────────────
$metrics  = [];
$packages = [];
$dbError  = '';

try {
    // Query 1: Revenue & booking count (Confirmed only)
    $mStmt = $pdo->query(
        "SELECT
            COALESCE(SUM(final_payable), 0) AS total_revenue,
            COUNT(*) AS total_bookings
         FROM bookings
         WHERE status = 'Confirmed'"
    );
    $metrics['confirmed'] = $mStmt->fetch();

    // Query 2: Total package count
    $pStmt = $pdo->query('SELECT COUNT(*) AS total_packages FROM packages');
    $metrics['packages'] = $pStmt->fetch();

    // Query 3: Total user count
    $uStmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users WHERE role = 'customer'");
    $metrics['users'] = $uStmt->fetch();

    // Query 4: Package management table with booking counts
    $pkgStmt = $pdo->query(
        "SELECT
            p.package_id,
            p.title,
            p.zone,
            p.state,
            p.base_price,
            p.availability,
            p.duration_days,
            p.created_at,
            COUNT(b.booking_id) AS total_bookings
         FROM packages p
         LEFT JOIN bookings b ON p.package_id = b.package_id
         GROUP BY p.package_id
         ORDER BY p.created_at DESC"
    );
    $packages = $pkgStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[IndiaYatra][admin-dashboard] ' . $e->getMessage());
    $dbError = 'Failed to load dashboard data. Please check database connectivity.';
}

$jsAdminData = json_encode([
    'metrics'  => $metrics,
    'packages' => $packages,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsDbError   = json_encode($dbError, JSON_HEX_TAG);

$GLOBALS['pageTitle']       = 'Admin Dashboard — IndiaYatra';
$GLOBALS['pageDescription'] = 'IndiaYatra admin control panel — manage packages, view bookings, track revenue.';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

  <script>
    window.__ADMIN_DATA__ = <?= $jsAdminData ?>;
    window.__DB_ERROR__   = <?= $jsDbError ?>;
  </script>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div id="admin-root"></div>
  </main>

  <script type="text/babel">
  (function () {
    const { useState, useMemo } = React;

    function fmtINR(amount) {
      return '₹' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    /* Metric Card */
    function MetricCard({ icon, label, value, colorClass, sub }) {
      return (
        <div className={`rounded-2xl p-5 text-white shadow-md ${colorClass}`}>
          <div className="flex items-center justify-between mb-3">
            <span className="text-2xl">{icon}</span>
            <span className="text-xs font-semibold uppercase tracking-wider opacity-80">{label}</span>
          </div>
          <p className="text-3xl font-bold">{value}</p>
          {sub && <p className="text-xs opacity-75 mt-1">{sub}</p>}
        </div>
      );
    }

    /* Sort icon */
    function SortIcon({ field, sortField, sortDir }) {
      if (sortField !== field) {
        return <svg className="w-3.5 h-3.5 opacity-30 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>;
      }
      return sortDir === 'asc'
        ? <svg className="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7"/></svg>
        : <svg className="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7"/></svg>;
    }

    function AdminDashboard() {
      const data    = window.__ADMIN_DATA__ || {};
      const dbError = window.__DB_ERROR__   || '';
      const { metrics = {}, packages = [] } = data;

      const [search,   setSearch]   = useState('');
      const [sortField,setSortField]= useState('created_at');
      const [sortDir,  setSortDir]  = useState('desc');

      // Delete handler
      const handleDelete = (pkgId, pkgTitle) => {
        if (!window.confirm(`⚠️ Delete "${pkgTitle}"?\n\nThis will also remove all associated bookings. This action cannot be undone.`)) return;
        window.location.href = `delete-package.php?id=${pkgId}&confirm=yes`;
      };

      // Sort toggle
      const toggleSort = (field) => {
        if (sortField === field) {
          setSortDir(d => d === 'asc' ? 'desc' : 'asc');
        } else {
          setSortField(field);
          setSortDir('asc');
        }
      };

      // Filtered & sorted packages
      const displayed = useMemo(() => {
        const q = search.toLowerCase();
        const filtered = packages.filter(p =>
          p.title.toLowerCase().includes(q) ||
          p.state.toLowerCase().includes(q) ||
          p.zone.toLowerCase().includes(q)
        );
        return [...filtered].sort((a, b) => {
          let av = a[sortField], bv = b[sortField];
          if (!isNaN(Number(av))) { av = Number(av); bv = Number(bv); }
          else if (typeof av === 'string') { av = av.toLowerCase(); bv = bv.toLowerCase(); }
          if (av < bv) return sortDir === 'asc' ? -1 : 1;
          if (av > bv) return sortDir === 'asc' ?  1 : -1;
          return 0;
        });
      }, [packages, search, sortField, sortDir]);

      const confirmed = metrics.confirmed || {};
      const pkgMeta   = metrics.packages  || {};
      const userMeta  = metrics.users     || {};

      return (
        <div className="space-y-8">

          {/* Page header */}
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 className="font-display text-3xl font-bold text-slate-900">Admin Dashboard</h1>
              <p className="text-sm text-slate-500 mt-1">Platform overview and package management.</p>
            </div>
            <a
              href="manage-package.php"
              id="add-package-btn"
              className="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 px-5 rounded-xl transition-colors duration-200 text-sm"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4"/>
              </svg>
              Add New Package
            </a>
          </div>

          {/* DB Error */}
          {dbError && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2" role="alert">
              <svg className="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd"/>
              </svg>
              {dbError}
            </div>
          )}

          {/* Metrics row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
              icon="💰" label="Total Revenue" colorClass="bg-gradient-to-br from-orange-500 to-orange-700"
              value={fmtINR(confirmed.total_revenue || 0)}
              sub="Confirmed bookings only"
            />
            <MetricCard
              icon="🎫" label="Total Bookings" colorClass="bg-gradient-to-br from-emerald-500 to-emerald-700"
              value={Number(confirmed.total_bookings || 0).toLocaleString('en-IN')}
              sub="Confirmed bookings"
            />
            <MetricCard
              icon="📦" label="Packages" colorClass="bg-gradient-to-br from-brand-500 to-orange-700"
              value={Number(pkgMeta.total_packages || 0).toLocaleString('en-IN')}
              sub="Active travel packages"
            />
            <MetricCard
              icon="👥" label="Customers" colorClass="bg-gradient-to-br from-purple-500 to-purple-700"
              value={Number(userMeta.total_users || 0).toLocaleString('en-IN')}
              sub="Registered travellers"
            />
          </div>

          {/* Package management table */}
          <div className="bg-white rounded-2xl shadow-md overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <h2 className="text-base font-semibold text-slate-800">Package Management</h2>
              <div className="relative w-full sm:w-64">
                <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                  type="text"
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  placeholder="Search packages…"
                  id="pkg-search"
                  className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                />
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                  <tr>
                    <th className="px-4 py-3 text-left">#</th>
                    <th className="px-4 py-3 text-left cursor-pointer hover:text-brand-500 transition-colors" onClick={() => toggleSort('title')}>
                      <span className="inline-flex items-center">Title <SortIcon field="title" sortField={sortField} sortDir={sortDir}/></span>
                    </th>
                    <th className="px-4 py-3 text-left cursor-pointer hover:text-brand-500 transition-colors" onClick={() => toggleSort('zone')}>
                      <span className="inline-flex items-center">Zone <SortIcon field="zone" sortField={sortField} sortDir={sortDir}/></span>
                    </th>
                    <th className="px-4 py-3 text-left">State</th>
                    <th className="px-4 py-3 text-right cursor-pointer hover:text-brand-500 transition-colors" onClick={() => toggleSort('base_price')}>
                      <span className="inline-flex items-center justify-end">Price <SortIcon field="base_price" sortField={sortField} sortDir={sortDir}/></span>
                    </th>
                    <th className="px-4 py-3 text-center cursor-pointer hover:text-brand-500 transition-colors" onClick={() => toggleSort('availability')}>
                      <span className="inline-flex items-center justify-center">Seats <SortIcon field="availability" sortField={sortField} sortDir={sortDir}/></span>
                    </th>
                    <th className="px-4 py-3 text-center cursor-pointer hover:text-brand-500 transition-colors" onClick={() => toggleSort('total_bookings')}>
                      <span className="inline-flex items-center justify-center">Bookings <SortIcon field="total_bookings" sortField={sortField} sortDir={sortDir}/></span>
                    </th>
                    <th className="px-4 py-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {displayed.length === 0 && (
                    <tr>
                      <td colSpan="8" className="px-4 py-10 text-center text-slate-400 text-sm">
                        {search ? 'No packages match your search.' : 'No packages found.'}
                      </td>
                    </tr>
                  )}
                  {displayed.map((pkg, i) => (
                    <tr key={pkg.package_id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3.5 text-slate-400 text-xs">{i + 1}</td>
                      <td className="px-4 py-3.5">
                        <span className="font-medium text-slate-800 line-clamp-1">{pkg.title}</span>
                        <span className="block text-xs text-slate-400">{pkg.duration_days} days</span>
                      </td>
                      <td className="px-4 py-3.5">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-700">
                          {pkg.zone}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 text-slate-600">{pkg.state}</td>
                      <td className="px-4 py-3.5 text-right font-semibold text-brand-500">{fmtINR(pkg.base_price)}</td>
                      <td className="px-4 py-3.5 text-center">
                        <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                          parseInt(pkg.availability) === 0
                            ? 'bg-red-100 text-red-700'
                            : parseInt(pkg.availability) <= 5
                              ? 'bg-amber-100 text-amber-800'
                              : 'bg-emerald-100 text-emerald-800'
                        }`}>
                          {pkg.availability}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 text-center text-slate-700 font-medium">{pkg.total_bookings}</td>
                      <td className="px-4 py-3.5 text-center">
                        <div className="flex items-center justify-center gap-2">
                          <a
                            href={`manage-package.php?id=${pkg.package_id}`}
                            id={`edit-pkg-${pkg.package_id}`}
                             className="inline-flex items-center gap-1 text-xs font-medium text-orange-600 hover:text-orange-800 bg-orange-50 hover:bg-orange-100 px-2.5 py-1.5 rounded-lg transition-colors"
                          >
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                          </a>
                          <button
                            onClick={() => handleDelete(pkg.package_id, pkg.title)}
                            id={`del-pkg-${pkg.package_id}`}
                            className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg transition-colors"
                          >
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Table footer */}
            <div className="px-6 py-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-400 flex justify-between">
              <span>Showing {displayed.length} of {packages.length} packages</span>
              {search && <button onClick={() => setSearch('')} className="text-brand-500 hover:text-brand-600 font-medium">Clear search</button>}
            </div>
          </div>

        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('admin-root')).render(<AdminDashboard />);
  })();
  </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>