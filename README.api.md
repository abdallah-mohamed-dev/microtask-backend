## API Endpoints Overview

كل الـ endpoints ترجع JSON فقط وتحتاج الهيدر `Authorization: Bearer {token}` بعد تسجيل الدخول، ما عدا مساري التسجيل وتسجيل الدخول.

### 1. Authentication

| Method & Path             | Body (JSON)                                                  | Notes                                         |
| ------------------------- | ------------------------------------------------------------ | --------------------------------------------- |
| `POST /api/auth/register` | `name` (required), `email` (required), `password` (required) | ينشئ مستخدم جديد ويرجع بياناته مع التوكن.     |
| `POST /api/auth/login`    | `email` (required), `password` (required)                    | يرجّع بيانات المستخدم + توكن جديد.            |
| `GET /api/auth/me`        | —                                                            | يحتاج توكن صالح؛ يرجع بيانات المستخدم الحالي. |

### 2. Projects

| Method & Path                          | Body (JSON)                                           | تفاصيل                                                                                     |
| -------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `GET /api/projects`                    | —                                                     | يرجع كل مشاريع المستخدم مع التاسكات المرتبطة.                                              |
| `POST /api/projects`                   | `title` (required), `description?`, `image?`, `link?` | ينشئ مشروع جديد.                                                                           |
| `GET /api/projects/{id}`               | —                                                     | يرجع مشروع واحد + قائمة تاسكاته.                                                           |
| `PUT /api/projects/{id}`               | أي حقول من: `title`, `description`, `image`, `link`   | يحدث بيانات المشروع. الحقول الغير مرسلة تظل كما هي.                                        |
| `DELETE /api/projects/{id}`            | —                                                     | يحذف المشروع وكل ما يخصه.                                                                  |
| `POST /api/projects/{id}/upload-image` | `multipart/form-data` مع حقل `image`                  | يرفع صورة للمشروع ويحفظ المسار. الامتدادات المسموحة: `jpg,jpeg,png,gif,webp` وحجم حتى 5MB. |

### 3. Tasks

| Method & Path            | Body (JSON)                                                                                                                                                                                                   | تفاصيل                                                              |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| `POST /api/tasks`        | `project_id` (required), `title` (required), `description?`, `status?` (`pending/in_progress/completed/blocked`), `deadline?` (`YYYY-MM-DD`), `tags?` (array of strings), `links?` (array of `{title?, url}`) | ينشئ تاسك داخل مشروع مملوك للمستخدم.                                |
| `GET /api/tasks/{id}`    | —                                                                                                                                                                                                             | يرجع التاسك مع `tags`, `tag_meta`, `links`, `images`, `image_meta`. |
| `PUT /api/tasks/{id}`    | أي حقول من: `title`, `description`, `status`, `deadline`                                                                                                                                                      | يحدث بيانات التاسك.                                                 |
| `DELETE /api/tasks/{id}` | —                                                                                                                                                                                                             | يحذف التاسك.                                                        |

### 4. Task Extras

| Method & Path                            | Body / متطلبات                   | تفاصيل                                            |
| ---------------------------------------- | -------------------------------- | ------------------------------------------------- |
| `POST /api/tasks/{id}/add-tag`           | JSON: `tag` (required)           | يضيف تاج جديد. الرد يرجع كامل بيانات التاسك.      |
| `DELETE /api/tasks/{id}/tag/{tagID}`     | —                                | يحذف تاج باستخدام الـ id الموجود داخل `tag_meta`. |
| `POST /api/tasks/{id}/add-link`          | JSON: `url` (required), `title?` | يضيف لينك للتاسك.                                 |
| `DELETE /api/tasks/{id}/link/{linkID}`   | —                                | يحذف لينك موجود.                                  |
| `POST /api/tasks/{id}/upload-image`      | `multipart/form-data` مع `image` | يرفع صورة جديدة للتاسك ويضيفها للـ `images`.      |
| `DELETE /api/tasks/{id}/image/{imageID}` | —                                | يحذف صورة حسب الـ id الموجود في `image_meta`.     |

### 5. ملاحظات إضافية

- جميع التواريخ بصيغة `Y-m-d H:i:s` ما عدا `deadline` (`Y-m-d`).
- ارفع الصور داخل `uploads/projects` و `uploads/tasks`; تأكد أن السيرفر لديه صلاحية الكتابة.
- جميع الاستعلامات تستخدم PDO + prepared statements، فلا ترسل حقول إضافية غير معروفة.\*\*\*
