<?php
declare(strict_types=1);

/**
 * IndiaYatra — Package Detail, Seating Map, Modifiers & E-Ticket Payment Wizard
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';

// Validate ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    $GLOBALS['pageTitle'] = '404 Not Found — IndiaYatra';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="max-w-2xl mx-auto px-4 py-24 text-center">
      <div class="text-6xl mb-4">🗺️</div>
      <h1 class="font-display text-3xl font-bold text-slate-900 mb-3">Item Not Found</h1>
      <p class="text-slate-500 mb-6">The specified inventory item ID is invalid.</p>
      <a href="index.php" class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-8 rounded-xl transition-colors">
        Browse Marketplace
      </a>
    </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch package details
$package = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE package_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $package = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[IndiaYatra][package-detail] ' . $e->getMessage());
}

if (!$package) {
    http_response_code(404);
    $GLOBALS['pageTitle'] = '404 Item Not Found — IndiaYatra';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="max-w-2xl mx-auto px-4 py-24 text-center">
      <div class="text-6xl mb-4">😔</div>
      <h1 class="font-display text-3xl font-bold text-slate-900 mb-3">Listing Not Found</h1>
      <p class="text-slate-500 mb-6">This item is no longer available in our aggregator database.</p>
      <a href="index.php" class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2.5 px-8 rounded-xl transition-colors">
        Browse Other Options
      </a>
    </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$GLOBALS['pageTitle'] = htmlspecialchars($package['title'], ENT_QUOTES, 'UTF-8') . ' — IndiaYatra';
require_once __DIR__ . '/includes/header.php';
?>

<style>
  /* Print Isolation Layout CSS style */
  @media print {
    body * {
      visibility: hidden !important;
    }
    #print-ticket-wrapper, #print-ticket-wrapper * {
      visibility: visible !important;
    }
    #print-ticket-wrapper {
      position: absolute !important;
      left: 0 !important;
      top: 0 !important;
      width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      box-shadow: none !important;
      border: 2px dashed #000 !important;
      background: #fff !important;
      color: #000 !important;
    }
  }
</style>

<!-- Breadcrumbs -->
<nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4" aria-label="Breadcrumb">
  <ol class="flex items-center gap-2 text-sm text-slate-500">
    <li><a href="index.php" class="hover:text-orange-500 transition-colors">Home</a></li>
    <li class="text-slate-300">/</li>
    <li><a href="index.php" class="hover:text-orange-500 transition-colors">Marketplace</a></li>
    <li class="text-slate-300">/</li>
    <li class="text-slate-800 font-bold truncate max-w-xs"><?= htmlspecialchars($package['title'], ENT_QUOTES, 'UTF-8') ?></li>
  </ol>
</nav>

