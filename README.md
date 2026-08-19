# IndiaYatra - Travel Booking Platform

A full-stack PHP/MySQL travel booking platform with React 18 frontend, supporting flights, hotels, packages, trains, and buses across India.

## Features

- Multi-modal transit booking (Flights, Trains, Buses)
- Live schedule tracking with mock PNR status
- React 18 + Tailwind CSS frontend
- Gamified booking system with loyalty points and badges
- Interactive travel guides and how-to knowledge base
- Emergency helpline widget
- Admin dashboard for package management
- Wishlist and booking management

## Tech Stack

- **Backend**: PHP 8.x with PDO
- **Database**: MySQL 8.x (InnoDB)
- **Frontend**: React 18, Tailwind CSS, Babel Standalone
- **Build**: Vite

## Database Setup

1. Import `schema.sql` into MySQL
2. Update `.env` with your database credentials
3. Seed data includes 34+ packages, 18 transit schedules, and 5 travel guides

## Demo Credentials

- **Admin**: admin@travel.com / password123
- **Customer**: customer@travel.com / password123

## Pages

- `index.php` - Marketplace with live filters
- `schedules.php` - Multi-modal transit schedule tracker
- `guides.php` - Travel how-to guides and destination tips
- `package-detail.php` - Detailed package view with booking
- `my-bookings.php` - Customer booking history
- `admin/dashboard.php` - Admin panel

## License

MIT
