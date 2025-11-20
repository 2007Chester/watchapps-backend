# WatchApps — Backend API

Документ описывает все текущие и будущие API-маршруты.

---

# 1. Авторизация

### POST /api/auth/register
```json
{
  "name": "Max",
  "email": "user@test.com",
  "password": "123456"
}
```

### POST /api/auth/login  
Возвращает токен.

### GET /api/auth/user  
Текущий пользователь.

### POST /api/auth/logout

---

# 2. Upload API

### POST /api/upload  
Только для авторизованных.

Формат:
```
file: binary(apk/png/jpg)
```

Ответ:
```json
{
  "id": 12,
  "filename": "1672765412_app.apk",
  "url": "https://watchapps.ru/storage/uploads/1672765412_app.apk"
}
```

---

# 3. Watchfaces API (будет реализовано)

### POST /api/dev/watchfaces  
Создание товара.

### POST /api/dev/watchfaces/{id}/files  
Привязка файлов к watchface.

### GET /api/catalog/top  
### GET /api/catalog/new  
### GET /api/catalog/discounts  

### GET /api/watchface/{slug}

---

# 4. Payments API

### POST /api/payment/webhook  
Платёжная система → сервер.

### GET /api/payment/status/{purchase_id}  
Проверка покупки.

---

# 5. Android ↔ Wear API

### GET /api/watchface/{id}/download  
Secure download.

### POST /api/device/install-status  
Android/Wear отправляет результат установки.
