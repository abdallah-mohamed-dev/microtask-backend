## Microtask Backend (PHP)

Backend API for fast project & task management, built with plain PHP, MySQL, and PDO. Implements token-based auth plus file uploads for project/task images.

### Requirements
- PHP 8.1+
- MySQL 8+
- Composer (optional, not required)

### Setup
1. Copy `config/config.example.php` to `config/config.php` and update credentials plus upload paths if needed.
2. Import `schema.sql` into your MySQL database.
3. Start the PHP server:
   ```
   php -S localhost:8000 -t public
   ```
4. Call endpoints with `Authorization: Bearer {token}` after registering/logging in.

### Endpoints
- Auth: `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`
- Projects: CRUD + `POST /api/projects/{id}/upload-image`
- Tasks: CRUD + tag/link/image extras (see spec)

- `tags` is a string array for quick display, while `tag_meta` contains `{id, tag}` objects used by delete endpoints. Task images follow the same pattern with `images` (urls) and `image_meta` (ids + paths).
- Upload project images via `multipart/form-data` with `image` field; task images use the same field.

