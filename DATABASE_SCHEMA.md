# DATABASE_SCHEMA.md — WatchApps Backend Schema

Версия: 1.0

Документ описывает ключевые сущности базы данных, связанные с:

- пользователями и аутентификацией
- загрузками файлов
- товарами (watchfaces / apps)
- категориями
- файлами товаров

Система использует стандартные таблицы Laravel (users, jobs, cache и т.д.) + кастомные таблицы модуля Watchfaces.

---

## 1. Таблица `users`

**Назначение:** учетные записи пользователей/разработчиков.

Примерная структура (Laravel default, упрощённо):

| Поле        | Тип              | Описание                      |
|------------|------------------|-------------------------------|
| id         | BIGINT UNSIGNED  | PK                            |
| name       | VARCHAR(255)     | Имя                           |
| email      | VARCHAR(255)     | Email (уникальный)            |
| password   | VARCHAR(255)     | Хэш пароля                    |
| created_at | TIMESTAMP        |                               |
| updated_at | TIMESTAMP        |                               |

---

## 2. Таблица `personal_access_tokens`

**Назначение:** токены Sanctum для API‑доступа.

(стандартная структура Laravel Sanctum)

---

## 3. Таблица `uploads`

**Назначение:** хранение метаданных загруженных файлов.

| Поле       | Тип              | Описание                                      |
|-----------|------------------|-----------------------------------------------|
| id        | BIGINT UNSIGNED  | PK                                            |
| user_id   | BIGINT UNSIGNED  | FK → users.id (кто загрузил файл)            |
| filename  | VARCHAR(255)     | Оригинальное имя файла                        |
| path      | VARCHAR(255)     | Путь в файловой системе (для Storage)        |
| mime_type | VARCHAR(100)     | MIME‑тип (`image/png`, `application/vnd...`) |
| size      | BIGINT           | Размер в байтах                               |
| created_at| TIMESTAMP        |                                               |
| updated_at| TIMESTAMP        |                                               |

Связи:
- `Upload` belongsTo `User`

---

## 4. Таблица `watchfaces`

**Назначение:** товары (циферблаты / приложения).

| Поле            | Тип                        | Описание                                               |
|-----------------|---------------------------|--------------------------------------------------------|
| id              | BIGINT UNSIGNED           | PK                                                     |
| developer_id    | BIGINT UNSIGNED           | FK → users.id (разработчик)                            |
| title           | VARCHAR(255)              | Название товара                                        |
| slug            | VARCHAR(255)              | Уникальный slug для URL                                |
| description     | LONGTEXT NULL             | Описание                                               |
| price           | INT                       | Базовая цена (например, в рублях / центах)            |
| discount_price  | INT NULL                  | Цена со скидкой (если есть)                           |
| discount_end_at | DATETIME NULL             | Дата/время окончания скидки                           |
| is_free         | TINYINT(1) DEFAULT 0      | Бесплатный ли товар                                   |
| version         | VARCHAR(255) NULL         | Версия приложения/циферблата (опционально)            |
| type            | ENUM('watchface','app')   | Тип: циферблат или приложение                          |
| status          | ENUM('draft','published','hidden') | Статус публикации                           |
| created_at      | TIMESTAMP NULL            |                                                        |
| updated_at      | TIMESTAMP NULL            |                                                        |

Индексы:
- индекс по `developer_id`
- уникальный индекс по `slug` (рекомендуется)

Связи:
- `Watchface` belongsTo `User` (developer)
- `Watchface` hasMany `WatchfaceFile`
- `Watchface` belongsToMany `Category` через `watchface_category`

---

## 5. Таблица `categories`

**Назначение:** категории (Analog, Digital, Premium, Animated, Free, Sport и т.д.).

