# WatchApps — Архитектура Backend

Документ описывает архитектуру backend части проекта WatchApps.

---

# 1. Общая схема системы

```
Frontend (Next.js)
      ↓ REST
Backend (Laravel 11)
      ↓
Database (MariaDB)
      ↓
Android App ↔ Wear OS App (APK установка)
```

---

# 2. Архитектура Backend (Laravel)

Модули:

- Авторизация (Sanctum)
- Пользователи
- Разработчики
- Watchfaces
- Категории
- Uploads
- Покупки
- Платежи
- Admin Panel
- Android ↔ Wear OS API
- Secure File Delivery

---

# 3. Поток установки APK (Android → Wear OS)

1. Покупка на сайте
2. Платёжная система отправляет webhook
3. Сервер создаёт запись purchase
4. Android получает push от сервера
5. Android скачивает APK по secure-ссылке
6. Android передает файл на Wear OS через FileTransferClient
7. Часы проверяют файл
8. Часы вызывают системный установщик
9. Wear OS → Android → сервер (статус установки)

---

# 4. Модули и их назначение

## Uploads
- хранение файлов
- привязка к watchface
- контроль MIME, размера, безопасности

## Watchfaces
- товар (циферблат)
- файлы (apk, icon, screenshot)
- категории
- скидки
- фильтры каталога

## Payments
- СБП
- банковские карты
- webhooks
- проверка authenticity

---

# 5. Безопасность

- HTTPS / SSL
- Sanctum токены
- Хеш файла APK
- Ограничение скачивания
- Ограничение API запросов
- Rate limiting
- CSRF (для веба)
