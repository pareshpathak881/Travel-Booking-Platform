<?php
declare(strict_types=1);

// Featured destinations module (non-database, uses local assets + externally provided maps/videos).
// Output is HTML only (front-end).

function iy_escape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$modules = [
    [
        'key' => 'pangong',
        'title' => 'Pangong Lake, Ladakh',
        'youtube' => 'https://youtu.be/ZFLsBXFK3Pc?si=7QE6eC1YYSJ7YkVp',
        'maps_iframe' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d848560.6162977536!2d78.06420649524433!3d33.820314563977526!2m3!1f0!2f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39002d69b6082a97%3A0xb7ba17e3c8c016a9!2sPangong%20Tso!5e0!3m2!1sen!2sin!4v1783767259110!5m2!1sen!2sin',
        'hero_img' => 'assets/images/Pangong Lake, Ladakh, India.jpg',
        'gallery' => [
            'assets/images/Road TripPangong Lake Leh gallery.jpg',
            'assets/images/Starry night at Pangong Tso, Ladakh.jpg',
            'assets/images/Sunrise at Pangong Tso, Ladakh, India..jpg',
            'assets/images/Turquoise Shore Pangong Lake.jpg',
            'assets/images/Night Sky kutch.jpg' // fallback-themed; if missing it will visually degrade
        ],
        'months' => 'Best Months: May–September',
        'activities' => ['Magnetic stargazing nights', 'Spectacular lake viewpoints', 'Scenic road trip vibes'],
        'nearby' => ['Spangmik Lake', 'Thiksey Monastery'],
        'tips' => ['Carry warm layers', 'Start early for sunrise views', 'Keep cash for small markets'],
    ],
    [
        'key' => 'hampi',
        'title' => 'Hampi, Karnataka',
        'youtube' => 'https://youtu.be/0Vs_8efAuFc?si=mxXblLSJK5wfjZ6O',
        'maps_iframe' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d61565.008722100654!2d76.40993215335806!3d15.332391922653565!2m3!1f0!2f0!3f0!2f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bb77fd95d4be823%3A0x6e52e05076df36b8!2sHampi%2C%20Karnataka!5e0!3m2!1sen!2sin!4v1783795755007!5m2!1sen!2sin',
        'hero_img' => 'assets/images/🖼 Heroh hampi.jpg',
        'gallery' => [
            'assets/images/Ancient Ruins hampi.jpg',
            'assets/images/Hampi river.jpg',
            'assets/images/Matanga Hill View hampi.jpg',
            'assets/images/Stone Chariot hampi.jpg',
            'assets/images/Sunrise (Virupaksha Temple).jpg'
        ],
        'months' => 'Best Months: October–March',
        'activities' => ['Boulder hikes & viewpoints', 'Historic architecture walk', 'Sunrise temple exploration'],
        'nearby' => ['Virupaksha Temple', 'Matanga Hill'],
        'tips' => ['Wear comfortable shoes', 'Carry water bottles', 'Respect heritage areas'],
    ],
    [
        'key' => 'swaraj-dweep',
        'title' => 'Swaraj Dweep (Havelock Island), Andaman & Nicobar',
        'youtube' => 'https://youtu.be/9WBzVC66vhA?si=lwHO15-0JpBcTUqE',
        'maps_iframe' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d249800.25634098303!2d92.82465480415333!3d11.965569024403823!2m3!1f0!2f0!3f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3088d3d85e0fe039%3A0x25c8aaaa513ef4bf!2sSwaraj%20Dweep!5e0!3m2!1sen!2sin!4v1783796719463!5m2!1sen!2sin',
        'hero_img' => 'assets/images/Swaraj Dweep (Havelock Island)🖼 Hero.jpg',
        'gallery' => [
            'assets/images/Pristine waters at Radha Nagar Beach swaraj dweep .jpg',
            'assets/images/Palm Trees: sawraj island.jpg',
            'assets/images/Coral Reef: sawraj island.jpg',
            'assets/images/Scuba Diving: sawraj island.jpg',
            'assets/images/Sunset swaraj island.jpg'
        ],
        'months' => 'Best Months: November–March',
        'activities' => ['Coral reef snorkelling', 'Beach sunset walks', 'Island hopping'],
        'nearby' => ['Radha Nagar Beach', 'Elephant Beach'],
        'tips' => ['Use reef-safe sunscreen', 'Stay hydrated', 'Book early for tours'],
    ],
    [
        'key' => 'rann-kutch',
        'title' => 'Great Rann of Kutch, Gujarat',
        'youtube' => 'https://youtu.be/6EjCQEIVdHk?si=QiKQSEO7Zx1F143B',
        'maps_iframe' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3730081.8930721353!2d67.32603571769242!3d24.078341807172094!2m3!1f0!2f0!3f0!2f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39503cafffa1d8e3%3A0xfdd6e540c59eb48e!2sRann%20of%20Kutch!5e0!3m2!1sen!2sin!4v1783797975856!5m2!1sen!2sin',
        'hero_img' => 'assets/images/Great Rann of Kutch (Gujarat)🖼 Hero.jpg',
        'gallery' => [
            'assets/images/kutch White Salt Desert.jpg',
            'assets/images/Camel Safari kutch.jpg',
            'assets/images/Flamingos kutch.jpg',
            'assets/images/Night Sky kutch.jpg',
            'assets/images/sunset kutch.jpg'
        ],
        'months' => 'Best Months: October–February',
        'activities' => ['White desert photography', 'Camel safaris', 'Night sky stargazing'],
        'nearby' => ['Kalo Dungar', 'Aina Mahal'],
        'tips' => ['Carry windproof layers', 'Use breathable footwear', 'Avoid midday exposure'],
    ],
    [
        'key' => 'munnar',
        'title' => 'Munnar, Kerala',
        'youtube' => 'https://youtu.be/rWRgE2l6JrQ',
        'maps_iframe' => 'https://www.google.com/maps?q=Munnar&output=embed',
        'hero_img' => 'assets/images/Coral Reef: sawraj island.jpg',
        'gallery' => [
            'assets/images/arun-prakash-m0xNBfWSI3Q-unsplash.jpg',
            'assets/images/beach-waves.mp4'
        ],
        'months' => 'Best Months: July–March',
        'activities' => ['Tea estate drives', 'Mist valley viewpoints', 'Nature walks'],
        'nearby' => ['Eravikulam National Park', 'Mattupetty Dam'],
        'tips' => ['Carry a light rain jacket', 'Start hikes early', 'Respect wildlife areas'],
    ],
];

