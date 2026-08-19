<?php
declare(strict_types=1);
/**
 * IndiaYatra — Travel Guides & How-To Knowledge Base
 *
 * Interactive travel guides educating tourists on Indian travel logistics:
 * Flights, Trains, Buses, Packing, and Emergency Information.
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

$guides = [];
$loadError = false;

try {
    $stmt = $db->prepare("
        SELECT guide_id, title, destination, state, content, image_url, best_time_to_visit
        FROM guides
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $guides = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[IndiaYatra][guides] ' . $e->getMessage());
    $loadError = true;
    $guides = [];
}

$initialStateJson = json_encode([
    'guides'    => $guides,
    'loadError' => $loadError,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

if ($initialStateJson === false) {
    $initialStateJson = json_encode(['guides' => [], 'loadError' => true]);
}

$GLOBALS['pageTitle']       = 'Travel Guides & How-To — IndiaYatra';
$GLOBALS['pageDescription'] = 'Expert-curated travel guides and how-to articles for flights, trains, and buses in India. Plan your perfect trip with local insights.';
require_once __DIR__ . '/includes/header.php';
?>

<script>
  window.__GUIDES_INITIAL_STATE__ = <?= $initialStateJson ?>;
</script>

<main class="min-h-screen pb-16" style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">

  <!-- Page Header -->
  <section class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-orange-950 text-white overflow-hidden py-16 md:py-20 border-b border-orange-500/20">
    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #f97316 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <span class="inline-flex items-center gap-1.5 bg-orange-500/10 text-orange-400 border border-orange-500/30 text-xs font-semibold px-4 py-1.5 rounded-full mb-4">
        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
        Knowledge Base
      </span>
      <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-bold leading-tight mb-4">
        Travel <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300">Guides</span>
      </h1>
      <p class="text-base sm:text-lg text-slate-300 leading-relaxed max-w-2xl mx-auto">
        Master Indian travel logistics with our expert how-to guides. From web check-in to IRCTC Tatkal, we have you covered.
      </p>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
    <div class="flex flex-col lg:flex-row gap-8">

      <!-- Main Content: How-To Guides -->
      <div class="flex-1">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-10 h-10 rounded-xl bg-orange-500 flex items-center justify-center text-white">
            <i className="fa-solid fa-book-open"></i>
          </div>
          <div>
            <h2 className="font-display text-xl font-bold text-slate-900">How To Travel in India</h2>
            <p className="text-xs text-slate-500">Step-by-step guides for flights, trains, and buses</p>
          </div>
        </div>

        <div id="guides-root" className="space-y-4"></div>
      </div>

      <!-- Side Panel: Destination Quick Guides -->
      <div class="w-full lg:w-80 flex-shrink-0">
        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm sticky top-24">
          <div className="flex items-center gap-2 mb-4">
            <i className="fa-solid fa-map-location-dot text-orange-500"></i>
            <h3 className="font-display text-lg font-bold text-slate-900">Destination Tips</h3>
          </div>
          <p className="text-xs text-slate-500 mb-4">Quick packing tips based on popular destinations.</p>

          <div id="destination-tips" className="space-y-3">
            <!-- Dynamic destination tips rendered here -->
          </div>
        </div>
      </div>

    </div>
  </div>

</main>

<script type="text/babel">
(function() {
  const { useState, useEffect } = React;
  const session = window.__SESSION__ || {};
  const initialState = window.__GUIDES_INITIAL_STATE__ || { guides: [], loadError: false };

  const HOW_TO_DATA = [
    {
      id: 'how-to-fly',
      icon: 'fa-plane',
      color: 'sky',
      title: 'How to Fly in India',
      subtitle: 'Security, baggage, web check-in, and DigiYatra',
      tips: [
        { title: 'Web Check-in', desc: 'Check in 48 hours to 2 hours before departure on the airline website or app. Select seats, add meals, and skip airport counters.', icon: 'fa-laptop' },
        { title: 'Baggage Rules', desc: 'Economy class: 15kg check-in + 7kg cabin (max 1 piece). Carry medicines, electronics, and valuables in cabin baggage.', icon: 'fa-suitcase' },
        { title: 'Security Check', desc: 'Arrive 2 hours before domestic flights. Keep ID (Aadhaar/Passport) and boarding pass accessible. Remove laptops and liquids at security.', icon: 'fa-shield-halved' },
        { title: 'DigiYatra', desc: 'Use the DigiYatra app for paperless boarding at 15+ Indian airports. Register your Aadhaar and facial biometrics for seamless entry.', icon: 'fa-face-smile' },
        { title: 'Boarding Gate', desc: 'Gates close 25 minutes before departure. Boarding typically starts 45 minutes before departure. Keep your boarding pass handy.', icon: 'fa-door-open' },
      ]
    },
    {
      id: 'how-to-train',
      icon: 'fa-train',
      color: 'emerald',
      title: 'How to Travel by Train',
      subtitle: 'IRCTC booking, Tatkal, RAC vs Waitlist, coach positioning',
      tips: [
        { title: 'IRCTC Account', desc: 'Register at irctc.co.in. Verify with Aadhaar-linked mobile. Use the IRCTC Rail Connect app for faster booking.', icon: 'fa-user-plus' },
        { title: 'Tatkal Booking', desc: 'AC Tatkal opens 1 day before at 10 AM. Non-AC Tatkal opens at 11 AM. Have passenger details and payment ready before 10 AM sharp.', icon: 'fa-clock' },
        { title: 'RAC vs Waitlist', desc: 'RAC (Reservation Against Cancellation) guarantees a seat that may be split. Waitlist (WL/GNWL) is confirmable only if cancellations occur. Prefer RAC over WL.', icon: 'fa-ticket' },
        { title: 'Coach Position', desc: 'Check coach position on the IRCTC app or website. The green board on the platform shows the final position. Stand near the sign for easy boarding.', icon: 'fa-map-pin' },
        { title: 'On-Board Meals', desc: 'Book meals during ticket booking (IRCTC e-catering). Pantry cars available on long-distance trains. Carry water and snacks for short journeys.', icon: 'fa-utensils' },
      ]
    },
    {
      id: 'how-to-bus',
      icon: 'fa-bus',
      color: 'amber',
      title: 'How to Book Intercity Buses',
      subtitle: 'Sleeper vs Seater, pickup points, luggage safety',
      tips: [
        { title: 'Choose Seater vs Sleeper', desc: 'Seater (2+2) is faster and cheaper for <300km. Sleeper (2+1) is essential for overnight journeys >400km. Volvo/Mercedes are premium; ordinary is budget.', icon: 'fa-chair' },
        { title: 'Pickup Point Verification', desc: 'Confirm the exact boarding point on the ticket. Use the bus operator app to track the bus live. Reach 15 minutes early to avoid missing the bus.', icon: 'fa-location-dot' },
        { title: 'Luggage Safety', desc: 'Keep valuables (phone, wallet, laptop) in cabin-sized bag under your seat. Label large bags with name and destination. Avoid keeping electronics in checked luggage.', icon: 'fa-luggage-cart' },
        { title: 'Operator Ratings', desc: 'Check RedBus/AbhiBus ratings before booking. VRL, Zingbus, and Sharma Transports have high safety ratings. Read recent reviews for cleanliness and punctuality.', icon: 'fa-star' },
        { title: 'Cancellation Policy', desc: 'Most operators allow cancellation up to 4 hours before departure with 50-75% refund. Read the fine print. Travel insurance is recommended for high-value trips.', icon: 'fa-shield' },
      ]
    }
  ];

  const DESTINATION_TIPS = [
    { city: 'Ladakh', zone: 'North', icon: '🏔️', tips: 'Carry thermals, sunscreen SPF50+, and Diamox for altitude. Waterproof shoes are mandatory.' },
    { city: 'Cherrapunji', zone: 'North-East', icon: '🌧️', tips: 'Umbrella, raincoat, and waterproof bags are essential. Book caves guides in advance during monsoon.' },
    { city: 'Kutch', zone: 'West', icon: '🏜️', tips: 'Light cottons by day, warm jacket by night. Carry goggles for the white desert glare.' },
    { city: 'Goa', zone: 'West', icon: '🏖️', tips: 'Reef-safe sunscreen, flip-flops, and a portable power bank for beach days.' },
    { city: 'Kerala', zone: 'South', icon: '🌴', tips: 'Light breathable fabrics, mosquito repellent, and rain umbrella for sudden showers.' },
    { city: 'Varanasi', zone: 'North', icon: '🪔', tips: 'Modest clothing for temple visits, comfortable walking shoes for ghats, and a small torch for early morning boat rides.' },
  ];

  function HowToAccordion({ guide, isOpen, onToggle }) {
    return (
      <div className={`bg-white rounded-2xl border transition-all duration-300 ${isOpen ? 'border-orange-200 shadow-md' : 'border-slate-200 shadow-sm'}`}>
        <button
          onClick={onToggle}
          className="w-full flex items-center justify-between p-5 text-left focus:outline-none"
          aria-expanded={isOpen}
        >
          <div className="flex items-center gap-4">
            <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-white ${guide.color === 'sky' ? 'bg-sky-500' : guide.color === 'emerald' ? 'bg-emerald-500' : 'bg-amber-500'}`}>
              <i className={`fa-solid ${guide.icon} text-lg`}></i>
            </div>
            <div>
              <h3 className="font-display text-base font-bold text-slate-900">{guide.title}</h3>
              <p className="text-xs text-slate-500 mt-0.5">{guide.subtitle}</p>
            </div>
          </div>
          <i className={`fa-solid fa-chevron-down text-slate-400 transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}></i>
        </button>

        {isOpen && (
          <div className="px-5 pb-5 animate-fadeIn">
            <div className="border-t border-slate-100 pt-4 space-y-3">
              {guide.tips.map((tip, idx) => (
                <div key={idx} className="flex gap-3 bg-slate-50 p-4 rounded-xl border border-slate-100">
                  <div className="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-orange-500 flex-shrink-0">
                    <i className={`fa-solid ${tip.icon} text-sm`}></i>
                  </div>
                  <div>
                    <h4 className="text-sm font-bold text-slate-800 mb-1">{tip.title}</h4>
                    <p className="text-xs text-slate-600 leading-relaxed">{tip.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    );
  }

  function GuideCard({ guide }) {
    const [expanded, setExpanded] = useState(false);

    return (
      <article className="bg-white rounded-3xl border border-slate-200/70 overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-orange-200 flex flex-col shadow-sm">
        <div className="relative h-56 overflow-hidden bg-slate-100 flex-shrink-0">
          <img
            src={guide.image_url || 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80'}
            alt={guide.title}
            loading="lazy"
            onError={(e) => { e.target.src = 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80'; }}
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
          <div className="absolute bottom-4 left-4 right-4">
            <span className="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md shadow-sm">
              {guide.destination}
            </span>
            <h3 className="font-display text-xl font-bold text-white mt-2 leading-tight">{guide.title}</h3>
          </div>
        </div>

        <div className="p-5 flex-1 flex flex-col">
          <div className="flex items-center gap-2 mb-3">
            <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
              <i className="fa-regular fa-calendar text-[10px]"></i>
              {guide.best_time_to_visit || 'Year-round'}
            </span>
            <span className="text-xs text-slate-500 font-semibold">{guide.state}</span>
          </div>

          <p className={`text-sm text-slate-600 leading-relaxed ${expanded ? '' : 'line-clamp-3'}`}>
            {guide.content}
          </p>

          <button
            onClick={() => setExpanded(!expanded)}
            className="mt-3 text-xs font-bold text-orange-500 hover:text-orange-600 self-start"
          >
            {expanded ? 'Show less' : 'Read more'}
          </button>

          <div className="mt-4 pt-4 border-t border-slate-100">
            <a
              href="schedules.php"
              className="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs py-2 px-4 rounded-xl shadow-sm transition-colors"
            >
              <i className="fa-solid fa-plane"></i> Check Schedules
            </a>
          </div>
        </div>
      </article>
    );
  }

  function DestinationTipCard({ tip }) {
    return (
      <div className="bg-slate-50 p-3 rounded-xl border border-slate-100">
        <div className="flex items-center gap-2 mb-2">
          <span className="text-xl">{tip.icon}</span>
          <div>
            <h4 className="text-sm font-bold text-slate-800">{tip.city}</h4>
            <span className="text-[10px] text-slate-500 font-semibold uppercase">{tip.zone}</span>
          </div>
        </div>
        <p className="text-xs text-slate-600 leading-relaxed">{tip.tips}</p>
      </div>
    );
  }

  function GuidesPage() {
    const [guides, setGuides] = useState(() => initialState.guides || []);
    const [openAccordion, setOpenAccordion] = useState(null);
    const [toast, setToast] = useState(null);

    const showToast = (message) => {
      setToast(message);
      setTimeout(() => setToast(null), 3500);
    };

    const toggleAccordion = (id) => {
      setOpenAccordion(openAccordion === id ? null : id);
    };

    return (
      <div className="flex flex-col gap-8">
        {toast && (
          <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-lg z-50">
            {toast}
          </div>
        )}

        {/* How-To Accordion Section */}
        <div>
          <div className="flex items-center gap-2 mb-4">
            <i className="fa-solid fa-graduation-cap text-orange-500"></i>
            <h3 className="font-display text-lg font-bold text-slate-900">Travel Logistics Masterclass</h3>
          </div>
          <div className="space-y-3">
            {HOW_TO_DATA.map((guide) => (
              <HowToAccordion
                key={guide.id}
                guide={guide}
                isOpen={openAccordion === guide.id}
                onToggle={() => toggleAccordion(guide.id)}
              />
            ))}
          </div>
        </div>

        {/* Destination Quick Guides */}
        <div>
          <div className="flex items-center gap-2 mb-4">
            <i className="fa-solid fa-map-location-dot text-orange-500"></i>
            <h3 className="font-display text-lg font-bold text-slate-900">Destination Packing Tips</h3>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {DESTINATION_TIPS.map((tip, idx) => (
              <DestinationTipCard key={idx} tip={tip} />
            ))}
          </div>
        </div>

        {/* Destination Guide Cards */}
        {guides.length > 0 && (
          <div>
            <div className="flex items-center gap-2 mb-4">
              <i className="fa-solid fa-compass text-orange-500"></i>
              <h3 className="font-display text-lg font-bold text-slate-900">Curated Destination Guides</h3>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {guides.map(g => (
                <GuideCard key={g.guide_id} guide={g} />
              ))}
            </div>
          </div>
        )}
      </div>
    );
  }

  const rootEl = document.getElementById('guides-root');
  if (rootEl) {
    ReactDOM.createRoot(rootEl).render(<GuidesPage />);
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