<!-- Hydrate package payload -->
<script>
  window.__PACKAGE_DETAIL__ = <?= json_encode($package, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20" style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">
  <div id="booking-detail-root"></div>
</main>

<script type="text/babel">
(function() {
  const { useState, useEffect } = React;

  const pkg = window.__PACKAGE_DETAIL__;
  const session = window.__SESSION__ || {};
  const csrfToken = window.__CSRF_TOKEN__ || '';

  // Seed standard flight seats status mapping (e.g. Row 1 to Row 8)
  const SEAT_ROWS = [1, 2, 3, 4, 5, 6, 7, 8];
  const SEAT_LETTER = ['A', 'B', 'C', 'D', 'E', 'F'];
  const MOCK_OCCUPIED_SEATS = ['1B', '2D', '3A', '3F', '5C', '6E', '7B', '8A'];

  // Unsplash carousels
  const MOCK_CAROUSEL_IMAGES = [
    pkg.image_url || 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=800&q=80',
    'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=800&q=80'
  ];

  function DetailContainer() {
    const [currency, setCurrency] = useState(window.__CURRENCY__);
    const [activeImageIdx, setActiveImageIdx] = useState(0);

    // Dynamic Seating configuration
    const [selectedSeats, setSelectedSeats] = useState([]);
    
    // Hotel tier configurations
    const [selectedRoomTier, setSelectedRoomTier] = useState('Standard Room'); // Deluxe Suite, Executive Suite

    // Split cohorts
    const [splitEnabled, setSplitEnabled] = useState(false);
    const [splitCount, setSplitCount] = useState(2);

    // Form inputs & loading
    const [seatsCount, setSeatsCount] = useState(1);
    const [loading, setLoading] = useState(false);
    
    // Payment card inputs
    const [cardName, setCardName] = useState('');
    const [cardNumber, setCardNumber] = useState('');
    const [cardExpiry, setCardExpiry] = useState('');
    const [cardCvv, setCardCvv] = useState('');

    // E-Ticket response state
    const [eTicket, setETicket] = useState(null);

    useEffect(() => {
      const handleCurrencyChange = (e) => setCurrency(e.detail);
      window.addEventListener('currencyChange', handleCurrencyChange);
      return () => window.removeEventListener('currencyChange', handleCurrencyChange);
    }, []);

    // Calculate dynamic base pricing and modifier weights
    const getBasePricingDetails = () => {
      const baseVal = parseFloat(pkg.base_price);
      let modifier = 0;
      let tierLabel = '';

      if (pkg.vertical_type === 'hotel') {
        if (selectedRoomTier === 'Deluxe Suite') {
          modifier = 1500;
          tierLabel = 'Deluxe Suite (+₹1,500)';
        } else if (selectedRoomTier === 'Executive Suite') {
          modifier = 4000;
          tierLabel = 'Executive Suite (+₹4,000)';
        } else {
          tierLabel = 'Standard Room (Base Rate)';
        }
      }

      const activePerSeat = baseVal + modifier;
      
      // Enforce flight seat quantity based on selected seats count
      const activeSeatsNum = pkg.vertical_type === 'flight' ? Math.max(1, selectedSeats.length) : seatsCount;
      const baseTotal = activePerSeat * activeSeatsNum;

      // Loyalty Level calculations
      const level = session.user_level || 1;
      let discountPercent = 0;
      let perkText = 'Standard Base Fee';
      if (level === 2) {
        discountPercent = 0.05;
        perkText = 'Level 2 Explorer (5% Off)';
      } else if (level === 3) {
        discountPercent = 0.10;
        perkText = 'Level 3 Apex Voyager (10% Off)';
      }

      const discountAmt = baseTotal * discountPercent;
      const subtotalWithPerk = baseTotal - discountAmt;

      // GST Split: CGST (2.5%) + SGST (2.5%) = 5% Total GST
      const cgst = subtotalWithPerk * 0.025;
      const sgst = subtotalWithPerk * 0.025;
      const totalGst = cgst + sgst;
      const finalPayable = subtotalWithPerk + totalGst;

      return {
        activePerSeat,
        activeSeatsNum,
        baseTotal,
        discountAmt,
        perkText,
        cgst,
        sgst,
        totalGst,
        finalPayable
      };
    };

    const calc = getBasePricingDetails();

    // Toggle seat clicks
    const handleSeatToggle = (seatId) => {
      if (MOCK_OCCUPIED_SEATS.includes(seatId)) return;
      setSelectedSeats(prev => {
        const updated = prev.includes(seatId) ? prev.filter(s => s !== seatId) : [...prev, seatId];
        // Keep seats count in sync with visual map selections
        setSeatsCount(updated.length || 1);
        return updated;
      });
    };

    // Split slide controls
    const handleSplitCountChange = (e) => {
      const val = Number(e.target.value);
      setSplitCount(Math.min(calc.activeSeatsNum, val));
    };

    // Checkout async dispatch
    const handlePaymentSubmit = async (e) => {
      e.preventDefault();
      if (!session.loggedIn) {
        alert("Please log in first to purchase tickets.");
        window.location.href = "login.php";
        return;
      }

      if (pkg.vertical_type === 'flight' && selectedSeats.length === 0) {
        alert("Please select at least one seat from the flight map.");
        return;
      }

      setLoading(true);
      
      // Simulate bank transaction delay spinner
      setTimeout(async () => {
        try {
          const formData = new FormData();
          formData.append('package_id', pkg.package_id.toString());
          formData.append('seats_booked', calc.activeSeatsNum.toString());
          formData.append('csrf_token', csrfToken);
          formData.append('selected_seats', selectedSeats.join(','));
          formData.append('room_tier', selectedRoomTier);

          const res = await fetch('booking-action.php', {
            method: 'POST',
            body: formData
          });

          const data = await res.json();
          if (data.status === 'success') {
            setETicket({
              invoiceId: data.invoice_id,
              date: new Date().toLocaleDateString('en-IN'),
              itemTitle: pkg.title,
              vertical: pkg.vertical_type,
              quantity: calc.activeSeatsNum,
              baseTotal: calc.baseTotal,
              discount: calc.discountAmt,
              gst: calc.totalGst,
              finalPayable: data.final_price,
              seatsList: selectedSeats.join(', '),
              tier: selectedRoomTier,
              pointsEarned: data.new_points,
              badgesUnlocked: data.new_badges
            });
            // Update local session representation
            window.__SESSION__.badge_flags = data.new_badges;
            window.__SESSION__.loyalty_points = data.new_points;
            window.__SESSION__.user_level = data.new_level;
          } else {
            alert(data.message || "Aggregator payment confirmation failed.");
          }
        } catch (err) {
          console.error("Aggregation pipeline failure", err);
          alert("A network timeout occurred during bank handshake.");
        } finally {
          setLoading(false);
        }
      }, 2000);
    };

    const handlePrintInvoice = () => {
      window.print();
    };

    return (
      <div className="flex flex-col lg:flex-row gap-8 items-start mt-4">
        
        {/* ── LEFT SCREEN: description, map, details ────────────────── */}
        <div className="w-full lg:w-2/3 flex flex-col gap-6">
          
          {/* Unsplash Image Slider Carousel */}
          <div className="relative rounded-3xl overflow-hidden h-96 shadow-lg border border-slate-200">
            <img 
              src={MOCK_CAROUSEL_IMAGES[activeImageIdx]} 
              alt={pkg.title} 
              className="w-full h-full object-cover"
            />
            <div className="absolute top-4 left-4 flex gap-2">
              <span className="bg-slate-900/80 backdrop-blur-sm text-white text-xs font-bold px-3 py-1 rounded-full border border-slate-700">
                {pkg.zone} India
              </span>
              <span className="bg-orange-500 text-white text-xs font-black px-3.5 py-1 rounded-full shadow">
                Grade {pkg.letter_grade}
              </span>
            </div>
            
            {/* Image Dots */}
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5 bg-black/40 backdrop-blur-md px-3 py-1.5 rounded-full">
              {MOCK_CAROUSEL_IMAGES.map((_, idx) => (
                <button 
                  key={idx}
                  onClick={() => setActiveImageIdx(idx)}
                  className={`w-2.5 h-2.5 rounded-full ${activeImageIdx === idx ? 'bg-orange-500' : 'bg-white/50'}`}
                ></button>
              ))}
            </div>
          </div>

          {/* Heading */}
          <div>
            <span className="text-xs font-extrabold text-orange-500 uppercase tracking-widest bg-orange-50 px-2.5 py-1 rounded">
              {pkg.vertical_type} Inventory Card
            </span>
            <h1 className="font-display text-3xl font-extrabold text-slate-900 mt-2 leading-tight">
              {pkg.title}
            </h3>
            <p className="text-slate-500 text-sm mt-1.5 flex items-center gap-1.5">
              <i className="fa-solid fa-plane-departure text-orange-500"></i> Region: <strong>{pkg.state}</strong> &bull; Duration: <strong>{pkg.duration_days} Days</strong>
            </p>
          </div>

          {/* CONDITIONAL LAYOUT SHELLS */}
          
          {/* 1. FLIGHT LAYOUT */}
          {pkg.vertical_type === 'flight' && (
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <h2 className="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i className="fa-solid fa-couch text-orange-500"></i> Interactive Cabin Seating Map
              </h2>
              <div className="bg-slate-50 rounded-2xl border border-slate-100 p-6 max-w-md mx-auto">
                <div className="w-full bg-slate-200 h-10 rounded-t-3xl flex items-center justify-center text-[10px] font-bold text-slate-500 uppercase mb-6 tracking-widest">
                  Cockpit / Front of Cabin
                </div>
                
                <div className="space-y-3">
                  {SEAT_ROWS.map(rowNum => (
                    <div key={rowNum} className="flex justify-between items-center gap-2">
                      {/* Left Seats A, B, C */}
                      <div className="flex gap-2 flex-1 justify-end">
                        {['A', 'B', 'C'].map(char => {
                          const seatId = `${rowNum}${char}`;
                          const isOccupied = MOCK_OCCUPIED_SEATS.includes(seatId);
                          const isSelected = selectedSeats.includes(seatId);
                          return (
                            <button
                              key={seatId}
                              disabled={isOccupied}
                              onClick={() => handleSeatToggle(seatId)}
                              className={`w-8 h-8 rounded-lg text-[10px] font-bold flex items-center justify-center transition-colors border
                                ${isOccupied ? 'bg-slate-200 border-slate-300 text-slate-400 cursor-not-allowed' : 
                                  isSelected ? 'bg-orange-500 border-orange-500 text-white shadow-sm' : 
                                  'bg-white border-slate-200 text-slate-700 hover:bg-orange-50 hover:border-orange-200 hover:text-orange-600'}`}
                              title={isOccupied ? "Booked" : `Select Seat ${seatId}`}
                            >
                              {char}
                            </button>
                          );
                        })}
                      </div>

                      {/* Aisle */}
                      <span className="w-8 text-center text-[10px] font-extrabold text-slate-400 select-none">
                        Row {rowNum}
                      </span>

                      {/* Right Seats D, E, F */}
                      <div className="flex gap-2 flex-1 justify-start">
                        {['D', 'E', 'F'].map(char => {
                          const seatId = `${rowNum}${char}`;
                          const isOccupied = MOCK_OCCUPIED_SEATS.includes(seatId);
                          const isSelected = selectedSeats.includes(seatId);
                          return (
                            <button
                              key={seatId}
                              disabled={isOccupied}
                              onClick={() => handleSeatToggle(seatId)}
                              className={`w-8 h-8 rounded-lg text-[10px] font-bold flex items-center justify-center transition-colors border
                                ${isOccupied ? 'bg-slate-200 border-slate-300 text-slate-400 cursor-not-allowed' : 
                                  isSelected ? 'bg-orange-500 border-orange-500 text-white shadow-sm' : 
                                  'bg-white border-slate-200 text-slate-700 hover:bg-orange-50 hover:border-orange-200 hover:text-orange-600'}`}
                              title={isOccupied ? "Booked" : `Select Seat ${seatId}`}
                            >
                              {char}
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>

                <div className="flex items-center justify-center gap-6 mt-6 pt-4 border-t border-slate-200 text-[10px] font-bold text-slate-500">
                  <span className="flex items-center gap-1.5"><span className="w-3.5 h-3.5 rounded bg-white border border-slate-200 inline-block"></span> Available</span>
                  <span className="flex items-center gap-1.5"><span className="w-3.5 h-3.5 rounded bg-orange-500 inline-block"></span> Selected</span>
                  <span className="flex items-center gap-1.5"><span className="w-3.5 h-3.5 rounded bg-slate-200 border border-slate-300 inline-block"></span> Occupied</span>
                </div>
              </div>
            </div>
          )}

          {/* 2. HOTEL LAYOUT */}
          {pkg.vertical_type === 'hotel' && (
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <h2 className="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i className="fa-solid fa-bed text-orange-500"></i> Choose Room Tier
              </h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                {/* Tier 1 */}
                <div 
                  onClick={() => setSelectedRoomTier('Standard Room')}
                  className={`p-4 rounded-xl border cursor-pointer transition-all ${selectedRoomTier === 'Standard Room' ? 'border-orange-500 bg-orange-50/40 ring-1 ring-orange-500' : 'border-slate-200 hover:border-slate-300'}`}
                >
                  <span className="block font-bold text-sm text-slate-800">Standard Room</span>
                  <span className="block text-xs text-slate-400 mt-0.5">Included in base rate</span>
                  <div className="flex flex-wrap gap-1 mt-3">
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">WiFi</span>
                  </div>
                </div>

                {/* Tier 2 */}
                <div 
                  onClick={() => setSelectedRoomTier('Deluxe Suite')}
                  className={`p-4 rounded-xl border cursor-pointer transition-all ${selectedRoomTier === 'Deluxe Suite' ? 'border-orange-500 bg-orange-50/40 ring-1 ring-orange-500' : 'border-slate-200 hover:border-slate-300'}`}
                >
                  <span className="block font-bold text-sm text-slate-800">Deluxe Suite</span>
                  <span className="block text-xs text-orange-600 font-extrabold mt-0.5">+ {window.formatPrice(1500, currency)}</span>
                  <div className="flex flex-wrap gap-1 mt-3">
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">WiFi</span>
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">Breakfast</span>
                  </div>
                </div>

                {/* Tier 3 */}
                <div 
                  onClick={() => setSelectedRoomTier('Executive Suite')}
                  className={`p-4 rounded-xl border cursor-pointer transition-all ${selectedRoomTier === 'Executive Suite' ? 'border-orange-500 bg-orange-50/40 ring-1 ring-orange-500' : 'border-slate-200 hover:border-slate-300'}`}
                >
                  <span className="block font-bold text-sm text-slate-800">Executive Suite</span>
                  <span className="block text-xs text-orange-600 font-extrabold mt-0.5">+ {window.formatPrice(4000, currency)}</span>
                  <div className="flex flex-wrap gap-1 mt-3">
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">WiFi</span>
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">Breakfast</span>
                    <span className="bg-slate-100 text-slate-600 text-[9px] font-bold px-1.5 py-0.5 rounded">Infinity Pool</span>
                  </div>
                </div>

              </div>
            </div>
          )}

          {/* 3. PACKAGE LAYOUT */}
          {pkg.vertical_type === 'package' && (
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <h2 className="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i className="fa-solid fa-map-location-dot text-orange-500"></i> Tour Itinerary & Guides
              </h2>
              <div className="space-y-4">
                <div className="border-l-2 border-orange-500 pl-4 py-1">
                  <span className="block text-xs font-bold text-orange-600">Day 1: Arrival & Briefing</span>
                  <p className="text-xs text-slate-500 leading-normal mt-0.5">Welcome reception at local hub, check-in to homestays or premium hotels, followed by tour guides introductory briefing.</p>
                </div>
                <div className="border-l-2 border-orange-500 pl-4 py-1">
                  <span className="block text-xs font-bold text-orange-600">Day 2: Cultural Exploration</span>
                  <p className="text-xs text-slate-500 leading-normal mt-0.5">Detailed guide walks through heritage architecture, museums, and temples of regional importance.</p>
                </div>
                <div className="border-l-2 border-orange-500 pl-4 py-1">
                  <span className="block text-xs font-bold text-orange-600">Day 3: Nature Trekking & Departure</span>
                  <p className="text-xs text-slate-500 leading-normal mt-0.5">Early morning hikes to iconic regional scenic spots (like waterfalls, bridges, or palaces), checkout and departure assistance.</p>
                </div>
              </div>
            </div>
          )}

          {/* About description */}
          <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h2 className="text-base font-bold text-slate-800 mb-2">Detailed Information</h2>
            <p className="text-sm text-slate-600 leading-relaxed">{pkg.description}</p>
          </div>

          {/* Highlights */}
          {pkg.highlights && (
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <h2 className="text-base font-bold text-slate-800 mb-4 flex items-center gap-1.5">
                <i className="fa-regular fa-star text-orange-500"></i> Key Highlights
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {pkg.highlights.split('|').map((h, idx) => (
                  <div key={idx} className="flex items-center gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs font-medium text-slate-700">
                    <i className="fa-solid fa-check text-emerald-500"></i> {h}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Map Embed panel */}
          {pkg.map_coordinates && (
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm overflow-hidden">
              <h2 className="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i className="fa-solid fa-map-pin text-orange-500"></i> Verified Interactive Location Map
              </h2>
              <div className="h-64 w-full rounded-xl overflow-hidden border border-slate-150">
                <iframe 
                  src={pkg.map_coordinates}
                  width="100%" 
                  height="100%" 
                  style={{ border: 0 }} 
                  allowFullScreen="" 
                  loading="lazy" 
                  referrerPolicy="no-referrer-when-downgrade"
                  title="Interactive Map Location"
                ></iframe>
              </div>
            </div>
          )}

        </div>

        {/* ── RIGHT SCREEN: E-Ticket or Checkout Wizard ───────────── */}
        <div className="w-full lg:w-1/3 flex flex-col gap-6">
          
          {eTicket ? (
            /* STUNNING PRINTABLE E-TICKET CARD */
            <div 
              id="print-ticket-wrapper" 
              className="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-3xl p-6 shadow-2xl relative border-2 border-slate-800 animate-fadeIn"
            >
              <div className="flex justify-between items-center border-b border-slate-800 pb-4 mb-4">
                <div>
                  <span className="text-[10px] font-black uppercase tracking-widest text-orange-400">Boarding E-Ticket</span>
                  <h3 className="font-display font-bold text-lg text-white">IndiaYatra</h3>
                </div>
                <div className="text-right">
                  <span className="bg-emerald-500/20 text-emerald-400 font-extrabold text-[10px] uppercase border border-emerald-500/30 px-2 py-0.5 rounded">Confirmed</span>
                </div>
              </div>

              {/* Barcode representation */}
              <div className="bg-white py-4 px-2 rounded-lg flex flex-col items-center mb-6">
                <div className="w-full h-12 bg-slate-900 relative flex items-center justify-center overflow-hidden rounded">
                  {/* barcode styled lines */}
                  <div className="absolute inset-0 flex items-center justify-between px-3">
                    {[2,4,1,3,2,1,4,2,3,1,2,4,1,3,2,1,4,2,3,1].map((w, idx) => (
                      <div key={idx} className="bg-white h-full" style={{ width: `${w}px` }}></div>
                    ))}
                  </div>
                </div>
                <span className="text-[9px] font-black text-slate-800 mt-1.5 tracking-widest">IY-TXN-{eTicket.invoiceId}</span>
              </div>

              <div className="space-y-3.5 text-xs">
                <div className="grid grid-cols-2 gap-2 border-b border-slate-800/50 pb-2.5">
                  <div>
                    <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Item Details</span>
                    <strong className="text-slate-200">{eTicket.itemTitle}</strong>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Category</span>
                    <strong className="text-slate-200 capitalize">{eTicket.vertical}</strong>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-2 border-b border-slate-800/50 pb-2.5">
                  <div>
                    <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Travellers / Seats</span>
                    <strong className="text-slate-200">{eTicket.quantity} Pax</strong>
                  </div>
                  <div>
                    {eTicket.vertical === 'flight' ? (
                      <>
                        <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Selected Seats</span>
                        <strong className="text-slate-200">{eTicket.seatsList}</strong>
                      </>
                    ) : eTicket.vertical === 'hotel' ? (
                      <>
                        <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Selected Tier</span>
                        <strong className="text-slate-200">{eTicket.tier}</strong>
                      </>
                    ) : (
                      <>
                        <span className="text-slate-500 block text-[9px] uppercase tracking-wider">Status Date</span>
                        <strong className="text-slate-200">{eTicket.date}</strong>
                      </>
                    )}
                  </div>
                </div>

                <div className="bg-slate-800/40 p-3.5 rounded-xl space-y-1.5">
                  <div className="flex justify-between text-[10px] text-slate-400">
                    <span>Base Amount</span>
                    <span>{window.formatPrice(eTicket.baseTotal, currency)}</span>
                  </div>
                  {Number(eTicket.discount) > 0 && (
                    <div className="flex justify-between text-[10px] text-emerald-400">
                      <span>Loyalty Perk Discount</span>
                      <span>- {window.formatPrice(eTicket.discount, currency)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-[10px] text-slate-400">
                    <span>Indian GST (5%)</span>
                    <span>{window.formatPrice(eTicket.gst, currency)}</span>
                  </div>
                  <div className="border-t border-slate-700 pt-2 flex justify-between font-bold text-sm text-white">
                    <span>Total Paid</span>
                    <span className="text-orange-400">{window.formatPrice(eTicket.finalPayable, currency)}</span>
                  </div>
                </div>

                <div className="text-center pt-2">
                  <span className="text-[10px] text-slate-500 italic block mb-3">Earned +{eTicket.pointsEarned} loyalty points from this trip!</span>
                  <button 
                    onClick={handlePrintInvoice}
                    className="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs flex items-center justify-center gap-2 border border-orange-600 focus:outline-none transition-colors"
                  >
                    <i className="fa-solid fa-print"></i> Print E-Ticket / Save PDF
                  </button>
                </div>
              </div>
            </div>
          ) : (
            /* INTERACTIVE PAYMENT CALCULATOR AND PORTAL GATEWAY */
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
              <div className="pb-4 border-b border-slate-100 mb-5">
                <span className="text-slate-400 text-xs font-bold uppercase tracking-wider block">Aggregate Live Price</span>
                <span className="text-3xl font-extrabold text-orange-500">
                  {window.formatPrice(calc.activePerSeat, currency)}
                </span>
                <span className="text-slate-400 text-xs font-medium block mt-0.5">per person / seat</span>
              </div>

              {/* Quantity Select (If not flight) */}
              {pkg.vertical_type !== 'flight' && (
                <div className="mb-4">
                  <label htmlFor="qty-select" className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Quantity (Pax)</label>
                  <input 
                    id="qty-select"
                    type="number" 
                    min="1"
                    max={pkg.availability}
                    value={seatsCount}
                    onChange={(e) => setSeatsCount(Math.max(1, Math.min(pkg.availability, Number(e.target.value))))}
                    className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm"
                  />
                  <span className="text-[10px] text-slate-400 block mt-1">Maximum available: {pkg.availability} rooms/tours</span>
                </div>
              )}

              {/* Price Breakdown */}
              <div className="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-5 text-xs space-y-2">
                <div className="flex justify-between text-slate-500">
                  <span>Base Cost ({calc.activeSeatsNum} x {window.formatPrice(calc.activePerSeat, currency)})</span>
                  <span>{window.formatPrice(calc.baseTotal, currency)}</span>
                </div>
                {calc.discountAmt > 0 && (
                  <div className="flex justify-between text-emerald-600 font-bold">
                    <span>Discount ({calc.perkText})</span>
                    <span>- {window.formatPrice(calc.discountAmt, currency)}</span>
                  </div>
                )}
                <div className="flex justify-between text-slate-400">
                  <span>CGST (2.5%)</span>
                  <span>{window.formatPrice(calc.cgst, currency)}</span>
                </div>
                <div className="flex justify-between text-slate-400">
                  <span>SGST (2.5%)</span>
                  <span>{window.formatPrice(calc.sgst, currency)}</span>
                </div>
                <div className="border-t border-slate-200 pt-2 flex justify-between font-bold text-sm text-slate-800">
                  <span>Total Payable</span>
                  <span className="text-orange-500">{window.formatPrice(calc.finalPayable, currency)}</span>
                </div>
              </div>

              {/* Split payment matrix */}
              <div className="mb-5 bg-slate-50/50 p-4 rounded-xl border border-slate-150">
                <label className="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                  <input 
                    type="checkbox"
                    checked={splitEnabled}
                    onChange={(e) => setSplitEnabled(e.target.checked)}
                    className="rounded border-slate-300 text-orange-500 focus:ring-orange-500 accent-orange-500 w-4 h-4"
                  />
                  <span>Split Payment with Cohorts</span>
                </label>
                
                {splitEnabled && (
                  <div className="mt-4 border-t border-slate-200 pt-4 animate-fadeIn">
                    <div className="flex justify-between text-xs font-bold text-slate-500 mb-1.5">
                      <span>Travel Cohorts</span>
                      <span className="text-orange-500">{splitCount} Travelers</span>
                    </div>
                    <input 
                      type="range"
                      min="2"
                      max={Math.max(2, calc.activeSeatsNum)}
                      value={splitCount}
                      onChange={handleSplitCountChange}
                      className="w-full accent-orange-500 cursor-pointer"
                    />
                    <div className="mt-3 flex justify-between items-center text-xs bg-white p-2.5 rounded-lg border border-slate-150">
                      <span className="text-slate-400 font-bold uppercase">Per Cohort Share</span>
                      <strong className="text-slate-800 font-black text-sm">{window.formatPrice(calc.finalPayable / splitCount, currency)}</strong>
                    </div>
                  </div>
                )}
              </div>

              {/* Payment Gateway Form Panel */}
              <form onSubmit={handlePaymentSubmit} className="space-y-4">
                <span className="block text-xs font-bold text-slate-400 uppercase tracking-wider">AGGREGATOR CHECKOUT PORTAL</span>
                
                <div>
                  <label htmlFor="card-name" className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cardholder Name</label>
                  <input 
                    id="card-name"
                    type="text" 
                    required
                    value={cardName}
                    onChange={(e) => setCardName(e.target.value)}
                    placeholder="Enter full name"
                    className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm"
                  />
                </div>

                <div>
                  <label htmlFor="card-number" className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Card Number (16-Digit Mask)</label>
                  <input 
                    id="card-number"
                    type="text" 
                    required
                    maxLength="19"
                    value={cardNumber}
                    onChange={(e) => {
                      // Apply simple 16-digit spacing mask
                      const val = e.target.value.replace(/\s?/g, '').replace(/[^0-9]/g, '');
                      const matches = val.match(/\d{4,16}/g);
                      const match = matches && matches[0] || '';
                      const parts = [];
                      for (let i = 0, len = match.length; i < len; i += 4) {
                        parts.push(match.substring(i, i + 4));
                      }
                      if (parts.length > 0) {
                        setCardNumber(parts.join(' '));
                      } else {
                        setCardNumber(val);
                      }
                    }}
                    placeholder="XXXX XXXX XXXX XXXX"
                    className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label htmlFor="card-expiry" className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Expiry Date</label>
                    <input 
                      id="card-expiry"
                      type="text" 
                      required
                      placeholder="MM/YY"
                      maxLength="5"
                      value={cardExpiry}
                      onChange={(e) => {
                        const val = e.target.value.replace(/[^0-9]/g, '');
                        if (val.length >= 2) {
                          setCardExpiry(val.substring(0, 2) + '/' + val.substring(2, 4));
                        } else {
                          setCardExpiry(val);
                        }
                      }}
                      className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm"
                    />
                  </div>
                  <div>
                    <label htmlFor="card-cvv" className="block text-[10px] font-bold text-slate-500 uppercase mb-1">CVV</label>
                    <input 
                      id="card-cvv"
                      type="password" 
                      required
                      maxLength="3"
                      value={cardCvv}
                      onChange={(e) => setCardCvv(e.target.value.replace(/[^0-9]/g, ''))}
                      placeholder="•••"
                      className="w-full border border-slate-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm"
                    />
                  </div>
                </div>

                <button 
                  type="submit"
                  disabled={loading}
                  className={`w-full font-bold py-3 px-6 rounded-xl transition-all text-xs flex items-center justify-center gap-2 uppercase tracking-wider
                    ${loading ? 'bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200' : 'bg-orange-500 hover:bg-orange-600 text-white shadow-md shadow-orange-100 border border-orange-600'}`}
                >
                  {loading ? (
                    <>
                      <div className="w-4 h-4 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></div>
                      Processing Bank Handshake...
                    </>
                  ) : (
                    <>
                      Proceed Payment — {window.formatPrice(calc.finalPayable, currency)}
                    </>
                  )}
                </button>
              </form>
            </div>
          )}

        </div>

      </div>
    );
  }

  ReactDOM.createRoot(document.getElementById('booking-detail-root')).render(<DetailContainer />);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
