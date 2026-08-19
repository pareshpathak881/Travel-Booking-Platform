-- ============================================================
-- IndiaYatra Travel Booking Platform - Database Schema
-- MySQL 8.0 | InnoDB | utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS travel_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE travel_platform;

-- Drop dependent tables first to avoid FK constraints
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS users;

-- ============================================================
-- Ensure production-safe tables exist (idempotent)
-- ============================================================


-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)                    NOT NULL,
    email          VARCHAR(100)                    NOT NULL UNIQUE,
    password       VARCHAR(255)                    NOT NULL,
    role           ENUM('customer', 'admin')       NOT NULL DEFAULT 'customer',
    booking_count  INT                             NOT NULL DEFAULT 0,
    total_spent    DECIMAL(10, 2)                  NOT NULL DEFAULT 0.00,
    badge_flags    VARCHAR(255)                    NOT NULL DEFAULT '',
    loyalty_points INT                             NOT NULL DEFAULT 0,
    user_level     INT                             NOT NULL DEFAULT 1,
    created_at     TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- PACKAGES (Unified Inventory System) TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS packages (
    package_id             INT AUTO_INCREMENT PRIMARY KEY,
    title                  VARCHAR(150)                                              NOT NULL,
    vertical_type          ENUM('flight', 'hotel', 'package')                        NOT NULL DEFAULT 'package',
    airline_name           VARCHAR(100)                                              NULL,
    room_tier              VARCHAR(50)                                               NULL,
    departure_time         DATETIME                                                  NULL,
    arrival_time           DATETIME                                                  NULL,
    zone                   ENUM('North', 'South', 'East', 'West', 'North-East', 'Central') NOT NULL,
    state                  VARCHAR(100)                                              NOT NULL,
    description            TEXT                                                      NOT NULL,
    highlights             TEXT                                                      DEFAULT NULL,
    base_price             DECIMAL(10, 2)                                            NOT NULL,
    availability           INT                                                       NOT NULL DEFAULT 0,
    letter_grade           ENUM('A+', 'A', 'B', 'C', 'D', 'F')                       NOT NULL DEFAULT 'A',
    map_coordinates        TEXT                                                      NULL,
    comp_price_makemytrip  DECIMAL(10, 2)                                            NULL,
    comp_price_yatra       DECIMAL(10, 2)                                            NULL,
    image_url              TEXT                                                      DEFAULT NULL,
    duration_days          INT                                                       NOT NULL DEFAULT 1,
    created_at             TIMESTAMP                                                 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pkg_filters (zone, state, base_price, vertical_type),
    CHECK (base_price > 0),
    CHECK (availability >= 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- BOOKINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    booking_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT                                      NOT NULL,
    package_id    INT                                      NOT NULL,
    seats_booked  INT                                      NOT NULL DEFAULT 1,
    base_total    DECIMAL(10, 2)                           NOT NULL,
    total_gst     DECIMAL(10, 2)                           NOT NULL,
    final_payable DECIMAL(10, 2)                           NOT NULL,
    status        ENUM('Pending', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    booking_date  TIMESTAMP                                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_booking_user    (user_id),
    INDEX idx_booking_package (package_id),
    INDEX idx_booking_status  (status),
    CHECK (seats_booked >= 1),
    CHECK (base_total >= 0),
    CHECK (total_gst >= 0),
    CHECK (final_payable >= 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- WISHLISTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS wishlists (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    package_id  INT NOT NULL,
    FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_user_package (user_id, package_id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- LOGIN ATTEMPTS TABLE (rate-limiting: IP + email dual-key)
--
-- Thresholds enforced in login.php:
--   • Per-email: 5 failures within 15 minutes → user locked out
--   • Per-IP   : 20 failures within 15 minutes → IP locked out
--     (higher IP threshold avoids shared-NAT / campus-wifi collateral lockout)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT         UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)    NOT NULL,
    email        VARCHAR(150)   NOT NULL,
    attempt_time TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time      (email,      attempt_time),
    INDEX idx_ip_time         (ip_address, attempt_time)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA - Users
-- Passwords: password_hash('password123', PASSWORD_DEFAULT)
-- Admin:    admin@travel.com  / password123
-- Customer: customer@travel.com / password123
-- ============================================================
INSERT INTO users (name, email, password, role, booking_count, total_spent, badge_flags, loyalty_points, user_level)
VALUES
    ('Paresh', 'admin@travel.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'admin', 0, 0.00, '', 0, 1),
    ('Raj Sharma', 'customer@travel.com',
     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'customer', 0, 0.00, '', 0, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- SEED DATA - Packages
-- ============================================================
INSERT INTO packages (title, vertical_type, airline_name, room_tier, departure_time, arrival_time, zone, state, description, highlights, base_price, availability, letter_grade, map_coordinates, comp_price_makemytrip, comp_price_yatra, image_url, duration_days)
VALUES
    (
        'IndiGo Flight Mumbai-Delhi',
        'flight',
        'IndiGo',
        NULL,
        '2026-10-15 08:30:00',
        '2026-10-15 10:45:00',
        'West',
        'Maharashtra',
        'Direct non-stop flight from Chhatrapati Shivaji Maharaj International Airport (BOM), Mumbai to Indira Gandhi International Airport (DEL), Delhi. Free high-speed check-in, water onboard, and standard baggage allowance included.',
        'Non-stop flight|Standard 15kg baggage|Meal pre-booking available|A320 Neo Fleet|Terminal 2 departure',
        5200.00,
        180,
        'A',
        'https://maps.google.com/maps?q=Mumbai%20Airport&t=&z=13&ie=UTF8&iwloc=&output=embed',
        5500.00,
        5400.00,
        'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=800&q=80',
        1
    ),
    (
        'Taj Exotica Goa Deluxe Suite',
        'hotel',
        NULL,
        'Deluxe Suite',
        NULL,
        NULL,
        'West',
        'Goa',
        'Escape to the Mediterranean-inspired resort Taj Exotica, sprawling over 56 acres of lush gardens along Benaulim beach in South Goa. Indulge in culinary delights, pristine ocean views, and dynamic watersports right at your doorstep.',
        'Private beach access|Free breakfast & high-speed WiFi|Infinity pool access|World-class Jiva Spa|24/7 Butler service',
        18500.00,
        45,
        'A+',
        'https://maps.google.com/maps?q=Taj%20Exotica%20Goa&t=&z=13&ie=UTF8&iwloc=&output=embed',
        19800.00,
        19200.00,
        'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
        1
    ),
    (
        'Meghalaya Living Root Bridges Explorer Bundle',
        'package',
        NULL,
        NULL,
        '2026-11-01 07:00:00',
        '2026-11-07 18:00:00',
        'North-East',
        'Meghalaya',
        'Trek to the famous double-decker living root bridges of Nongriat. Traverse pristine streams, explore limestone caves, and witness seasonal waterfalls of Cherrapunji in this award-winning eco-tourism adventure package.',
        'Double-decker root bridge trek|Mawsmai caves guide| Homestays with Khasi tribe|Boat ride in crystal clear Dawki river|Shillong Peak sunset view',
        24000.00,
        15,
        'A',
        'https://maps.google.com/maps?q=Nongriat%20Root%20Bridge&t=&z=13&ie=UTF8&iwloc=&output=embed',
        25500.00,
        24800.00,
        'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
        7
    ),
    (
        'Air India Flight Bengaluru-Kolkata',
        'flight',
        'Air India',
        NULL,
        '2026-10-18 14:15:00',
        '2026-10-18 16:50:00',
        'South',
        'Karnataka',
        'Comfortable and premium domestic flight from Kempegowda International Airport (BLR) to Netaji Subhash Chandra Bose International Airport (CCU). Full hot meal service and generous baggage allowance of 25kg included.',
        'Hot meals served onboard|25kg check-in baggage|Boeing Dreamliner experience|Extra legroom options|In-flight entertainment',
        6800.00,
        120,
        'B',
        'https://maps.google.com/maps?q=Kempegowda%20Airport&t=&z=13&ie=UTF8&iwloc=&output=embed',
        7200.00,
        7100.00,
        'https://images.unsplash.com/photo-1517999144091-3d9dca6d1e43?auto=format&fit=crop&w=800&q=80',
        1
    ),
    (
        'The Oberoi Amarvilas Agra Executive Suite',
        'hotel',
        NULL,
        'Executive Suite',
        NULL,
        NULL,
        'North',
        'Uttar Pradesh',
        'Wake up to uninterrupted, breathtaking views of the iconic Taj Mahal from your private balcony at The Oberoi Amarvilas. Relish our signature Mughal dinners, wellness treatments, and unmatched luxury hospitality.',
        'Direct uninterrupted Taj Mahal views|Private open-air balcony|Mughal fine dining experience|Ayurvedic spa treatments|Complimentary Golf Cart transfers',
        35000.00,
        25,
        'A+',
        'https://maps.google.com/maps?q=The%20Oberoi%20Amarvilas&t=&z=13&ie=UTF8&iwloc=&output=embed',
        37500.00,
        36800.00,
        'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=800&q=80',
        1
    ),
    (
        'Kerala Houseboat & Backwater Leisure Tour',
        'package',
        NULL,
        NULL,
        '2026-12-10 10:00:00',
        '2026-12-14 16:00:00',
        'South',
        'Kerala',
        'Immerse yourself in Keralas backwaters aboard a luxury traditional Kettuvallam houseboat. Cruise quiet coconut-fringed lagoons, visit spice markets, and enjoy delicious local fish delicacies prepared live by your personal onboard chef.',
        'Overnight houseboat cruise|Spice garden guided walk|Kathakali dance performance|Live seafood preparations|Kovalam Beach sunset drive',
        16999.00,
        30,
        'A',
        'https://maps.google.com/maps?q=Alleppey%20Backwaters&t=&z=13&ie=UTF8&iwloc=&output=embed',
        18000.00,
        17500.00,
        'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?auto=format&fit=crop&w=800&q=80',
        4
    );

-- ============================================================
-- SCHEDULES TABLE (Multi-Modal Transit Engine)
-- ============================================================
CREATE TABLE IF NOT EXISTS schedules (
    schedule_id       INT AUTO_INCREMENT PRIMARY KEY,
    transit_type      ENUM('flight', 'train', 'bus')                 NOT NULL,
    carrier_name      VARCHAR(100)                                    NOT NULL,
    origin_city       VARCHAR(100)                                    NOT NULL,
    destination_city  VARCHAR(100)                                    NOT NULL,
    departure_time    TIME                                            NOT NULL,
    arrival_time      TIME                                            NOT NULL,
    duration_mins     INT                                             NOT NULL,
    running_days      VARCHAR(50)  DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
    pnr_tracker_code  VARCHAR(20)                                     NULL,
    fare_price        DECIMAL(10, 2)                                 NOT NULL,
    created_at        TIMESTAMP                                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_schedule_route (origin_city, destination_city, transit_type),
    INDEX idx_schedule_transit (transit_type),
    CHECK (fare_price > 0),
    CHECK (duration_mins > 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA - Schedules (Multi-Modal Transit)
-- ============================================================
INSERT INTO schedules (transit_type, carrier_name, origin_city, destination_city, departure_time, arrival_time, duration_mins, running_days, pnr_tracker_code, fare_price)
VALUES
    -- Flights (8 schedules)
    ('flight', 'IndiGo 6E-204', 'Mumbai', 'Delhi', '08:30:00', '10:45:00', 135, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'IND6E204', 5200.00),
    ('flight', 'Air India AI-821', 'Delhi', 'Mumbai', '14:15:00', '16:30:00', 135, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'INDAI821', 5800.00),
    ('flight', 'Vistara UK-945', 'Bangalore', 'Delhi', '06:00:00', '09:15:00', 195, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'VSTUK945', 7500.00),
    ('flight', 'IndiGo 6E-3307', 'Kolkata', 'Bagdogra', '11:20:00', '13:05:00', 105, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'IND6E3307', 3200.00),
    ('flight', 'SpiceJet SG-148', 'Chennai', 'Kochi', '07:45:00', '09:20:00', 95, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'SPJSG148', 2800.00),
    ('flight', 'Air India AI-440', 'Delhi', 'Agra', '09:00:00', '10:00:00', 60, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'INDAI440', 4500.00),
    ('flight', 'IndiGo 6E-601', 'Mumbai', 'Goa', '16:00:00', '17:30:00', 90, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'IND6E601', 3900.00),
    ('flight', 'Vistara UK-803', 'Hyderabad', 'Chennai', '13:30:00', '14:45:00', 75, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'VSTUK803', 2600.00),

    -- Trains (5 schedules)
    ('train', 'Vande Bharat Express 22436', 'Delhi', 'Agra', '06:00:00', '08:30:00', 150, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'VB22436', 1800.00),
    ('train', 'Rajdhani Express 12301', 'Kolkata', 'Delhi', '16:45:00', '09:35:00', 965, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'RAJ12301', 2200.00),
    ('train', 'Vande Bharat Express 20667', 'Bangalore', 'Kochi', '06:30:00', '13:45:00', 435, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'VB20667', 1500.00),
    ('train', 'Shatabdi Express 12019', 'Chennai', 'Bangalore', '06:00:00', '10:30:00', 270, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'SHT12019', 1200.00),
    ('train', 'Duronto Express 12259', 'Mumbai', 'Delhi', '23:00:00', '11:15:00', 735, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'DUR12259', 1850.00),

    -- Buses (5 schedules)
    ('bus', 'Zingbus Volvo AC - Route MB-102', 'Mumbai', 'Pune', '08:00:00', '11:30:00', 210, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'ZMB102', 850.00),
    ('bus', 'VRL Travels Sleeper - Route BL-205', 'Bangalore', 'Hyderabad', '21:00:00', '08:30:00', 450, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'VBL205', 1200.00),
    ('bus', 'KSRTC A/C Seater - Route KL-308', 'Kochi', 'Munnar', '07:00:00', '11:00:00', 240, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'KKL308', 450.00),
    ('bus', 'RTC Express - Route DJ-412', 'Delhi', 'Jaipur', '09:00:00', '13:30:00', 270, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'RTDJ412', 600.00),
    ('bus', 'Sharma Transports - Route GU-515', 'Ahmedabad', 'Udaipur', '22:00:00', '06:30:00', 510, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'STGU515', 950.00);
