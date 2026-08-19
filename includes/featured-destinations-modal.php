<?php
declare(strict_types=1);

// Modal HTML shell for featured destination browsing.
// React will populate it via window.__FEATURED_DESTINATION_MODAL__.
?>

<div id="featuredDestinationModal" className="booking-modal" aria-hidden="true" style="display:none;">
  <div className="modal-overlay" data-modal-close="true"></div>
  <div className="modal-content" role="dialog" aria-modal="true" aria-labelledby="featuredModalTitle">

    <button className="modal-close" type="button" aria-label="Close" data-modal-close="true">×</button>

    <div className="modal-header">
      <h2 id="featuredModalTitle">Featured Destination</h2>
      <p id="featuredModalSubtitle" className="text-sm text-slate-500 mt-2"></p>
    </div>

    <div className="modal-body" style="gap:1.25rem;">

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="rounded-2xl overflow-hidden border border-slate-200 bg-slate-50">
          <img id="featuredModalHero" src="" alt="" className="w-full h-64 object-cover" />
        </div>

        <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white p-4">
          <h3 className="text-sm font-extrabold uppercase tracking-wider text-orange-500 mb-2">Description</h3>
          <p id="featuredModalDescription" className="text-sm text-slate-600 leading-relaxed"></p>

          <div className="mt-4">
            <h3 className="text-sm font-extrabold uppercase tracking-wider text-orange-500 mb-2">Verified Map</h3>
            <div id="featuredModalMap" className="w-full rounded-xl overflow-hidden border border-slate-100 bg-slate-50"></div>
          </div>
        </div>
      </div>

      <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white">
        <h3 className="px-5 pt-5 text-sm font-extrabold uppercase tracking-wider text-orange-500">YouTube</h3>
        <div className="p-5">
          <div id="featuredModalYoutube" className="relative w-full" style="padding-top:56.25%"></div>
        </div>
      </div>

      <div className="rounded-2xl overflow-hidden border border-slate-200 bg-white">
        <h3 className="px-5 pt-5 text-sm font-extrabold uppercase tracking-wider text-orange-500">Gallery</h3>
        <div id="featuredModalGallery" className="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
      </div>

    </div>

  </div>
</div>

