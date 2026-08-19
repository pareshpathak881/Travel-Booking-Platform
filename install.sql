DROP DATABASE IF EXISTS travel_platform;
CREATE DATABASE travel_platform
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE travel_platform;

-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255) DEFAULT 'default-user.png',
    country VARCHAR(80),
    role ENUM('user','admin') DEFAULT 'user',
    status ENUM('active','blocked') DEFAULT 'active',
    loyalty_points INT DEFAULT 0,
    email_verified BOOLEAN DEFAULT FALSE,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- PACKAGES
-- =====================================================

CREATE TABLE packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    city VARCHAR(100),
    country VARCHAR(100),
    category ENUM('Beach','Adventure','Luxury','Honeymoon','Cruise','Family') DEFAULT 'Beach',
    description TEXT,
    long_description LONGTEXT,
    highlights TEXT,
    amenities TEXT,
    hotel_name VARCHAR(150),
    duration_days INT NOT NULL,
    max_guests INT DEFAULT 2,
    price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(5,2) DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 5.0,
    review_count INT DEFAULT 0,
    availability INT DEFAULT 20,
    featured BOOLEAN DEFAULT FALSE,
    image_url VARCHAR(255) DEFAULT 'default-package.jpg',
    video_url VARCHAR(255),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- BOOKINGS
-- =====================================================

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(30) UNIQUE,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    travel_date DATE NOT NULL,
    travelers INT DEFAULT 1,
    total_amount DECIMAL(10,2),
    payment_status ENUM(
        'Pending',
        'Paid',
        'Refunded'
    ) DEFAULT 'Pending',
    booking_status ENUM(
        'Pending',
        'Confirmed',
        'Cancelled',
        'Completed'
    ) DEFAULT 'Pending',
    special_request TEXT,
    cancellation_reason TEXT,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    FOREIGN KEY (package_id)
        REFERENCES packages(package_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- PAYMENTS
-- =====================================================

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM(
        'Card',
        'UPI',
        'Net Banking',
        'Wallet',
        'Cash'
    ),
    transaction_id VARCHAR(100),
    payment_status ENUM(
        'Pending',
        'Success',
        'Failed',
        'Refunded'
    ) DEFAULT 'Pending',
    paid_at DATETIME,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- REVIEWS
-- =====================================================

CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    FOREIGN KEY (package_id)
        REFERENCES packages(package_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- WISHLIST
-- =====================================================

CREATE TABLE wishlist (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(user_id, package_id),

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    FOREIGN KEY (package_id)
        REFERENCES packages(package_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- GALLERY
-- =====================================================

CREATE TABLE gallery (
    gallery_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 1,

    FOREIGN KEY (package_id)
        REFERENCES packages(package_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- COUPONS
-- =====================================================

CREATE TABLE coupons (
    coupon_id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_code VARCHAR(30) UNIQUE,
    discount DECIMAL(5,2),
    expiry_date DATE,
    max_usage INT DEFAULT 100,
    used_count INT DEFAULT 0
) ENGINE=InnoDB;

-- =====================================================
-- CONTACT MESSAGES
-- =====================================================

CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150),
    subject VARCHAR(200),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- BOOKING LOGS
-- =====================================================

CREATE TABLE booking_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE CASCADE,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- LOGIN ATTEMPTS (rate-limiting: IP + email dual-key)
--
-- Thresholds enforced in login.php:
--   • Per-email: 5 failures within 15 minutes → user locked out
--   • Per-IP   : 20 failures within 15 minutes → IP locked out
-- =====================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT       UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    email        VARCHAR(150) NOT NULL,
    attempt_time TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time  (email,      attempt_time),
    INDEX idx_ip_time     (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;