# Правила разработки WatchApps

---

## Ветки

- main — стабильная
- dev — разработка
- feature/* — отдельные фичи

Примеры:
```bash
git checkout -b feature/watchfaces
git checkout -b feature/payments
git checkout -b feature/android-sync
```

---

## Коммиты

```text
feat: новая функция
fix: исправление бага
refactor: переработка
chore: мелкие правки
```

---

## Pull Requests

- Один PR = одна фича
- PR должен ссылаться на Issue
- Описание обязательно
