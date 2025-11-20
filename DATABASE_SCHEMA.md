# WatchApps — Структура базы данных (MariaDB)

---

# 1. users
- id
- name
- email
- password
- created_at
- updated_at

# 2. developers
- id
- user_id
- payout_info
- created_at

# 3. uploads
- id
- filename
- original_name
- mime
- size
- user_id

# 4. watchfaces
- id
- developer_id
- title
- slug
- description
- price
- is_free
- type
- version
- aod_support
- status

# 5. watchface_files
- id
- watchface_id
- upload_id
- type (apk/icon/screenshot/banner)
- sort_order

# 6. categories
- id
- name
- slug

# 7. watchface_category
- watchface_id
- category_id

# 8. purchases
- id
- user_id
- watchface_id
- payment_id
- created_at

# 9. payments
- id
- purchase_id
- amount
- status
- method
- raw_webhook

# 10. installations
- id
- purchase_id
- device_id
- status (success/failed)
- created_at
