-- Featured Destinations module schema + seed
-- MySQL 8.x | InnoDB | utf8mb4

USE travel_db;

CREATE TABLE IF NOT EXISTS featured_destinations (
  destination_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  hero_image_url TEXT NOT NULL,
  image_url TEXT NOT NULL,
  map_embed_url TEXT NULL,
  state VARCHAR(120) NULL,
  youtube_url TEXT NULL,
  gallery_images JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_featured_active_sort (is_active, sort_order),
  INDEX idx_featured_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 5 vibrant featured destinations
INSERT INTO featured_destinations (
  name, description, hero_image_url, image_url, map_embed_url, state, youtube_url, gallery_images, is_active, sort_order
) VALUES
  (
    'Pangong Lake, Ladakh',
    'High-altitude Pangong Tso is famous for its surreal blue hues, wide shorelines, and stargazing nights—an unmissable Himalayan adventure.',
    'assets/images/Pangong Lake, Ladakh, India.jpg',
    'assets/images/Pangong Lake, Ladakh, India.jpg',
    'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d848560.6162977536!2d78.06420649524433!3d33.820314563977526!2m3!1f0!2f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39002d69b6082a97%3A0xb7ba17e3c8c016a9!2sPangong%20Tso!5e0!3m2!1sen!2sin!4v1783767259110!5m2!1sen!2sin',
    'Ladakh',
    'https://youtu.be/ZFLsBXFK3Pc?si=7QE6eC1YYSJ7YkVp',
    JSON_ARRAY(
      'assets/images/Road TripPangong Lake Leh gallery.jpg',
      'assets/images/Starry night at Pangong Tso, Ladakh.jpg',
      'assets/images/Sunrise at Pangong Tso, Ladakh, India..jpg',
      'assets/images/Turquoise Shore Pangong Lake.jpg',
      'assets/images/Night Sky kutch.jpg'
    ),
    1,
    1
  ),
  (
    'Hampi, Karnataka',
    'Hampi is a living heritage landscape—ancient ruins, boulder climbs, sunset viewpoints, and effortless day-by-day exploration.',
    'assets/images/🖼 Heroh hampi.jpg',
    'assets/images/🖼 Heroh hampi.jpg',
    'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d61565.008722100654!2d76.40993215335806!3d15.332391922653565!2m3!1f0!2f0!3f0!2f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bb77fd95d4be823%3A0x6e52e05076df36b8!2sHampi%2C%20Karnataka!5e0!3m2!1sen!2sin!4v1783795755007!5m2!1sen!2sin',
    'Karnataka',
    'https://youtu.be/0Vs_8efAuFc?si=mxXblLSJK5wfjZ6O',
    JSON_ARRAY(
      'assets/images/Ancient Ruins hampi.jpg',
      'assets/images/Hampi river.jpg',
      'assets/images/Matanga Hill View hampi.jpg',
      'assets/images/Stone Chariot hampi.jpg',
      'assets/images/Sunrise (Virupaksha Temple).jpg'
    ),
    1,
    2
  ),
  (
    'Swaraj Dweep (Havelock Island), Andaman & Nicobar',
    'Tropical beaches, crystal-clear waters, and marine life make Swaraj Dweep a perfect blend of relaxation and adventure.',
    'assets/images/Swaraj Dweep (Havelock Island)🖼 Hero.jpg',
    'assets/images/Swaraj Dweep (Havelock Island)🖼 Hero.jpg',
    'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d249800.25634098303!2d92.82465480415333!3d11.965569024403823!2m3!1f0!2f0!3f0!2f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3088d3d85e0fe039%3A0x25c8aaaa513ef4bf!2sSwaraj%20Dweep!5e0!3m2!1sen!2sin!4v1783796719463!5m2!1sen!2sin',
    'Andaman & Nicobar',
    'https://youtu.be/9WBzVC66vhA?si=lwHO15-0JpBcTUqE',
    JSON_ARRAY(
      'assets/images/Pristine waters at Radha Nagar Beach swaraj dweep .jpg',
      'assets/images/Palm Trees: sawraj island.jpg',
      'assets/images/Coral Reef: sawraj island.jpg',
      'assets/images/Scuba Diving: sawraj island.jpg',
      'assets/images/Sunset swaraj island.jpg'
    ),
    1,
    3
  ),
  (
    'Great Rann of Kutch, Gujarat',
    'Witness the vast salt desert magic—camel safaris, flamingo skies, and night heavens that feel impossibly close.',
    'assets/images/Great Rann of Kutch (Gujarat)🖼 Hero.jpg',
    'assets/images/Great Rann of Kutch (Gujarat)🖼 Hero.jpg',
    'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3730081.8930721353!2d67.32603571769242!3d24.078341807172094!2m3!1f0!2f0!3f0!2f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39503cafffa1d8e3%3A0xfdd6e540c59eb48e!2sRann%20of%20Kutch!5e0!3m2!1sen!2sin!4v1783797975856!5m2!1sen!2sin',
    'Gujarat',
    'https://youtu.be/6EjCQEIVdHk?si=QiKQSEO7Zx1F143B',
    JSON_ARRAY(
      'assets/images/kutch White Salt Desert.jpg',
      'assets/images/Camel Safari kutch.jpg',
      'assets/images/Flamingos kutch.jpg',
      'assets/images/Night Sky kutch.jpg',
      'assets/images/sunset kutch.jpg'
    ),
    1,
    4
  ),
  (
    'Munnar, Kerala',
    'Munnar’s misty tea valleys and mist-layered viewpoints deliver a calming nature escape with memorable road trips.',
    'https://images.unsplash.com/photo-1602216055903-aa1dd6d3a8a?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1602216055903-aa1dd6d3a8a?auto=format&fit=crop&w=1600&q=80',
    'https://www.google.com/maps?q=Munnar&output=embed',
    'Kerala',
    'https://youtu.be/rWRgE2l6JrQ',
    JSON_ARRAY(
      'assets/images/arun-prakash-m0xNBfWSI3Q-unsplash.jpg',
      'assets/images/beach-waves.mp4'
    ),
    1,
    5
  )
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  hero_image_url = VALUES(hero_image_url),
  image_url = VALUES(image_url),
  map_embed_url = VALUES(map_embed_url),
  youtube_url = VALUES(youtube_url),
  gallery_images = VALUES(gallery_images),
  state = VALUES(state),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

