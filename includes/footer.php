  <!-- ═══════════════════════════════════════════════════════════
       SITE FOOTER
  ══════════════════════════════════════════════════════════════ -->
  <footer class="bg-slate-900 text-slate-300 mt-24" role="contentinfo">

    <!-- Top section: 3-column grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-10">

        <!-- Column 1: Brand -->
        <div class="space-y-4">
          <a href="index.php" class="inline-flex items-center gap-2 group" aria-label="IndiaYatra home">
            <span class="text-2xl select-none transition-transform duration-300 group-hover:scale-110" aria-hidden="true">🔥</span>
            <span class="font-display text-2xl font-bold text-white tracking-tight leading-none">
              India<span class="text-brand-400">Yatra</span>
            </span>
          </a>
          <p class="text-sm text-slate-400 leading-relaxed max-w-xs">
            Your trusted partner for curated travel across every region of India. From the peaks of the Himalayas to the backwaters of Kerala — we make every journey unforgettable.
          </p>
          <!-- Social links -->
          <div class="flex items-center gap-3 pt-1">
            <a href="#" aria-label="Follow us on Instagram" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-brand-500 flex items-center justify-center transition-colors duration-200 text-sm">📷</a>
            <a href="#" aria-label="Follow us on Twitter" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-brand-500 flex items-center justify-center transition-colors duration-200 text-sm">🐦</a>
            <a href="#" aria-label="Follow us on Facebook" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-brand-500 flex items-center justify-center transition-colors duration-200 text-sm">📘</a>
            <a href="#" aria-label="Follow us on YouTube" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-brand-500 flex items-center justify-center transition-colors duration-200 text-sm">▶️</a>
          </div>
        </div>

        <!-- Column 2: Quick Links -->
        <div>
          <h3 class="text-sm font-semibold text-white uppercase tracking-widest mb-5">Quick Links</h3>
          <ul class="space-y-2.5">
            <li>
              <a href="index.php" class="text-sm text-slate-400 hover:text-brand-400 transition-colors duration-200 flex items-center gap-2">
                <span class="text-brand-500">›</span> Home
              </a>
            </li>
            <li>
              <a href="index.php#packages" class="text-sm text-slate-400 hover:text-brand-400 transition-colors duration-200 flex items-center gap-2">
                <span class="text-brand-500">›</span> Browse Packages
              </a>
            </li>
            <li>
              <a href="login.php" class="text-sm text-slate-400 hover:text-brand-400 transition-colors duration-200 flex items-center gap-2">
                <span class="text-brand-500">›</span> Login
              </a>
            </li>
            <li>
              <a href="register.php" class="text-sm text-slate-400 hover:text-brand-400 transition-colors duration-200 flex items-center gap-2">
                <span class="text-brand-500">›</span> Register
              </a>
            </li>
            <li>
              <a href="my-bookings.php" class="text-sm text-slate-400 hover:text-brand-400 transition-colors duration-200 flex items-center gap-2">
                <span class="text-brand-500">›</span> My Bookings
              </a>
            </li>
          </ul>
        </div>

        <!-- Column 3: Contact -->
        <div>
          <h3 class="text-sm font-semibold text-white uppercase tracking-widest mb-5">Contact Us</h3>
          <ul class="space-y-3">
            <li class="flex items-start gap-3 text-sm text-slate-400">
              <span class="mt-0.5 flex-shrink-0">📍</span>
              <span>IndiaYatra Travels Pvt. Ltd.<br>Nashik, Maharashtra 422001</span>
            </li>
            <li class="flex items-center gap-3 text-sm text-slate-400">
              <span>📞</span>
              <a href="tel:+919876543210" class="hover:text-brand-400 transition-colors">+91 98765 43210</a>
            </li>
            <li class="flex items-center gap-3 text-sm text-slate-400">
              <span>✉️</span>
              <a href="mailto:support@indiayatra.in" class="hover:text-brand-400 transition-colors">support@indiayatra.in</a>
            </li>
            <li class="flex items-center gap-3 text-sm text-slate-400">
              <span>🕒</span>
              <span>Mon – Sat: 9 AM – 8 PM IST</span>
            </li>
          </ul>

          <!-- GST Badge -->
          <div class="mt-5 inline-flex items-center gap-1.5 text-xs text-slate-500 bg-slate-800 px-3 py-1.5 rounded-full">
            <span>🏛</span> GSTIN: 27AABCI1234A1Z5
          </div>
        </div>

      </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
        <p>© <?= date('Y') ?> IndiaYatra Travels Pvt. Ltd. All rights reserved.</p>
        <div class="flex items-center gap-4">
          <a href="#" class="hover:text-brand-400 transition-colors">Privacy Policy</a>
          <span>·</span>
          <a href="#" class="hover:text-brand-400 transition-colors">Terms &amp; Conditions</a>
          <span>·</span>
          <a href="#" class="hover:text-brand-400 transition-colors">Cancellation Policy</a>
        </div>
      </div>
    </div>

  </footer>

  <!-- Back-to-top button -->
  <button
    id="back-to-top"
    onclick="window.scrollTo({top:0,behavior:'smooth'})"
    aria-label="Back to top"
    class="fixed bottom-6 right-6 z-40 w-11 h-11 rounded-full bg-brand-500 text-white shadow-lg flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 hover:bg-brand-600 hover:shadow-xl hover:-translate-y-1"
  >
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
    </svg>
  </button>

  <!-- Emergency Helpline Widget -->
  <div id="emergency-helpline" class="fixed bottom-6 left-6 z-50">
    <button
      id="helpline-toggle"
      class="w-12 h-12 rounded-full bg-red-600 hover:bg-red-700 text-white shadow-lg flex items-center justify-center transition-all duration-300 hover:scale-110"
      aria-label="Emergency Helplines"
      title="Emergency Helplines"
    >
      <i className="fa-solid fa-phone-volume text-lg"></i>
    </button>

    <div id="helpline-panel" class="absolute bottom-16 left-0 w-72 bg-white rounded-2xl shadow-2xl border border-slate-200 opacity-0 pointer-events-none transition-all duration-300 scale-95 translate-y-2">
      <div className="p-4 border-b border-slate-100">
        <div className="flex items-center justify-between">
          <h3 className="font-display text-sm font-bold text-slate-900">Emergency Helplines</h3>
          <button id="helpline-close" className="w-6 h-6 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 text-xs transition-colors" aria-label="Close">×</button>
        </div>
        <p className="text-[10px] text-slate-500 mt-1">Tap number to copy</p>
      </div>

      <div className="p-3 space-y-2">
        <a href="tel:1363" className="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group">
          <div className="flex items-center gap-3">
            <span className="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-xs font-bold">
              <i className="fa-solid fa-plane"></i>
            </span>
            <div>
              <p className="text-xs font-bold text-slate-800">Tourist Helpline</p>
              <p className="text-[10px] text-slate-500">24x7 Multi-lingual</p>
            </div>
          </div>
          <span className="text-xs font-bold text-orange-600 group-hover:text-orange-700">1363</span>
        </a>

        <a href="tel:139" className="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group">
          <div className="flex items-center gap-3">
            <span className="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">
              <i className="fa-solid fa-train"></i>
            </span>
            <div>
              <p className="text-xs font-bold text-slate-800">Railway Helpline</p>
              <p className="text-[10px] text-slate-500">Train emergencies & info</p>
            </div>
          </div>
          <span className="text-xs font-bold text-emerald-600 group-hover:text-emerald-700">139</span>
        </a>

        <a href="tel:112" className="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group">
          <div className="flex items-center gap-3">
            <span className="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center text-xs font-bold">
              <i className="fa-solid fa-truck-medical"></i>
            </span>
            <div>
              <p className="text-xs font-bold text-slate-800">Emergency Services</p>
              <p className="text-[10px] text-slate-500">Police, Fire, Ambulance</p>
            </div>
          </div>
          <span className="text-xs font-bold text-red-600 group-hover:text-red-700">112</span>
        </a>

        <a href="tel:1800111363" className="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group">
          <div className="flex items-center gap-3">
            <span className="w-8 h-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center text-xs font-bold">
              <i className="fa-solid fa-hospital"></i>
            </span>
            <div>
              <p className="text-xs font-bold text-slate-800">Medical Helpline</p>
              <p className="text-[10px] text-slate-500">Ambulance & health info</p>
            </div>
          </div>
          <span className="text-xs font-bold text-sky-600 group-hover:text-sky-700">1800-11-1363</span>
        </a>
      </div>
    </div>
  </div>

  <script>
    // Back-to-top visibility
    (function () {
      const btn = document.getElementById('back-to-top');
      if (!btn) return;
      const toggle = () => {
        if (window.scrollY > 300) {
          btn.classList.remove('opacity-0', 'pointer-events-none');
          btn.classList.add('opacity-100');
        } else {
          btn.classList.add('opacity-0', 'pointer-events-none');
          btn.classList.remove('opacity-100');
        }
      };
      window.addEventListener('scroll', toggle, { passive: true });
      toggle();
    })();

    // Emergency Helpline Widget Toggle
    (function () {
      const toggleBtn = document.getElementById('helpline-toggle');
      const panel = document.getElementById('helpline-panel');
      const closeBtn = document.getElementById('helpline-close');
      if (!toggleBtn || !panel) return;

      const openPanel = () => {
        panel.classList.remove('opacity-0', 'pointer-events-none', 'scale-95', 'translate-y-2');
        panel.classList.add('opacity-100', 'pointer-events-auto', 'scale-100', 'translate-y-0');
      };

      const closePanel = () => {
        panel.classList.add('opacity-0', 'pointer-events-none', 'scale-95', 'translate-y-2');
        panel.classList.remove('opacity-100', 'pointer-events-auto', 'scale-100', 'translate-y-0');
      };

      toggleBtn.addEventListener('click', () => {
        if (panel.classList.contains('opacity-0')) {
          openPanel();
        } else {
          closePanel();
        }
      });

      closeBtn.addEventListener('click', closePanel);

      document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== toggleBtn) {
          closePanel();
        }
      });
    })();
  </script>

</body>
</html>