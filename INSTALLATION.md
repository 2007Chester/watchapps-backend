# Установка и запуск backend WatchApps

---

## Требования

- PHP 8.3
- Composer
- MariaDB 10+
- Nginx
- SSL сертификат

---

## Установка

```bash
git clone git@github.com:2007Chester/watchapps-backend.git
cd watchapps-backend
composer install
cp .env.example .env
php artisan key:generate
```

---

## База данных

```bash
php artisan migrate --force
```

---

## Storage

```bash
php artisan storage:link
```

---

## Nginx

```nginx
root /var/www/watchapps/backend/public;

location / {
  try_files $uri $uri/ /index.php?$query_string;
}
```

---

## HTTPS

Используем Certbot:

```bash
certbot --nginx
```
