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

## Deploying to Vercel

This project uses the [vercel-php](https://github.com/vercel-community/php) community runtime so PHP pages run as serverless functions while static assets are served from the repo root.

### 1. Cloud database (required)

Vercel cannot reach `127.0.0.1` on your machine. Provision a free MySQL instance (e.g. [Aiven](https://aiven.io), [PlanetScale](https://planetscale.com), or [TiDB Cloud](https://tidbcloud.com)) and import the schema:

```bash
mysql -h YOUR_CLOUD_HOST -P YOUR_PORT -u YOUR_USER -p YOUR_DB_NAME < schema.sql
```

### 2. Vercel project settings

In **Settings → General**:

| Setting | Value |
|---|---|
| Framework Preset | **Other** |
| Build Command | *(handled by `vercel.json` — runs `npm run build`)* |
| Output Directory | **`dist`** |

In **Settings → Environment Variables**, add:

| Variable | Example |
|---|---|
| `DB_HOST` | `your-db.aivencloud.com` |
| `DB_PORT` | `3306` *(optional, if non-standard)* |
| `DB_USER` | `avnadmin` |
| `DB_PASS` | *(your cloud password)* |
| `DB_NAME` | `travel_db` |
| `DB_CHARSET` | `utf8mb4` |

Apply to **Production**, **Preview**, and **Development**.

### 3. Deploy

```bash
git add vercel.json .vercelignore config/db.php
git commit -m "Add Vercel PHP serverless runtime configuration"
git push origin main
```

Vercel reads `vercel.json`, compiles PHP with `vercel-php@0.9.0`, builds frontend assets via Vite, and connects to your cloud database through environment variables.

### Local vs production credentials

- **Local:** Copy `.env.example` → `.env` and fill in your local MySQL credentials.
- **Vercel:** Environment variables from the dashboard take precedence; no `.env` file is needed on the server.

