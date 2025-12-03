# WatchApps Backend (Laravel)

Backend API для платформы WatchApps, построенный на Laravel 12.

## 📋 Содержание

- [Описание](#описание)
- [Технологический стек](#технологический-стек)
- [Установка и настройка](#установка-и-настройка)
- [Структура проекта](#структура-проекта)
- [API эндпоинты](#api-эндпоинты)
- [Аутентификация](#аутентификация)
- [Роли и права доступа](#роли-и-права-доступа)
- [База данных](#база-данных)
- [Загрузка файлов](#загрузка-файлов)
- [Развертывание](#развертывание)

---

## Описание

WatchApps Backend — это REST API, который обеспечивает:

- **Аутентификацию и авторизацию** пользователей через Laravel Sanctum
- **Управление пользователями** с поддержкой многоролевой системы
- **Dev Console API** для разработчиков (управление циферблатами)
- **Публичный каталог** циферблатов
- **Загрузку и хранение файлов** (APK, изображения)
- **Статистику** просмотров и кликов
- **Email верификацию** и восстановление пароля

---

## Технологический стек

- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Database**: SQLite (разработка), PostgreSQL/MySQL (production)
- **Authentication**: Laravel Sanctum (SPA authentication)
- **File Storage**: Laravel Filesystem
- **Mail**: Laravel Mail (SMTP)

---

## Установка и настройка

### Требования

- PHP 8.2+
- Composer
- SQLite (или PostgreSQL/MySQL)
- Расширения PHP: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`

### Установка

```bash
cd /var/www/watchapps/backend

# Установка зависимостей
composer install

# Копирование файла окружения
cp .env.example .env

# Генерация ключа приложения
php artisan key:generate
```

### Настройка .env

```env
APP_NAME=WatchApps
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# База данных (SQLite для разработки)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/watchapps/backend/database/database.sqlite

# Или PostgreSQL/MySQL для production
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=watchapps
# DB_USERNAME=...
# DB_PASSWORD=...

# Sanctum для SPA
SANCTUM_STATEFUL_DOMAINS=localhost:3000,dev.watchapps.ru,watchapps.ru
SESSION_DOMAIN=.watchapps.ru

# Mail (для верификации email и восстановления пароля)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@watchapps.ru
MAIL_FROM_NAME="${APP_NAME}"

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://dev.watchapps.ru,https://watchapps.ru
```

### Миграции

```bash
# Создание базы данных (если используете SQLite)
touch database/database.sqlite

# Запуск миграций
php artisan migrate

# Заполнение тестовыми данными (опционально)
php artisan db:seed
```

### Запуск сервера разработки

```bash
php artisan serve
# Сервер будет доступен на http://localhost:8000
```

---

## Структура проекта

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Контроллеры API
│   │   │   ├── AuthController.php              # Аутентификация
│   │   │   ├── CatalogController.php           # Публичный каталог
│   │   │   ├── DeveloperOnboardingController.php # Onboarding разработчика
│   │   │   ├── PurchaseController.php          # Покупки
│   │   │   ├── UploadController.php            # Загрузка файлов
│   │   │   ├── WatchfaceController.php         # CRUD циферблатов
│   │   │   └── WatchfaceStatsController.php     # Статистика
│   │   └── Middleware/
│   │       ├── RoleMiddleware.php               # Проверка ролей
│   │       └── VerifyEmailMiddleware.php       # Проверка верификации
│   ├── Models/                  # Eloquent модели
│   │   ├── User.php
│   │   ├── UserRole.php
│   │   ├── Watchface.php
│   │   ├── WatchfaceFile.php
│   │   ├── Category.php
│   │   ├── Upload.php
│   │   └── ...
│   └── Services/
│       └── WatchfaceService.php
├── database/
│   ├── migrations/              # Миграции БД
│   └── seeders/                # Сидеры
├── routes/
│   └── api.php                  # API маршруты
├── storage/
│   └── app/
│       └── public/
│           └── uploads/        # Загруженные файлы
└── config/
    ├── sanctum.php              # Конфигурация Sanctum
    └── cors.php                 # Конфигурация CORS
```

---

## API эндпоинты

### Базовый URL

- **Development**: `http://localhost:8000/api`
- **Production**: `https://dev.watchapps.ru/api`

### Публичные эндпоинты (без авторизации)

#### Аутентификация

```
GET  /sanctum/csrf-cookie          # Получить CSRF cookie для SPA
POST /auth/register                # Регистрация пользователя
POST /auth/login                   # Вход в систему
POST /auth/check-email             # Проверка email на уникальность
POST /auth/forgot-password         # Запрос восстановления пароля
POST /auth/reset-password          # Сброс пароля с токеном
GET  /auth/verify/{token}         # Подтверждение email
```

#### Каталог

```
GET /catalog/top                   # Топ циферблатов
GET /catalog/new                   # Новые циферблаты
GET /catalog/discounts             # Циферблаты со скидкой
GET /catalog/category/{slug}       # Циферблаты по категории
GET /watchface/{slug}              # Информация о циферблате
```

#### Статистика (публичное логирование)

```
POST /watchface/{id}/log/view      # Логирование просмотра
POST /watchface/{id}/log/click     # Логирование клика
```

### Защищенные эндпоинты (требуют авторизации)

#### Общие (для всех авторизованных)

```
GET  /auth/user                    # Информация о текущем пользователе
POST /auth/logout                  # Выход из системы
POST /auth/send-verification      # Отправить письмо верификации
```

#### Onboarding разработчика (требует роль `developer`)

```
GET  /dev/onboarding               # Получить данные onboarding
PUT  /dev/onboarding               # Обновить данные onboarding
POST /dev/onboarding/complete      # Завершить onboarding
```

### Эндпоинты для верифицированных пользователей

#### Загрузка файлов

```
POST /upload                       # Загрузить файл
GET  /uploads                      # Список загруженных файлов
```

#### Покупки

```
POST /purchase                     # Покупка циферблата
```

### Dev Console (требует `verified` + `role:developer`)

#### Управление циферблатами

```
GET    /dev/watchfaces             # Список циферблатов разработчика
POST   /dev/watchfaces             # Создать циферблат
GET    /dev/watchfaces/{id}        # Получить циферблат
PUT    /dev/watchfaces/{id}        # Обновить циферблат
DELETE /dev/watchfaces/{id}        # Удалить циферблат
POST   /dev/watchfaces/{id}/publish      # Опубликовать
POST   /dev/watchfaces/{id}/unpublish    # Снять с публикации
```

#### Управление файлами циферблатов

```
POST   /dev/watchfaces/{id}/files        # Прикрепить файлы
POST   /dev/watchfaces/{watchfaceId}/files/{fileId}/replace  # Заменить файл
DELETE /dev/watchfaces/{watchfaceId}/files/{fileId}        # Удалить файл
```

#### Статистика

```
GET /dev/watchfaces/{id}/stats     # Статистика циферблата
```

---

## Аутентификация

### Laravel Sanctum SPA Authentication

Backend использует Laravel Sanctum для аутентификации SPA (Single Page Application).

#### Процесс аутентификации:

1. **Получение CSRF cookie** (перед первым запросом):
   ```
   GET /api/sanctum/csrf-cookie
   ```

2. **Вход в систему**:
   ```
   POST /api/auth/login
   Content-Type: application/json
   
   {
     "email": "user@example.com",
     "password": "password123",
     "remember": true
   }
   ```

   Ответ:
   ```json
   {
     "token": "1|abc123...",
     "user": {
       "id": 1,
       "name": "Иван Иванов",
       "email": "user@example.com",
       "roles": ["user", "developer"],
       "primary_role": "developer",
       "email_verified_at": "2025-01-01T00:00:00.000000Z",
       "onboarding_completed": true
     }
   }
   ```

3. **Использование токена**:
   Все последующие запросы должны включать токен в заголовке:
   ```
   Authorization: Bearer 1|abc123...
   ```

#### Выход из системы

```
POST /api/auth/logout
Authorization: Bearer {token}
```

### Email верификация

1. После регистрации пользователь получает письмо с токеном
2. Переход по ссылке `/api/auth/verify/{token}` подтверждает email
3. До верификации некоторые функции недоступны (покупки, загрузка файлов)

### Восстановление пароля

1. Пользователь запрашивает восстановление:
   ```
   POST /api/auth/forgot-password
   {
     "email": "user@example.com"
   }
   ```

2. Получает письмо с токеном восстановления

3. Сбрасывает пароль:
   ```
   POST /api/auth/reset-password
   {
     "email": "user@example.com",
     "token": "reset-token",
     "password": "newpassword123",
     "password_confirmation": "newpassword123"
   }
   ```

---

## Роли и права доступа

### Роли пользователей

- **user** — обычный пользователь (может покупать циферблаты)
- **developer** — разработчик (может загружать и управлять циферблатами)
- **admin** — администратор (полный доступ)

### Многоролевая система

Один пользователь может иметь несколько ролей одновременно. Приоритет ролей: `admin` > `developer` > `user`.

### Middleware

#### `auth:sanctum`
Проверяет наличие валидного токена авторизации.

#### `verified`
Проверяет, что email пользователя верифицирован (`email_verified_at` не null).

#### `role:developer`
Проверяет, что у пользователя есть роль `developer`.

### Примеры использования

```php
// Требует авторизации
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
});

// Требует авторизации + верификации
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
});

// Требует авторизации + роль developer
Route::middleware(['auth:sanctum', 'role:developer'])->group(function () {
    Route::get('/dev/onboarding', [DeveloperOnboardingController::class, 'show']);
});

// Требует авторизации + верификации + роль developer
Route::middleware(['auth:sanctum', 'verified', 'role:developer'])->group(function () {
    Route::get('/dev/watchfaces', [WatchfaceController::class, 'index']);
});
```

---

## База данных

### Основные таблицы

#### `users`
Хранит информацию о пользователях.

**Поля**:
- `id` — ID пользователя
- `name` — Имя
- `email` — Email (уникальный)
- `password` — Хеш пароля
- `email_verified_at` — Дата верификации email
- `brand_name` — Название бренда (для разработчиков)
- `logo_upload_id` — ID загруженного логотипа
- `onboarding_completed` — Завершен ли onboarding
- `created_at`, `updated_at`

#### `user_roles`
Хранит роли пользователей (многоролевая система).

**Поля**:
- `id` — ID записи
- `user_id` — ID пользователя
- `role` — Роль (user, developer, admin)
- `created_at`, `updated_at`

#### `watchfaces`
Хранит информацию о циферблатах.

**Поля**:
- `id` — ID циферблата
- `developer_id` — ID разработчика
- `title` — Название
- `slug` — URL-friendly идентификатор
- `description` — Описание
- `price` — Цена
- `discount_price` — Цена со скидкой
- `discount_end_at` — Дата окончания скидки
- `is_free` — Бесплатный ли
- `version` — Версия
- `type` — Тип (watchface, app)
- `status` — Статус (draft, published)
- `created_at`, `updated_at`

#### `watchface_files`
Связывает циферблаты с загруженными файлами (APK).

**Поля**:
- `id` — ID файла
- `watchface_id` — ID циферблата
- `upload_id` — ID загруженного файла
- `platform` — Платформа (wearos)
- `version` — Версия файла
- `created_at`, `updated_at`

#### `uploads`
Хранит информацию о загруженных файлах.

**Поля**:
- `id` — ID загрузки
- `filename` — Имя файла на диске
- `original_name` — Оригинальное имя файла
- `mime` — MIME тип
- `size` — Размер файла
- `user_id` — ID пользователя, загрузившего файл
- `created_at`, `updated_at`

#### `watchface_views`
Логирует просмотры циферблатов.

**Поля**:
- `id` — ID записи
- `watchface_id` — ID циферблата
- `ip_address` — IP адрес
- `user_agent` — User Agent
- `created_at`

#### `watchface_clicks`
Логирует клики по циферблатам.

**Поля**:
- `id` — ID записи
- `watchface_id` — ID циферблата
- `ip_address` — IP адрес
- `user_agent` — User Agent
- `created_at`

#### `watchface_sales`
Хранит информацию о покупках.

**Поля**:
- `id` — ID продажи
- `watchface_id` — ID циферблата
- `user_id` — ID покупателя
- `price` — Цена покупки
- `created_at`

#### `categories`
Категории циферблатов.

**Поля**:
- `id` — ID категории
- `name` — Название
- `slug` — URL-friendly идентификатор
- `sort_order` — Порядок сортировки

#### `email_verification_tokens`
Токены для верификации email.

**Поля**:
- `id` — ID токена
- `user_id` — ID пользователя
- `token` — Токен верификации
- `expires_at` — Дата истечения
- `created_at`

### Связи

- `User` → `UserRole` (один ко многим)
- `User` → `Watchface` (один ко многим, через `developer_id`)
- `User` → `Upload` (один ко многим)
- `Watchface` → `WatchfaceFile` (один ко многим)
- `Watchface` → `Category` (многие ко многим)
- `Watchface` → `WatchfaceView` (один ко многим)
- `Watchface` → `WatchfaceClick` (один ко многим)
- `Watchface` → `WatchfaceSale` (один ко многим)

---

## Загрузка файлов

### Хранение файлов

Файлы хранятся в `storage/app/public/uploads/`. Для доступа к файлам через HTTP используется симлинк:

```bash
php artisan storage:link
```

Это создаст симлинк `public/storage` → `storage/app/public`.

### URL файлов

URL файлов генерируются динамически с учетом окружения:

- **Development**: `http://localhost:8000/storage/uploads/{filename}`
- **Production**: `https://dev.watchapps.ru/storage/uploads/{filename}`

Контроллеры (`UploadController`, `DeveloperOnboardingController`) автоматически определяют правильный протокол и домен на основе заголовков запроса.

### Загрузка файла

```
POST /api/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: [binary]
```

Ответ:
```json
{
  "id": 1,
  "filename": "abc123.jpg",
  "original_name": "logo.jpg",
  "mime": "image/jpeg",
  "size": 12345,
  "url": "https://dev.watchapps.ru/storage/uploads/abc123.jpg",
  "created_at": "2025-01-01T00:00:00.000000Z"
}
```

---

## Развертывание

### Production окружение

#### 1. Настройка .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dev.watchapps.ru

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=watchapps
DB_USERNAME=...
DB_PASSWORD=...

SANCTUM_STATEFUL_DOMAINS=dev.watchapps.ru,watchapps.ru
SESSION_DOMAIN=.watchapps.ru

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@watchapps.ru
MAIL_FROM_NAME="WatchApps"
```

#### 2. Оптимизация

```bash
# Кэширование конфигурации
php artisan config:cache

# Кэширование маршрутов
php artisan route:cache

# Кэширование представлений
php artisan view:cache

# Оптимизация автозагрузки
composer install --optimize-autoloader --no-dev
```

#### 3. Запуск сервера

```bash
# Через systemd service (рекомендуется)
# Создать файл /etc/systemd/system/watchapps-backend.service
```

Пример systemd service:

```ini
[Unit]
Description=WatchApps Laravel Backend
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/watchapps/backend
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
Restart=always

[Install]
WantedBy=multi-user.target
```

#### 4. Nginx конфигурация

```nginx
location /api {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
}
```

---

## Полезные команды

### Миграции

```bash
# Создать новую миграцию
php artisan make:migration create_example_table

# Запустить миграции
php artisan migrate

# Откатить последнюю миграцию
php artisan migrate:rollback

# Откатить все миграции
php artisan migrate:reset

# Пересоздать базу данных
php artisan migrate:fresh
```

### Очистка кэша

```bash
# Очистить все кэши
php artisan optimize:clear

# Или по отдельности
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Работа с пользователями

```bash
# Создать пользователя через tinker
php artisan tinker
>>> User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password123')
]);

# Добавить роль пользователю
>>> $user = User::find(1);
>>> $user->roles()->create(['role' => 'developer']);

# Удалить всех пользователей
php artisan tinker --execute="DB::table('user_roles')->delete(); DB::table('users')->delete();"
```

### Логи

```bash
# Просмотр логов в реальном времени
php artisan pail

# Или через tail
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### Проблемы с аутентификацией

1. **401 Unauthorized на всех запросах**
   - Проверьте, что токен отправляется в заголовке `Authorization: Bearer {token}`
   - Проверьте, что CSRF cookie получена перед первым запросом
   - Проверьте настройки `SANCTUM_STATEFUL_DOMAINS` в `.env`

2. **CORS ошибки**
   - Проверьте настройки в `config/cors.php`
   - Убедитесь, что домен фронтенда добавлен в `CORS_ALLOWED_ORIGINS`

### Проблемы с загрузкой файлов

1. **403 Forbidden при загрузке**
   - Проверьте, что пользователь верифицирован (`verified` middleware)
   - Проверьте права доступа к директории `storage/app/public/uploads`

2. **Файлы не доступны через HTTP**
   - Убедитесь, что создан симлинк: `php artisan storage:link`
   - Проверьте права доступа к `public/storage`

### Проблемы с базой данных

1. **SQLite: database is locked**
   - Убедитесь, что только один процесс использует базу данных
   - Проверьте права доступа к файлу базы данных

2. **Миграции не применяются**
   - Проверьте подключение к базе данных в `.env`
   - Убедитесь, что файл базы данных существует (для SQLite)

---

## Дополнительная документация

- [ARCHITECTURE.md](./ARCHITECTURE.md) — Архитектура backend
- [BACKEND_API.md](./BACKEND_API.md) — Подробная документация API
- [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) — Схема базы данных
- [INSTALLATION.md](./INSTALLATION.md) — Детальная инструкция по установке
- [ROADMAP.md](./ROADMAP.md) — План развития

---

**Последнее обновление**: 2025-01-XX
