# BACKEND_API.md — WatchApps Backend API (Laravel)

Версия: 1.0  
Base URL (prod, пример): `https://api.watchapps.ru`  
Base URL (dev): `https://dev-api.watchapps.ru` или локально: `http://localhost:8000`

Все API находятся под префиксом `/api`, ниже указаны пути **без** `/api` (как они описаны в `routes/api.php`).

Аутентификация — через Laravel Sanctum (Bearer Token в заголовке `Authorization`).

---

## 1. AUTH API

### 1.1 Регистрация

**POST** `/auth/register`  

Регистрация нового пользователя‑разработчика.

#### Request (JSON)

```json
{
  "name": "Max Chester",
  "email": "user@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

#### Response 201

```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Max Chester",
    "email": "user@example.com"
  },
  "token": "SANCTUM_TOKEN_HERE"
}
```

---

### 1.2 Логин

**POST** `/auth/login`  

Авторизация пользователя и выдача Sanctum-токена.

#### Request (JSON)

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

#### Response 200

```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Max Chester",
    "email": "user@example.com"
  },
  "token": "SANCTUM_TOKEN_HERE"
}
```

---

### 1.3 Получение текущего пользователя

**GET** `/auth/user`  
Требует: `Authorization: Bearer <token>`

#### Response 200

```json
{
  "id": 1,
  "name": "Max Chester",
  "email": "user@example.com"
}
```

---

### 1.4 Логаут

**POST** `/auth/logout`  
Требует: `Authorization: Bearer <token>`

#### Response 200

```json
{
  "success": true
}
```

---

## 2. Upload API

Используется Dev Console и другими частями системы для загрузки файлов (иконки, баннеры, скриншоты, APK, видео превью и т.п.).

### 2.1 Загрузка файла

**POST** `/upload`  
Требует: `Authorization: Bearer <token>`  
Тип: `multipart/form-data`

#### Параметры

- `file` — загружаемый файл (обязательный)
- `type` — опциональный тип (например: `icon`, `banner`, `screenshot`, `apk`, `preview_video`)

#### Response 201

```json
{
  "success": true,
  "upload": {
    "id": 10,
    "filename": "chester-icon.png",
    "path": "uploads/2025/11/21/chester-icon.png",
    "mime_type": "image/png",
    "size": 123456,
    "created_at": "2025-11-21T12:00:00Z"
  }
}
```

---

### 2.2 Список загруженных файлов

**GET** `/uploads`  
Требует: `Authorization: Bearer <token>`

Опционально в дальнейшем можно добавить фильтры (по типу, дате и т.д.).

#### Response 200

```json
{
  "success": true,
  "items": [
    {
      "id": 10,
      "filename": "chester-icon.png",
      "path": "uploads/2025/11/21/chester-icon.png",
      "mime_type": "image/png",
      "size": 123456
    }
  ]
}
```

---

## 3. Dev Console — Watchfaces Management

Все маршруты начинаются с префикса `/dev` и доступны только авторизованным пользователям (`auth:sanctum`).  
Это внутренняя панель разработчика WatchApps.

### 3.1 Список watchfaces текущего разработчика

**GET** `/dev/watchfaces`

#### Response 200

```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "developer_id": 5,
      "title": "Chester Premium Silver",
      "slug": "chester-premium-silver-90ab1",
      "description": "Premium analog face",
      "price": 199,
      "discount_price": 99,
      "discount_end_at": "2025-12-31T23:59:59Z",
      "is_free": false,
      "type": "watchface",
      "status": "draft",
      "created_at": "2025-11-21T10:00:00Z",
      "updated_at": "2025-11-21T10:30:00Z",
      "categories": [
        { "id": 1, "name": "Analog", "slug": "analog" },
        { "id": 3, "name": "Premium", "slug": "premium" }
      ],
      "files": [
        { "id": 11, "type": "icon", "upload_id": 50, "sort_order": 0 },
        { "id": 12, "type": "banner", "upload_id": 51, "sort_order": 0 },
        { "id": 13, "type": "screenshot", "upload_id": 52, "sort_order": 1 }
      ]
    }
  ]
}
```

---

### 3.2 Создание нового watchface

**POST** `/dev/watchfaces`

#### Request (JSON)

```json
{
  "title": "Chester Premium Silver",
  "description": "Premium analog watch face",
  "price": 199,
  "is_free": false,
  "type": "watchface",
  "discount_price": 99,
  "discount_end_at": "2025-12-31T23:59:59Z",
  "categories": [1, 3, 5]
}
```

- `categories` — массив `id` категорий из таблицы `categories` (необязателен)
- если `is_free = true`, `price` может быть `0`, но не обязательно (зависит от бизнес‑логики)

#### Response 201

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "developer_id": 5,
    "title": "Chester Premium Silver",
    "slug": "chester-premium-silver-90ab1",
    "description": "Premium analog watch face",
    "price": 199,
    "discount_price": 99,
    "discount_end_at": "2025-12-31T23:59:59Z",
    "is_free": false,
    "type": "watchface",
    "status": "draft",
    "categories": [
      { "id": 1, "name": "Analog", "slug": "analog" },
      { "id": 3, "name": "Premium", "slug": "premium" }
    ],
    "files": []
  }
}
```

