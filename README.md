# 🌍 Country Data Refresh Service

A Laravel-based backend service that periodically fetches, validates, and updates country and currency information from external APIs (like RESTCountries and ExchangeRate).  
It stores results in your database, calculates estimated GDP, and provides a clean API for further integration.

---

## 🚀 Features

- Fetches and validates country data from a public API  
- Integrates with exchange rate APIs for currency conversion  
- Calculates estimated GDP using population and exchange rates  
- Upserts records intelligently to avoid duplication  
- Rolls back transactions on validation or fetch failure  
- Generates summary reports with last refreshed timestamp  

---

## 🧰 Tech Stack

- **Laravel 10+**
- **PHP 8.2+**
- **MySQL / MariaDB**
- **Composer**
- **Guzzle HTTP Client** (for API calls)
- **Carbon** (for date/time operations)

---

## ⚙️ Installation

### 1️⃣ Clone the Repository
```bash
git clone https://github.com/your-username/country-data-refresh.git
cd country-data-refresh
```

### 1️⃣ Install Dependencies
```bash
composer install
```

### Configure Environment
```bash
cp .env.example .env
```
Then edit .env to include your database details:

```env
APP_NAME=CountryRefreshService
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=country_refresh
DB_USERNAME=root
DB_PASSWORD=
```

### Run migration
```bash
php artisan migrate
```

### Usage
```bash
php artisan serve
```

Refresh Country and Exchange Data
You can trigger the refresh via:
```bash
php artisan tinker
>>> app(App\Services\CountryService::class)->refresh();
```
Or through an API endpoint if exposed (e.g.):
```bash
api/countries/refresh
```

Successful response:
```json
{
  "message": "Refreshed successfully",
  "last_refreshed_at": "2025-10-27T12:30:00Z",
}
```

### Folder Structure
```pgsql
app/
 ├── Models/
 │   └── CountryCurrency.php
 ├── Services/
 │   └── CountryCurrencyService.php
database/
 ├── migrations/
 └── seeders/
routes/
 └── api.php
 ```