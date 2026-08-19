<section class="package-hero">

<?php if(!empty($package['video_url'])): ?>

<video
class="hero-video"
autoplay
muted
loop
playsinline>

<source
src="<?= htmlspecialchars($package['video_url']) ?>"
type="video/mp4">

</video>

<?php else: ?>

<div
class="hero-image"
style="
background-image:
url('<?= htmlspecialchars($package['image_url']) ?>');
">

</div>

<?php endif; ?>

<div class="hero-overlay"></div>

<div class="container hero-content">

<span class="package-type">

<?= ucfirst($package['type']) ?>

Package

</span>

<h1>

<?= htmlspecialchars($package['title']) ?>

</h1>

<div class="hero-meta">

<div>

📍

<?= htmlspecialchars($package['destination']) ?>

</div>

<div>

⭐

4.9 (243 Reviews)

</div>

<div>

🕒

<?= $package['duration_days'] ?>

Days

</div>

</div>

<div class="hero-price">

$

<?= number_format($package['price']) ?>

<span>

/ person

</span>

</div>

<a
href="#booking"
class="button primary">

Book Now

</a>

</div>

<button class="sound-toggle">

🔇

</button>

</section>