---

### 3.3 Получение одного watchface для редактирования

**GET** `/dev/watchfaces/{id}`

#### Response 200

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "title": "Chester Premium Silver",
    "description": "Premium analog watch face",
    "price": 199,
    "discount_price": 99,
    "discount_end_at": "2025-12-31T23:59:59Z",
    "is_free": false,
    "type": "watchface",
    "status": "draft",
    "categories": [
      { "id": 1, "name": "Analog", "slug": "analog" }
    ],
    "files": [
      { "id": 11, "type": "icon", "upload_id": 50, "sort_order": 0 },
      { "id": 12, "type": "banner", "upload_id": 51, "sort_order": 0 },
      { "id": 13, "type": "screenshot", "upload_id": 52, "sort_order": 1 }
    ]
  }
}
```

Коды ошибок:

- `403 Forbidden` — если watchface не принадлежит текущему разработчику
- `404 Not Found` — если записи нет

---

### 3.4 Обновление watchface (общие поля + цена + скидка + категории)

**PUT** `/dev/watchfaces/{id}`

#### Request (JSON)

Все поля опциональны, передаются только изменяемые:

```json
{
  "title": "Chester Premium Silver v2",
  "description": "Updated description",
  "price": 249,
  "discount_price": 149,
  "discount_end_at": "2026-01-10T00:00:00Z",
  "is_free": false,
  "type": "watchface",
  "categories": [1, 4]
}
```

- если `categories` передан — он **перезаписывает** список категорий (через `sync()`)
- если `discount_price = null` — скидка убирается

#### Response 200

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "title": "Chester Premium Silver v2",
    "price": 249,
    "discount_price": 149,
    "discount_end_at": "2026-01-10T00:00:00Z",
    "status": "draft",
    "categories": [
      { "id": 1, "name": "Analog", "slug": "analog" },
      { "id": 4, "name": "Animated", "slug": "animated" }
    ],
    "files": [ /* ... */ ]
  }
}
```

---

### 3.5 Удаление watchface

**DELETE** `/dev/watchfaces/{id}`

Удаляет:

- сам watchface
- все связи с категориями
- все записи в `watchface_files`
- соответствующие записи в `uploads`
- и физические файлы через `Storage::delete()`

#### Response 200

```json
{
  "success": true
}
```

---

### 3.6 Привязка файлов к watchface

**POST** `/dev/watchfaces/{id}/files`

#### Request (JSON)

```json
{
  "files": [
    { "upload_id": 50, "type": "icon", "sort_order": 0 },
    { "upload_id": 51, "type": "banner", "sort_order": 0 },
    { "upload_id": 52, "type": "screenshot", "sort_order": 1 }
  ]
}
```

- `upload_id` — id записи в `uploads`
- `type` — `icon | banner | screenshot | apk | preview_video`
- `sort_order` — для сортировки скриншотов и т.п.