| Поле       | Тип              | Описание                  |
|-----------|------------------|---------------------------|
| id        | BIGINT UNSIGNED  | PK                        |
| name      | VARCHAR(255)     | Название категории        |
| slug      | VARCHAR(255)     | Уникальный slug           |
| sort_order| INT DEFAULT 0    | Для сортировки            |
| created_at| TIMESTAMP NULL   |                            |
| updated_at| TIMESTAMP NULL   |                            |

Связи:
- `Category` belongsToMany `Watchface`

---

## 6. Таблица `watchface_category` (pivot)

**Назначение:** связь многие‑ко‑многим между `watchfaces` и `categories`.

| Поле         | Тип              | Описание                    |
|-------------|------------------|-----------------------------|
| id          | BIGINT UNSIGNED  | PK                          |
| watchface_id| BIGINT UNSIGNED  | FK → watchfaces.id         |
| category_id | BIGINT UNSIGNED  | FK → categories.id         |

Индексы:
- составной индекс (`watchface_id`, `category_id`)

---

## 7. Таблица `watchface_files`

**Назначение:** связь watchface ↔ upload файлы (иконки, баннеры, скриншоты, apk, видео).

| Поле         | Тип               | Описание                                              |
|-------------|-------------------|-------------------------------------------------------|
| id          | BIGINT UNSIGNED   | PK                                                    |
| watchface_id| BIGINT UNSIGNED   | FK → watchfaces.id                                   |
| upload_id   | BIGINT UNSIGNED   | FK → uploads.id                                      |
| type        | ENUM(...)         | `icon`, `banner`, `screenshot`, `apk`, `preview_video` |
| sort_order  | INT DEFAULT 0     | Порядок, особенно для скриншотов                     |
| created_at  | TIMESTAMP NULL    |                                                       |
| updated_at  | TIMESTAMP NULL    |                                                       |

Связи:
- `WatchfaceFile` belongsTo `Watchface`
- `WatchfaceFile` belongsTo `Upload`

---

## 8. Прочие таблицы (стандартный Laravel / служебные)

Эти таблицы используются фреймворком и инфраструктурой, и в рамках бизнес‑логики Watchfaces практически не трогаются напрямую:

### 8.1 `migrations`

- Хранит список выполненных миграций.

### 8.2 `jobs`, `failed_jobs`, `job_batches`

- Очереди задач.

### 8.3 `cache`, `cache_locks`

- Кэш Laravel.

### 8.4 `sessions`

- Сессии, если используется драйвер `database`.

### 8.5 `password_reset_tokens`

- Токены сброса пароля.

### 8.6 `user_devices` (кастомная таблица)

Вероятно используется для связи пользователей с устройствами (телефоны/часы). Подробная схема описывается в отдельной спецификации модуля устройств.

### 8.7 `developers`, `developer_payouts`, `payments`, `purchases`

Это таблицы, связанные с:

- учёткой разработчиков как отдельной сущности (если отделено от users)
- выплатами разработчикам
- платежами пользователей
- покупками конкретных товаров

Их точная схема зависит от платежной и финансовой логики и может быть описана в отдельном документе `BILLING_SCHEMA.md`.

---

## 9. Связи на уровне моделей (Eloquent)

### 9.1 Watchface

```php
class Watchface extends Model
{
    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function files()
    {
        return $this->hasMany(WatchfaceFile::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'watchface_category');
    }
}
```

### 9.2 Category

```php
class Category extends Model
{
    public function watchfaces()
    {
        return $this->belongsToMany(Watchface::class, 'watchface_category');
    }
}
```

### 9.3 WatchfaceFile

```php
class WatchfaceFile extends Model
{
    public function watchface()
    {
        return $this->belongsTo(Watchface::class);
    }

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
```

### 9.4 Upload

```php
class Upload extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## 10. Итог

Данный документ покрывает:

- основные бизнес‑сущности WatchApps backend
- связи между пользователями, файлами и товарами
- поддержку категорий, скидок и файлов разного типа

При изменении структуры таблиц (новые поля, типы файлов, статусы и т.п.) этот файл должен обновляться и коммититься вместе с миграциями.
