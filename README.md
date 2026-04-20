# Beacon

Beacon is a small Laravel + Inertia + React application for drafting and scheduling communication campaigns against a user-owned contact list.

## Setup

### Prerequisites

- PHP 8.4+
- Composer
- Node.js 22+ recommended

### Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
```

### Run the App

For the full local dev experience:

```bash
composer run dev
```

That starts:

- the Laravel app server
- the queue listener
- the Laravel log tail
- the Vite dev server

If you only need frontend assets:

```bash
npm run dev
```

If you only need a production asset build:

```bash
npm run build
```

### Seed Demo Data

```bash
php artisan db:seed
```

The seeders create a test user, a baseline tag set, and a large sample contact list to make filtering and pagination easier to review.

## Testing

Run the focused Laravel test suite:

```bash
php artisan test --compact
```

Run specific high-value feature files while iterating:

```bash
php artisan test --compact tests/Feature/ContactManagementTest.php
php artisan test --compact tests/Feature/CampaignManagementTest.php
php artisan test --compact tests/Feature/ProcessScheduledCampaignTest.php
```

Useful frontend and static checks:

```bash
npm run types:check
npm run lint
```

PHP formatting is handled with Pint:

```bash
vendor/bin/pint --dirty --format agent
```