// Render as cards above the marketplace React root.
?>

<section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
  <div className="flex items-start justify-between gap-4 flex-col md:flex-row md:items-end">
    <div>
      <div className="inline-flex items-center gap-2 bg-orange-500/10 border border-orange-500/30 text-orange-500 px-3 py-1 rounded-full text-xs font-bold">
        ⭐ Featured Destinations
      </div>
      <h2 className="mt-3 font-display text-2xl sm:text-3xl font-extrabold text-slate-900">
        Plan your next epic journey
      </h2>
      <p className="mt-2 text-sm text-slate-500 max-w-2xl">
        Cinematic maps, curated activities, and verified media — built for fast discovery.
      </p>
    </div>
    <a href="index.php" className="text-sm font-bold text-orange-600 hover:text-orange-700">
      Browse Marketplace &rarr;
    </a>
  </div>

  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
    <?php foreach ($modules as $m): ?>
      <article className="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
        <div className="relative">
          <img
            src="<?= iy_escape($m['hero_img']) ?>"
            alt="<?= iy_escape($m['title']) ?>"
            className="w-full h-56 sm:h-72 object-cover"
            loading="lazy"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" />
          <div className="absolute top-4 left-4 flex flex-wrap gap-2">
            <span className="bg-slate-900/80 text-white text-[11px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-lg">
              Verified
            </span>
            <span className="bg-orange-500 text-white text-[11px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-lg">
              <?= iy_escape($m['months']) ?>
            </span>
          </div>
        </div>

        <div className="p-6">
          <h3 className="font-display text-xl font-extrabold text-slate-900">
            <?= iy_escape($m['title']) ?>
          </h3>

          <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-slate-50">
              <iframe
                src="<?= iy_escape($m['maps_iframe']) ?>"
                width="100%"
                height="220"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                title="Map - <?= iy_escape($m['title']) ?>"
              ></iframe>
            </div>
            <div className="rounded-2xl overflow-hidden border border-slate-200 bg-slate-50">
              <div className="relative w-full" style="padding-top:56.25%">
                <iframe
                  className="absolute top-0 left-0 w-full h-full"
                  src="<?= iy_escape($m['youtube']) ?>"
                  title="YouTube - <?= iy_escape($m['title']) ?>"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowfullscreen
                  loading="lazy"
                ></iframe>
              </div>
            </div>
          </div>

          <div className="mt-5">
            <h4 className="text-sm font-bold text-slate-800">Activities</h4>
            <ul className="mt-2 flex flex-wrap gap-2">
              <?php foreach ($m['activities'] as $a): ?>
                <li className="bg-orange-50 text-orange-800 text-xs font-bold px-3 py-1 rounded-full border border-orange-200">
                  <?= iy_escape($a) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <h4 className="text-sm font-bold text-slate-800">Nearby Attractions</h4>
              <ul className="mt-2 text-xs text-slate-600 space-y-1">
                <?php foreach ($m['nearby'] as $n): ?>
                  <li className="flex items-center gap-2"><span className="text-orange-500">•</span><?= iy_escape($n) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div>
              <h4 className="text-sm font-bold text-slate-800">Travel Tips</h4>
              <ul className="mt-2 text-xs text-slate-600 space-y-1">
                <?php foreach ($m['tips'] as $t): ?>
                  <li className="flex items-center gap-2"><span className="text-orange-500">✓</span><?= iy_escape($t) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <div className="mt-6 flex items-center justify-end">
            <a href="#marketplace-root" className="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-3 rounded-xl transition-colors">
              Explore Now <i className="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

