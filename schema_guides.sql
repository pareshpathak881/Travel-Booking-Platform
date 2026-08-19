CREATE TABLE IF NOT EXISTS guides (
    guide_id          INT AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(150)                              NOT NULL,
    destination       VARCHAR(100)                              NOT NULL,
    state             VARCHAR(100)                              NOT NULL,
    content           TEXT                                      NOT NULL,
    image_url         TEXT                                      DEFAULT NULL,
    best_time_to_visit VARCHAR(100)                             DEFAULT NULL,
    created_at        TIMESTAMP                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_guide_destination (destination, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO guides (title, destination, state, content, image_url, best_time_to_visit)
VALUES
    (
        'Ladakh: The Land of High Passes',
        'Ladakh',
        'Jammu & Kashmir',
        'Ladakh is a region like no other — stark, surreal, and spiritually charged. Start in Leh at 3,500m, acclimatize for 48 hours, then chase the turquoise mirror of Pangong Tso, drive over Khardung La, and camp under stars so bright they cast shadows. Carry warm layers even in July; the wind cuts through everything.',
        'https://images.unsplash.com/photo-1544735716-392fe2489ffa?auto=format&fit=crop&w=800&q=80',
        'May – September'
    ),
    (
        'Kerala: God''s Own Country',
        'Kerala',
        'Kerala',
        'Cruise the backwaters on a traditional Kettuvallam houseboat, wake up to the aroma of filter coffee, and watch Kathakali dancers tell ancient stories through expressive eye movements. Don''t miss the spice trails of Thekkady, the tea gardens of Munnar, and the sunset chai on Kovalam Beach.',
        'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?auto=format&fit=crop&w=800&q=80',
        'September – March'
    ),
    (
        'Rajasthan: The Royal Desert Trail',
        'Jaipur',
        'Rajasthan',
        'Walk through the pink city of Jaipur, then ride camels at sunset over the golden dunes of Jaisalmer. Stay in heritage havelis, feast on dal baati churma under starlit skies, and shop for block-printed textiles in the narrow lanes of Jodhpur. Rajasthan is India''s living museum of kings and craftsmen.',
        'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80',
        'October – March'
    ),
    (
        'Goa: Sun, Sand & Seafood',
        'Goa',
        'Goa',
        'Beyond the beach parties, Goa is a slow-discovery state. Explore Portuguese-era churches in Old Goa, kayak through the mangroves of Sal, eat fiery fish curry rice at a beach shack in Agonda, and end every evening with a walk on Baga or Palolem as the Arabian Sea turns gold.',
        'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?auto=format&fit=crop&w=800&q=80',
        'November – February'
    ),
    (
        'Meghalaya: Where Rivers Flow Above Ground',
        'Meghalaya',
        'Meghalaya',
        'Home to the wettest place on Earth — Mawsynram — Meghalaya surprises with its living root bridges, crystal-clear Dawki river, and the cleanest village in Asia, Mawlynnong. Trek through dense forests, explore limestone caves, and let the sound of waterfalls be your soundtrack.',
        'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
        'September – November'
    );