#### Response 200

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "files": [
      { "id": 11, "type": "icon", "upload_id": 50, "sort_order": 0 },
      { "id": 12, "type": "banner", "upload_id": 51, "sort_order": 0 },
      { "id": 13, "type": "screenshot", "upload_id": 52, "sort_order": 1 }
    ]
  }
}
```

---

### 3.7 Удаление файла у watchface

**DELETE** `/dev/watchfaces/{watchfaceId}/files/{fileId}`

Удаляет:

- запись `watchface_files`
- связанную запись `uploads`
- физический файл `Storage::delete($upload->path)`

#### Response 200

```json
{
  "success": true,
  "message": "File removed successfully"
}
```

---

### 3.8 Замена файла у watchface

**POST** `/dev/watchfaces/{watchfaceId}/files/{fileId}/replace`

#### Request (JSON)

```json
{
  "upload_id": 60,
  "type": "icon"
}
```

- `upload_id` — новый файл в `uploads`
- `type` — опционально, можно сменить тип при замене

Старый upload‑файл будет удалён из файловой системы и таблицы `uploads`.

#### Response 200

```json
{
  "success": true,
  "file": {
    "id": 11,
    "watchface_id": 1,
    "upload_id": 60,
    "type": "icon",
    "sort_order": 0
  }
}
```

---

### 3.9 Публикация watchface

**POST** `/dev/watchfaces/{id}/publish`

Переводит статус в `published`.

#### Response 200

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "status": "published"
  }
}
```

---

### 3.10 Снятие с публикации

**POST** `/dev/watchfaces/{id}/unpublish`

Переводит статус в `draft`.

#### Response 200

```json
{
  "success": true,
  "watchface": {
    "id": 1,
    "status": "draft"
  }
}
```

---

## 4. PUBLIC CATALOG API

Эти маршруты **публичные**, не требуют авторизации.

### 4.1 Топ‑товары

**GET** `/catalog/top`

#### Response 200

```json
[
  {
    "id": 1,
    "title": "Chester Premium Silver",
    "slug": "chester-premium-silver-90ab1",
    "price": 199,
    "discount_price": 99,
    "type": "watchface",
    "status": "published"
  }
]
```

---

### 4.2 Новинки

**GET** `/catalog/new`

Сортировка по `created_at DESC`, только `status = published`.

---

### 4.3 Товары со скидкой

**GET** `/catalog/discounts`

Фильтр по:

- `status = published`
- `discount_price IS NOT NULL`
- `discount_end_at IS NULL OR discount_end_at >= NOW()` (можно реализовать в будущем)

---

### 4.4 Товары по категории

**GET** `/catalog/category/{slug}`

Возвращает все опубликованные watchfaces, привязанные к категории с указанным `slug`.

---

### 4.5 Страница товара по slug

**GET** `/watchface/{slug}`

#### Response 200

```json
{
  "id": 1,
  "title": "Chester Premium Silver",
  "slug": "chester-premium-silver-90ab1",
  "description": "Premium analog watch face",
  "price": 199,
  "discount_price": 99,
  "discount_end_at": "2025-12-31T23:59:59Z",
  "type": "watchface",
  "status": "published",
  "categories": [
    { "id": 1, "name": "Analog", "slug": "analog" },
    { "id": 3, "name": "Premium", "slug": "premium" }
  ],
  "files": [
    { "type": "icon", "upload_id": 50 },
    { "type": "banner", "upload_id": 51 },
    { "type": "screenshot", "upload_id": 52 }
  ]
}
```

---

## 5. Коды ответов и ошибки

- `200 OK` — успешный запрос
- `201 Created` — ресурс создан
- `400 Bad Request` — ошибка валидации
- `401 Unauthorized` — нет или неверный токен
- `403 Forbidden` — нет прав (чужой watchface)
- `404 Not Found` — ресурс не найден
- `500 Internal Server Error` — внутренняя ошибка сервера

Все ошибки валидации стандартные для Laravel (`422 Unprocessable Entity` с полем `errors`).

