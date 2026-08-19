import { useState, useEffect, useRef } from 'react';

const BADGES_DATABASE = {
  Boulder: {
    emoji: '🛡️',
    title: 'Boulder Badge',
    description: 'Awarded for your first booking on IndiaYatra. The journey of a thousand miles starts here!',
  },
  Cascade: {
    emoji: '🌊',
    title: 'Cascade Badge',
    description: 'Awarded for booking across two different categories (e.g. Flight + Hotel). Multi-faceted traveler!',
  },
  Volcano: {
    emoji: '🔥',
    title: 'Volcano Badge',
    description: 'Elite tier achievement! Unlocked for 5+ bookings or total spent above ₹75,000.',
  },
};

function getLevelInfo(pts) {
  if (pts >= 3001) return { name: 'Apex Voyager', discount: '10% Tier Discount', next: 5000, percent: 100 };
  if (pts >= 1001) return { name: 'Explorer',     discount: '5% Tier Discount',  next: 3000, percent: ((pts - 1001) / 2000) * 100 };
  return             { name: 'Novice',           discount: 'Standard Base Fee', next: 1000, percent: (pts / 1000) * 100 };
}

function isActive(href) {
  const currentPath = window.__CURRENT_PATH__ || '';
  const base = href.split('?')[0];
  return currentPath.endsWith(base.replace(/^\.\.\//, '')) || currentPath.endsWith(base);
}

export default function NavBar() {
  const session   = window.__SESSION__ || {};
  const { loggedIn, role, name, loyalty_points, user_level, badge_flags } = session;
  const userBadges  = badge_flags ? badge_flags.split(',').filter(Boolean) : [];
  const levelInfo   = getLevelInfo(loyalty_points || 0);

  const [scrolled,        setScrolled]        = useState(false);
  const [scrollProgress,  setScrollProgress]  = useState(0);
  const [currency,        setCurrency]        = useState(window.__CURRENCY__ || 'INR');
  const [profileOpen,     setProfileOpen]     = useState(false);
  const [alertsOpen,      setAlertsOpen]      = useState(false);
  const [mobileMenuOpen,  setMobileMenuOpen]  = useState(false);
  const [notifications,   setNotifications]   = useState([
    { id: 1, text: '🚨 Flash Sale: Flights to Ladakh are currently 12% off!', read: false },
    { id: 2, text: '✅ Booking Confirmed: Your e-ticket for Kerala is ready for download.', read: false },
    { id: 3, text: '🎉 Level Up! You reached Level 2 and unlocked the Cascade Badge.', read: false },
  ]);

  const profileRef = useRef(null);
  const alertsRef  = useRef(null);
  const mobileRef  = useRef(null);

  // ── Scroll effects: navbar shadow + scroll-progress bar ──────────────────
  useEffect(() => {
    const onScroll = () => {
      setScrolled(window.scrollY > 8);
      const docH   = document.documentElement.scrollHeight - window.innerHeight;
      const progress = docH > 0 ? (window.scrollY / docH) * 100 : 0;
      setScrollProgress(progress);
      document.documentElement.style.setProperty('--scroll-progress', `${progress}%`);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // ── Click-outside handler to close dropdowns ──────────────────────────────
  useEffect(() => {
    const handler = (e) => {
      if (profileRef.current && !profileRef.current.contains(e.target)) setProfileOpen(false);
      if (alertsRef.current  && !alertsRef.current.contains(e.target))  setAlertsOpen(false);
      if (mobileRef.current  && !mobileRef.current.contains(e.target))  setMobileMenuOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // ── Listen for currency changes from other components ─────────────────────
  useEffect(() => {
    const onCurrencyChange = (e) => setCurrency(e.detail);
    window.addEventListener('currencyChange', onCurrencyChange);
    return () => window.removeEventListener('currencyChange', onCurrencyChange);
  }, []);

  const changeCurrency = (e) => {
    setCurrency(e.target.value);
    window.setGlobalCurrency?.(e.target.value);
  };

  const unreadCount = notifications.length;

  return (
    <header className="sticky top-0 z-50 w-full bg-white border-b border-slate-100 shadow-sm transition-shadow duration-300">
      {/* Scroll progress bar */}
      <div className="scroll-progress-bar" style={{ width: `${scrollProgress}%` }} aria-hidden="true" />

      {/* ── Evaluator Demo Switcher Bar ──────────────────────────────────── */}
      <div id="evaluator-bar" className="bg-gradient-to-r from-slate-900 to-slate-950 text-white text-xs px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-800">
        <div className="flex items-center gap-2">
          <span className="inline-block w-2.5 h-2.5 rounded-full bg-orange-500 animate-pulse" />
          <span className="font-semibold tracking-wide text-slate-300">SYSTEM ARCHITECT DEMO ROLE SWITCHER:</span>
          <span className="bg-slate-800 px-2 py-0.5 rounded text-orange-400 font-bold border border-slate-700 uppercase">
            {loggedIn ? role : 'guest'}
          </span>
        </div>
        <div className="flex items-center gap-3">
          <a href="?toggle_role=admin"     className={`px-2.5 py-1 rounded font-bold transition-colors ${role==='admin'&&loggedIn?'bg-orange-500 text-white':'bg-slate-800 hover:bg-slate-700 text-slate-300'}`}>🛡️ Admin View</a>
          <a href="?toggle_role=customer"  className={`px-2.5 py-1 rounded font-bold transition-colors ${role==='customer'&&loggedIn?'bg-emerald-500 text-white':'bg-slate-800 hover:bg-slate-700 text-slate-300'}`}>👤 Customer View</a>
          <a href="?toggle_role=guest"     className={`px-2.5 py-1 rounded font-bold transition-colors ${!loggedIn?'bg-slate-500 text-white':'bg-slate-800 hover:bg-slate-700 text-slate-300'}`}>👁️ Guest View</a>
          <div className="flex items-center gap-1.5 ml-2 border-l border-slate-700 pl-3">
            <label htmlFor="currency-select" className="text-slate-400 font-medium">Currency:</label>
            <select
              id="currency-select"
              value={currency}
              onChange={changeCurrency}
              className="bg-slate-800 border border-slate-700 text-white font-bold py-0.5 px-2 rounded focus:outline-none focus:ring-1 focus:ring-orange-500"
            >
              <option value="INR">INR (₹)</option>
              <option value="USD">USD ($)</option>
              <option value="EUR">EUR (€)</option>
              <option value="GBP">GBP (£)</option>
            </select>
          </div>
        </div>
      </div>

      {/* ── Main Navigation ──────────────────────────────────────────────── */}
      <nav className={`bg-white transition-all duration-300 ${scrolled ? 'py-3.5 shadow-md' : 'py-5'}`} aria-label="Main Navigation">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">

          {/* Logo */}
          <a href="index.php" className="flex items-center gap-2 group">
            <div className="w-14 h-14 rounded-xl bg-orange-500 flex items-center justify-center text-white text-2xl font-bold transition-transform group-hover:scale-105">🇮🇳</div>
            <span className="font-display text-2xl font-bold tracking-tight text-slate-900">India<span className="text-orange-500">Yatra</span></span>
          </a>

          {/* Desktop nav links */}
          <div className="hidden md:flex items-center gap-8">
            <a href="index.php" className={`text-base font-semibold transition-colors ${isActive('index.php')?'text-orange-600':'text-slate-600 hover:text-orange-500'}`}>Explore</a>
            <a href="schedules.php" className={`text-base font-semibold transition-colors ${isActive('schedules.php')?'text-orange-600':'text-slate-600 hover:text-orange-500'}`}>Schedules</a>
            <a href="guides.php" className={`text-base font-semibold transition-colors ${isActive('guides.php')?'text-orange-600':'text-slate-600 hover:text-orange-500'}`}>Guides</a>
            {loggedIn && role === 'customer' && (
              <a href="my-bookings.php" className={`text-base font-semibold transition-colors ${isActive('my-bookings.php')?'text-orange-600':'text-slate-600 hover:text-orange-500'}`}>My Bookings</a>
            )}
            {loggedIn && role === 'admin' && (
              <a href="admin/dashboard.php" className="text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-colors">
                <i className="fa-solid fa-gauge" /> Admin Dashboard
              </a>
            )}
          </div>

          {/* Right widgets */}
          <div className="flex items-center gap-4">

            {/* Notifications */}
            {loggedIn && (
              <div className="relative" ref={alertsRef}>
                <button onClick={() => setAlertsOpen(!alertsOpen)} className="p-2 text-slate-500 hover:text-orange-500 rounded-full hover:bg-slate-100 relative transition-colors focus:outline-none" aria-label="View notifications">
                  <i className="fa-regular fa-bell text-xl" />
                  {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-5 h-5 rounded-full bg-orange-600 text-white text-[10px] font-bold flex items-center justify-center border border-white">{unreadCount}</span>
                  )}
                </button>
                {alertsOpen && (
                  <div className="absolute right-0 mt-2 w-80 bg-white border border-slate-200 rounded-xl shadow-xl py-2 z-50 animate-fadeIn">
                    <div className="px-4 py-2 border-b border-slate-100 flex items-center justify-between">
                      <span className="font-bold text-slate-800 text-sm">Notifications</span>
                      {unreadCount > 0 && <button onClick={() => setNotifications([])} className="text-xs text-orange-500 hover:text-orange-600 font-bold focus:outline-none">Clear All</button>}
                    </div>
                    <div className="max-h-64 overflow-y-auto">
                      {notifications.length > 0 ? notifications.map(n => (
                        <div key={n.id} className="px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-0 transition-colors text-xs text-slate-700">{n.text}</div>
                      )) : (
                        <div className="px-4 py-6 text-center text-slate-400 text-xs">
                          <i className="fa-regular fa-circle-check text-2xl mb-1 text-slate-300 block" />No new alerts
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Badge showcase (desktop) */}
            {loggedIn && (
              <div className="hidden lg:flex items-center gap-2 border-l border-slate-200 pl-4 pr-2">
                {Object.entries(BADGES_DATABASE).map(([key, b]) => {
                  const unlocked = userBadges.includes(key);
                  return (
                    <div key={key} className="group relative">
                      <span className={`text-2xl cursor-default select-none filter transition-transform hover:scale-110 duration-200 block ${unlocked?'':'opacity-20 grayscale'}`}>{b.emoji}</span>
                      <div className="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 bg-slate-900 text-white rounded-lg p-3 shadow-xl opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-xs z-50">
                        <div className="font-bold text-orange-400 mb-1">{b.title}</div>
                        <p className="text-slate-300 leading-relaxed">{b.description}</p>
                        <div className="mt-1.5 font-bold text-[10px] uppercase tracking-wider text-slate-400">
                          Status: <span className={unlocked?'text-emerald-400':'text-red-400'}>{unlocked?'Unlocked ✓':'Locked 🔒'}</span>
                        </div>
                        <div className="absolute top-full left-1/2 -translate-x-1/2 w-2 h-2 bg-slate-900 rotate-45" />
                      </div>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Profile Avatar or Auth links */}
            {loggedIn ? (
              <div className="relative" ref={profileRef}>
                <button onClick={() => setProfileOpen(!profileOpen)} className="flex items-center gap-2 focus:outline-none hover:opacity-90 transition-opacity" aria-expanded={profileOpen} aria-label="User profile settings">
                  <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(name||'User')}&background=f97316&color=fff&bold=true`} alt={name||'User Profile'} className="w-9 h-9 rounded-full border-2 border-orange-500 shadow-sm" />
                  <div className="hidden sm:block text-left">
                    <div className="text-xs font-bold text-slate-800">{name}</div>
                    <div className="text-[10px] text-orange-600 font-bold uppercase tracking-wider">Level {user_level} {levelInfo.name}</div>
                  </div>
                </button>
                {profileOpen && (
                  <div className="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl py-3 z-50 animate-fadeIn">
                    <div className="px-4 py-2 border-b border-slate-100 mb-2">
                      <p className="text-xs text-slate-400 font-medium">Logged in as</p>
                      <p className="text-sm font-bold text-slate-800">{name}</p>
                      <p className="text-xs text-orange-600 font-bold uppercase tracking-wider mt-0.5">Level {user_level} — {levelInfo.name}</p>
                    </div>
                    <div className="px-4 py-2.5">
                      <div className="flex justify-between text-xs font-bold text-slate-700 mb-1">
                        <span>Loyalty points</span><span>{loyalty_points} / {levelInfo.next} Pts</span>
                      </div>
                      <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden mb-1.5 border border-slate-200">
                        <div className="bg-gradient-to-r from-orange-500 to-amber-400 h-full rounded-full transition-all duration-500" style={{ width: `${levelInfo.percent}%` }} />
                      </div>
                      <p className="text-[10px] text-slate-500 leading-normal"><i className="fa-solid fa-gift text-orange-500 mr-1" />Active Perk: <strong className="text-slate-700">{levelInfo.discount}</strong></p>
                    </div>
                    {/* Mobile badges */}
                    <div className="px-4 py-2 lg:hidden border-t border-slate-100 mt-2">
                      <p className="text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-2">Unlocked Badges</p>
                      <div className="flex items-center gap-3">
                        {Object.entries(BADGES_DATABASE).map(([key, b]) => (
                          <span key={key} className={`text-2xl filter ${userBadges.includes(key)?'':'opacity-25 grayscale'}`} title={b.title}>{b.emoji}</span>
                        ))}
                      </div>
                    </div>
                    <div className="border-t border-slate-100 mt-2 pt-2">
                      {role === 'admin' && <a href="admin/dashboard.php" className="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"><i className="fa-solid fa-gauge text-slate-400" /> Admin Panel</a>}
                      <a href="my-bookings.php" className="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"><i className="fa-solid fa-ticket text-slate-400" /> My Bookings</a>
                      <a href="logout.php" className="flex items-center gap-2 px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors border-t border-slate-100 mt-1"><i className="fa-solid fa-arrow-right-from-bracket text-red-400" /> Log Out</a>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <div className="flex items-center gap-3">
                <a href="login.php"    className="text-sm font-semibold text-slate-600 hover:text-orange-500 transition-colors">Login</a>
                <a href="register.php" className="bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-2 px-4 rounded-xl shadow-sm transition-colors">Register</a>
              </div>
            )}

            {/* Hamburger (mobile) */}
            <button onClick={() => setMobileMenuOpen(!mobileMenuOpen)} className="md:hidden p-2 text-slate-600 hover:text-orange-500 rounded-lg focus:outline-none" aria-label="Toggle menu">
              <i className="fa-solid fa-bars text-xl" />
            </button>
          </div>
        </div>

        {/* Mobile drawer */}
        {mobileMenuOpen && (
          <div ref={mobileRef} className="md:hidden border-t border-slate-100 bg-white py-3 px-4 shadow-inner animate-slideDown">
            <div className="flex flex-col gap-2">
              <a href="index.php"          className="px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-lg">Explore Packages</a>
              <a href="schedules.php"      className="px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-lg">Schedules</a>
              <a href="guides.php"         className="px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-lg">Travel Guides</a>
              {loggedIn && role==='customer' && <a href="my-bookings.php"    className="px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-lg">My Bookings</a>}
              {loggedIn && role==='admin'    && <a href="admin/dashboard.php" className="px-3 py-2 text-sm font-bold text-slate-800 bg-slate-100 rounded-lg">Admin Dashboard</a>}
            </div>
          </div>
        )}
      </nav>
    </header>
  );
}
