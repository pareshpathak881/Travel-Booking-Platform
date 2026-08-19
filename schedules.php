<?php
declare(strict_types=1);
/**
 * IndiaYatra — Multi-Modal Transit Schedule Tracker
 *
 * Live schedule search, PNR tracking, and booking for
 * Flights, Trains, and Buses across India.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

$db = getPDO();

$schedules = [];
$loadError = false;

try {
    $stmt = $db->prepare("
        SELECT schedule_id, transit_type, carrier_name, origin_city, destination_city,
               departure_time, arrival_time, duration_mins, running_days, pnr_tracker_code, fare_price
        FROM schedules
        WHERE transit_type IN ('flight', 'train', 'bus')
        ORDER BY departure_time ASC
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[IndiaYatra][schedules] ' . $e->getMessage());
    $loadError = true;
    $schedules = [];
}

$initialStateJson = json_encode([
    'schedules' => $schedules,
    'loadError' => $loadError,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

if ($initialStateJson === false) {
    $initialStateJson = json_encode(['schedules' => [], 'loadError' => true]);
}

$GLOBALS['pageTitle']       = 'Live Transit Schedules — IndiaYatra';
$GLOBALS['pageDescription'] = 'Track live flight, train, and bus schedules across India. Check PNR status, book tickets, and plan your journey.';
require_once __DIR__ . '/includes/header.php';
?>

<script>
  window.__SCHEDULES_INITIAL_STATE__ = <?= $initialStateJson ?>;
</script>

<main class="min-h-screen pb-16" style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">

  <!-- Page Header -->
  <section class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-orange-950 text-white overflow-hidden py-16 md:py-20 border-b border-orange-500/20">
    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #f97316 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <span class="inline-flex items-center gap-1.5 bg-orange-500/10 text-orange-400 border border-orange-500/30 text-xs font-semibold px-4 py-1.5 rounded-full mb-4">
        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
        Live Transit Hub
      </span>
      <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-bold leading-tight mb-4">
        Schedule <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300">Tracker</span>
      </h1>
      <p class="text-base sm:text-lg text-slate-300 leading-relaxed max-w-2xl mx-auto mb-6">
        Search live schedules across flights, trains, and buses. Track PNR status in real-time and book with instant confirmation.
      </p>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
    <div class="flex flex-col lg:flex-row gap-8">

      <!-- Main Content -->
      <div class="flex-1">
        <!-- Multi-Modal Tabs -->
        <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 flex justify-center md:justify-start gap-2 mb-6" role="tablist" aria-label="Transit type">
          <button role="tab" aria-selected="true" data-transit="flight" class="transit-tab active flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 bg-orange-500 text-white shadow-md">
            <i class="fa-solid fa-plane"></i> ✈️ Flights
          </button>
          <button role="tab" aria-selected="false" data-transit="train" class="transit-tab flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-train"></i> 🚆 Trains
          </button>
          <button role="tab" aria-selected="false" data-transit="bus" class="transit-tab flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-bus"></i> 🚌 Buses
          </button>
        </div>

        <!-- Search Form -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm mb-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label htmlFor="origin-input" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Origin City</label>
              <input id="origin-input" type="text" placeholder="e.g., Mumbai" class="w-full border border-slate-200 rounded-xl py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm" />
            </div>
            <div>
              <label htmlFor="destination-input" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Destination City</label>
              <input id="destination-input" type="text" placeholder="e.g., Delhi" class="w-full border border-slate-200 rounded-xl py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm" />
            </div>
            <div>
              <label htmlFor="date-input" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Travel Date</label>
              <input id="date-input" type="date" class="w-full border border-slate-200 rounded-xl py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm" />
            </div>
            <div class="flex items-end">
              <button id="search-schedules-btn" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm py-2.5 px-4 rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                <i className="fa-solid fa-magnifying-glass"></i> Search Schedules
              </button>
            </div>
          </div>
        </div>

        <!-- Route Timetable Grid -->
        <div id="schedule-results" class="space-y-4"></div>
      </div>

      <!-- Side Panel: PNR Tracker -->
      <div class="w-full lg:w-80 flex-shrink-0">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm sticky top-24">
          <div className="flex items-center gap-2 mb-4">
            <i className="fa-solid fa-satellite-dish text-orange-500"></i>
            <h3 className="font-display text-lg font-bold text-slate-900">Track Live Status</h3>
          </div>
          <p className="text-xs text-slate-500 mb-4">Enter a mock PNR or transit code to see live status simulation.</p>

          <div className="space-y-3">
            <input id="pnr-input" type="text" placeholder="e.g., IND6E204 or 1234567890" maxlength="10" class="w-full border border-slate-200 rounded-xl py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm font-mono" />
            <button id="track-status-btn" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm py-2.5 px-4 rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
              <i className="fa-solid fa-location-dot"></i> Track Status
            </button>
          </div>

          <div id="tracker-result" class="mt-4 hidden">
            <!-- Dynamic tracker content rendered here -->
          </div>
        </div>
      </div>

    </div>
  </div>

</main>

<script type="text/babel">
(function() {
  const { useState, useEffect, useMemo } = React;
  const session = window.__SESSION__ || {};
  const csrfToken = window.__CSRF_TOKEN__ || '';
  const initialState = window.__SCHEDULES_INITIAL_STATE__ || { schedules: [], loadError: false };

  const TRANSIT_ICONS = {
    flight: 'fa-plane',
    train: 'fa-train',
    bus: 'fa-bus'
  };

  const TRANSIT_COLORS = {
    flight: 'bg-sky-50 text-sky-700 border-sky-200',
    train: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    bus: 'bg-amber-50 text-amber-700 border-amber-200'
  };

  function getLiveStatus(departureTime, arrivalTime, transitType) {
    const now = new Date();
    const [depH, depM] = departureTime.split(':').map(Number);
    const [arrH, arrM] = arrivalTime.split(':').map(Number);
    const depDate = new Date(now); depDate.setHours(depH, depM, 0, 0);
    const arrDate = new Date(now); arrDate.setHours(arrH, arrM, 0, 0);

    if (now < depDate) {
      return { label: '⏳ Not Departed', color: 'slate', progress: 0 };
    } else if (now >= depDate && now <= arrDate) {
      const total = arrDate - depDate;
      const elapsed = now - depDate;
      const progress = Math.min(100, Math.max(0, Math.round((elapsed / total) * 100)));
      const delayChance = Math.random();
      if (delayChance > 0.85) {
        return { label: '🟡 Delayed 15m', color: 'yellow', progress };
      }
      return { label: '🟢 On Time', color: 'green', progress };
    } else {
      return { label: '✅ Arrived', color: 'emerald', progress: 100 };
    }
  }

  function fmtTime(timeStr) {
    if (!timeStr) return 'TBD';
    const [h, m] = timeStr.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hh = h % 12 || 12;
    const mm = String(m).padStart(2, '0');
    return `${hh}:${mm} ${ampm}`;
  }

  function fmtDuration(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${h}h ${m}m`;
  }

  function ScheduleCard({ schedule, onBook }) {
    const status = getLiveStatus(schedule.departure_time, schedule.arrival_time, schedule.transit_type);
    const colorMap = { green: 'text-emerald-700 bg-emerald-50 border-emerald-200', yellow: 'text-amber-700 bg-amber-50 border-amber-200', slate: 'text-slate-600 bg-slate-50 border-slate-200', emerald: 'text-emerald-800 bg-emerald-100 border-emerald-300' };
    const statusClass = colorMap[status.color] || colorMap.slate;

    return (
      <article className="bg-white rounded-3xl border border-slate-200/70 overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-orange-200 flex flex-col shadow-sm">
        <div className="p-5 flex-1 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <span className={`inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full border ${TRANSIT_COLORS[schedule.transit_type] || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                  <i className={`fa-solid ${TRANSIT_ICONS[schedule.transit_type] || 'fa-circle'}`}></i>
                  {schedule.transit_type.charAt(0).toUpperCase() + schedule.transit_type.slice(1)}
                </span>
                <span className="text-xs text-slate-500 font-semibold">{schedule.origin_city} → {schedule.destination_city}</span>
              </div>
              <span className={`text-xs font-bold px-2.5 py-1 rounded-full border ${statusClass}`}>{status.label}</span>
            </div>

            <h3 className="font-display text-base font-bold text-slate-800 mb-3">{schedule.carrier_name}</h3>

            {/* Timeline */}
            <div className="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-3">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs text-slate-400 font-semibold uppercase">Departs</p>
                  <p className="text-sm font-bold text-slate-800">{fmtTime(schedule.departure_time)}</p>
                  <p className="text-[10px] text-slate-500">{schedule.origin_city}</p>
                </div>
                <div className="flex-1 mx-4 border-t-2 border-dashed border-slate-300 relative">
                  <span className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-slate-50 text-[10px] font-bold text-orange-600 px-2 py-0.5 rounded-full border border-orange-200 whitespace-nowrap">
                    {fmtDuration(schedule.duration_mins)}
                  </span>
                </div>
                <div className="text-right">
                  <p className="text-xs text-slate-400 font-semibold uppercase">Arrives</p>
                  <p className="text-sm font-bold text-slate-800">{fmtTime(schedule.arrival_time)}</p>
                  <p className="text-[10px] text-slate-500">{schedule.destination_city}</p>
                </div>
              </div>
            </div>

            {/* Progress Bar */}
            <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden mb-3">
              <div className={`h-full rounded-full transition-all duration-500 ${status.progress === 100 ? 'bg-emerald-500' : 'bg-orange-500'}`} style={{ width: `${status.progress}%` }}></div>
            </div>
          </div>

          <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-2">
            <div>
              <span className="text-[10px] text-slate-400 block font-semibold uppercase">Fare</span>
              <span className="text-base font-extrabold text-orange-500">₹{Number(schedule.fare_price).toLocaleString('en-IN')}</span>
            </div>
            <button onClick={() => onBook(schedule)} className="bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs py-2 px-4 rounded-xl shadow-sm transition-colors flex items-center gap-1.5">
              Book Now <i className="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </article>
    );
  }

  function BookingModal({ schedule, onClose }) {
    const [seats, setSeats] = useState(1);
    const [loading, setLoading] = useState(false);
    const [eTicket, setETicket] = useState(null);
    const maxSeats = schedule.transit_type === 'bus' ? 10 : (schedule.transit_type === 'train' ? 6 : 9);
    const basePrice = parseFloat(schedule.fare_price) || 0;

    const calc = useMemo(() => {
      const rawSubtotal = basePrice * seats;
      const level = session.user_level || 1;
      let discountPercent = 0;
      if (level === 2) discountPercent = 0.05;
      else if (level === 3) discountPercent = 0.10;
      const discountAmt = rawSubtotal * discountPercent;
      const baseTotal = rawSubtotal - discountAmt;
      const cgst = baseTotal * 0.025;
      const sgst = baseTotal * 0.025;
      const totalGst = cgst + sgst;
      const finalPayable = baseTotal + totalGst;
      return { baseTotal, discountAmt, cgst, sgst, totalGst, finalPayable };
    }, [seats, basePrice]);

    const handleSubmit = async (e) => {
      e.preventDefault();
      if (!session.loggedIn) {
        alert('Please log in first to book.');
        window.location.href = 'login.php';
        return;
      }

      setLoading(true);
      setTimeout(async () => {
        try {
          const formData = new FormData();
          formData.append('package_id', '1');
          formData.append('seats_booked', String(seats));
          formData.append('csrf_token', csrfToken);
          formData.append('selected_seats', '');
          formData.append('room_tier', '');

          const res = await fetch('booking-action.php', { method: 'POST', body: formData });
          const data = await res.json();
          if (data.status === 'success') {
            setETicket({
              invoiceId: data.invoice_id,
              itemTitle: `${schedule.carrier_name} (${schedule.origin_city} → ${schedule.destination_city})`,
              quantity: seats,
              baseTotal: calc.baseTotal,
              discount: calc.discountAmt,
              gst: calc.totalGst,
              finalPayable: data.final_price,
              pointsEarned: data.new_points,
              badgesUnlocked: data.new_badges,
            });
            window.__SESSION__.badge_flags = data.new_badges;
            window.__SESSION__.loyalty_points = data.new_points;
            window.__SESSION__.user_level = data.new_level;
          } else {
            alert(data.message || 'Booking failed.');
          }
        } catch (err) {
          console.error(err);
          alert('Network error during booking.');
        } finally {
          setLoading(false);
        }
      }, 1500);
    };

    if (!schedule) return null;

    return (
      <div className="fixed inset-0 z-[1001] flex items-center justify-center p-4">
        <div className="absolute inset-0 bg-black/40 backdrop-blur-md" onClick={onClose}></div>
        <div className="relative bg-white rounded-3xl shadow-2xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
          <button onClick={onClose} className="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-800 transition-colors" type="button">×</button>

          {eTicket ? (
            <div className="animate-fadeIn">
              <div className="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
                <div>
                  <span className="text-[10px] font-black uppercase tracking-widest text-orange-400">Boarding Pass</span>
                  <h3 className="font-display font-bold text-lg text-slate-900">IndiaYatra</h3>
                </div>
                <span className="bg-emerald-100 text-emerald-700 font-extrabold text-[10px] uppercase border border-emerald-200 px-2 py-0.5 rounded">Confirmed</span>
              </div>

              <div className="bg-slate-900 text-white p-4 rounded-xl mb-4">
                <div className="flex justify-between text-xs mb-2">
                  <div>
                    <p className="text-slate-400 text-[9px] uppercase">Transit</p>
                    <p className="font-bold">{schedule.carrier_name}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-slate-400 text-[9px] uppercase">Passengers</p>
                    <p className="font-bold">{eTicket.quantity}</p>
                  </div>
                </div>
                <div className="border-t border-slate-700 pt-2">
                  <p className="text-[9px] text-slate-400">Booking Reference</p>
                  <p className="text-sm font-bold text-orange-400">IY-TXN-{eTicket.invoiceId}</p>
                </div>
              </div>

              <div className="space-y-2 text-xs mb-4">
                <div className="flex justify-between text-slate-600">
                  <span>Base Amount</span>
                  <span className="font-semibold">₹{Number(eTicket.baseTotal).toLocaleString('en-IN')}</span>
                </div>
                {Number(eTicket.discount) > 0 && (
                  <div className="flex justify-between text-emerald-600 font-bold">
                    <span>Loyalty Discount</span>
                    <span>- ₹{Number(eTicket.discount).toLocaleString('en-IN')}</span>
                  </div>
                )}
                <div className="flex justify-between text-slate-500">
                  <span>GST (5%)</span>
                  <span>₹{Number(eTicket.gst).toLocaleString('en-IN')}</span>
                </div>
                <div className="border-t border-slate-200 pt-2 flex justify-between font-bold text-sm text-slate-800">
                  <span>Total Paid</span>
                  <span className="text-orange-500">₹{Number(eTicket.finalPayable).toLocaleString('en-IN')}</span>
                </div>
              </div>

              <div className="text-center">
                <p className="text-[10px] text-slate-500 mb-3">+{eTicket.pointsEarned} loyalty points earned!</p>
                <button onClick={window.print} className="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2">
                  <i className="fa-solid fa-print"></i> Print / Save PDF
                </button>
              </div>
            </div>
          ) : (
            <div>
              <h3 className="font-display text-xl font-bold text-slate-900 mb-1">Book Transit</h3>
              <p className="text-sm text-slate-500 mb-4">{schedule.carrier_name}</p>
              <p className="text-xs text-slate-500 mb-4">{schedule.origin_city} → {schedule.destination_city} • {schedule.departure_time} - {schedule.arrival_time}</p>

              <div className="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4 text-xs space-y-2">
                <div className="flex justify-between text-slate-600">
                  <span>Base Fare ({seats} x ₹{Number(basePrice).toLocaleString('en-IN')})</span>
                  <span className="font-semibold">₹{Number(calc.baseTotal).toLocaleString('en-IN')}</span>
                </div>
                {calc.discountAmt > 0 && (
                  <div className="flex justify-between text-emerald-600 font-bold">
                    <span>Loyalty Discount</span>
                    <span>- ₹{Number(calc.discountAmt).toLocaleString('en-IN')}</span>
                  </div>
                )}
                <div className="flex justify-between text-slate-500">
                  <span>CGST + SGST (5%)</span>
                  <span>₹{Number(calc.totalGst).toLocaleString('en-IN')}</span>
                </div>
                <div className="border-t border-slate-200 pt-2 flex justify-between font-bold text-sm text-slate-800">
                  <span>Total Payable</span>
                  <span className="text-orange-500">₹{Number(calc.finalPayable).toLocaleString('en-IN')}</span>
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-xs font-bold text-slate-400 uppercase mb-2">Passengers</label>
                <input type="number" min="1" max={maxSeats} value={seats} onChange={(e) => setSeats(Math.max(1, Math.min(maxSeats, parseInt(e.target.value) || 1)))} className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm" />
                <p className="text-[10px] text-slate-400 mt-1">Max {maxSeats} passengers</p>
              </div>

              <button onClick={handleSubmit} disabled={loading} className={`w-full font-bold py-3 rounded-xl transition-all text-sm flex items-center justify-center gap-2 ${loading ? 'bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200' : 'bg-orange-500 hover:bg-orange-600 text-white shadow-md'}`}>
                {loading ? (
                  <>
                    <div className="w-4 h-4 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></div>
                    Processing...
                  </>
                ) : (
                  <>Proceed to Pay — ₹{Number(calc.finalPayable).toLocaleString('en-IN')}</>
                )}
              </button>
            </div>
          )}
        </div>
      </div>
    );
  }

  function SchedulesPage() {
    const [schedules, setSchedules] = useState(() => initialState.schedules || []);
    const [loadError, setLoadError] = useState(initialState.loadError ? 'Failed to load schedules.' : null);
    const [activeTransit, setActiveTransit] = useState('flight');
    const [selectedSchedule, setSelectedSchedule] = useState(null);
    const [toast, setToast] = useState(null);

    const filteredSchedules = useMemo(() => {
      return schedules.filter(s => s.transit_type === activeTransit);
    }, [schedules, activeTransit]);

    const showToast = (message, tone = 'info') => {
      setToast({ message, tone });
      setTimeout(() => setToast(null), 3500);
    };

    const handleBook = (schedule) => {
      if (!session.loggedIn) {
        showToast('Please log in to book.', 'info');
        window.location.href = 'login.php';
        return;
      }
      setSelectedSchedule(schedule);
    };

    // Tab switching
    useEffect(() => {
      const tabs = document.querySelectorAll('.transit-tab');
      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          tabs.forEach(t => {
            t.classList.remove('bg-orange-500', 'text-white', 'shadow-md', 'active');
            t.classList.add('text-slate-600', 'hover:bg-slate-100');
            t.setAttribute('aria-selected', 'false');
          });
          tab.classList.remove('text-slate-600', 'hover:bg-slate-100');
          tab.classList.add('bg-orange-500', 'text-white', 'shadow-md', 'active');
          tab.setAttribute('aria-selected', 'true');
          setActiveTransit(tab.dataset.transit);
        });
      });
    }, []);

    // Search handler
    useEffect(() => {
      const searchBtn = document.getElementById('search-schedules-btn');
      const originInput = document.getElementById('origin-input');
      const destInput = document.getElementById('destination-input');

      const handleSearch = () => {
        const origin = originInput?.value?.toLowerCase() || '';
        const dest = destInput?.value?.toLowerCase() || '';

        if (!origin && !dest) {
          showToast('Please enter origin or destination city.', 'info');
          return;
        }

        const filtered = initialState.schedules.filter(s => {
          if (s.transit_type !== activeTransit) return false;
          if (origin && !s.origin_city.toLowerCase().includes(origin)) return false;
          if (dest && !s.destination_city.toLowerCase().includes(dest)) return false;
          return true;
        });

        setSchedules(filtered);
        if (filtered.length === 0) {
          showToast('No schedules found matching your criteria.', 'info');
        }
      };

      searchBtn?.addEventListener('click', handleSearch);
      return () => searchBtn?.removeEventListener('click', handleSearch);
    }, [activeTransit, initialState.schedules]);

    // PNR Tracker
    useEffect(() => {
      const trackBtn = document.getElementById('track-status-btn');
      const pnrInput = document.getElementById('pnr-input');
      const resultDiv = document.getElementById('tracker-result');

      const handleTrack = async () => {
        const code = pnrInput?.value?.trim();
        if (!code) {
          showToast('Please enter a PNR or transit code.', 'info');
          return;
        }

        resultDiv.classList.remove('hidden');
        resultDiv.innerHTML = '<div className="flex items-center justify-center py-4"><div className="w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div></div>';

        try {
          const res = await fetch(`api/track-status.php?code=${encodeURIComponent(code)}`);
          const data = await res.json();

          if (data.status === 'success') {
            const statusColors = { green: 'text-emerald-700 bg-emerald-50 border-emerald-200', yellow: 'text-amber-700 bg-amber-50 border-amber-200', blue: 'text-sky-700 bg-sky-50 border-sky-200', slate: 'text-slate-600 bg-slate-50 border-slate-200', emerald: 'text-emerald-800 bg-emerald-100 border-emerald-300' };
            const sc = statusColors[data.live_status.status_color] || statusColors.slate;

            resultDiv.innerHTML = `
              <div className="animate-fadeIn space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Live Status</span>
                  <span className="text-[10px] font-mono text-slate-400">${data.live_status.last_updated}</span>
                </div>
                <div className="bg-white rounded-xl p-4 border border-slate-200">
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-sm font-bold text-slate-800">${data.schedule.carrier_name}</span>
                    <span className="text-xs font-bold px-2 py-1 rounded-full border ${sc}">${data.live_status.current_status}</span>
                  </div>
                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between text-slate-600">
                      <span>Current Location</span>
                      <span className="font-semibold">${data.live_status.current_location}</span>
                    </div>
                    <div className="flex justify-between text-slate-600">
                      <span>Gate / Platform</span>
                      <span className="font-semibold">${data.live_status.gate_info}</span>
                    </div>
                    <div className="flex justify-between text-slate-600">
                      <span>Baggage Info</span>
                      <span className="font-semibold text-right">${data.live_status.baggage_info}</span>
                    </div>
                  </div>
                  <div className="mt-3 w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div className="h-full bg-orange-500 rounded-full transition-all duration-500" style={{ width: `${data.live_status.progress_percent}%` }}></div>
                  </div>
                </div>
              </div>
            `;
          } else {
            resultDiv.innerHTML = `<div className="text-xs text-red-600 font-semibold bg-red-50 p-3 rounded-xl border border-red-200">${data.message || 'Tracker code not found.'}</div>`;
          }
        } catch (err) {
          resultDiv.innerHTML = `<div className="text-xs text-red-600 font-semibold bg-red-50 p-3 rounded-xl border border-red-200">Network error while tracking.</div>`;
        }
      };

      trackBtn?.addEventListener('click', handleTrack);
      return () => trackBtn?.removeEventListener('click', handleTrack);
    }, []);

    return (
      <div className="flex flex-col gap-6">
        {toast && (
          <div className={`fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2.5 rounded-xl shadow-lg z-50 text-sm font-semibold text-white ${toast.tone === 'error' ? 'bg-red-600' : 'bg-slate-800'}`}>
            {toast.message}
          </div>
        )}

        {loadError && (
          <div className="bg-red-50 border border-red-200 text-red-700 text-sm font-semibold rounded-xl p-4 flex items-center justify-between gap-4">
            <span>{loadError}</span>
            <button onClick={() => window.location.reload()} className="underline font-bold whitespace-nowrap">Retry</button>
          </div>
        )}

        {filteredSchedules.length > 0 ? (
          <div className="space-y-4">
            {filteredSchedules.map(s => (
              <ScheduleCard key={s.schedule_id} schedule={s} onBook={handleBook} />
            ))}
          </div>
        ) : (
          <div className="bg-white border border-slate-200 rounded-2xl p-12 text-center shadow-sm">
            <div className="text-4xl mb-3">🚆</div>
            <h3 className="font-bold text-slate-700 mb-1">No Schedules Found</h3>
            <p className="text-sm text-slate-400">Try adjusting your search or switch transit type.</p>
          </div>
        )}

        {selectedSchedule && (
          <BookingModal schedule={selectedSchedule} onClose={() => setSelectedSchedule(null)} />
        )}
      </div>
    );
  }

  const rootEl = document.getElementById('schedule-results');
  if (rootEl) {
    ReactDOM.createRoot(rootEl).render(<SchedulesPage />);
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
