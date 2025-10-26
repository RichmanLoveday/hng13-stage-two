## 7) README — Quick setup

1. Clone repo
2. `composer install`
3. Copy `.env.example` to `.env` and set DB and API keys
4. `php artisan key:generate`
5. `php artisan migrate`
6. `php artisan storage:link`
7. `php artisan serve` (or set deployment)
8. POST to `/api/countries/refresh` to populate DB

