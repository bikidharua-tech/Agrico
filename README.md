# Agrico

Full-stack plant disease platform using PHP + MySQL + Python FastAPI.

## Implemented Features
- User registration/login with password hashing (`password_hash`) and session authentication.
- Role-based access (`user`, `admin`) with separate admin login page.
- Plant disease diagnosis flow: image upload in PHP -> Python API -> disease output + confidence + description + treatment -> stored in MySQL.
- Location permission capture (lat/lon) on diagnosis.
- Diagnosis history: user sees own, admin sees all, sortable by date.
- Weather module: current + 7-day forecast using location, city search, or manual coordinates.
- Community forum:
  - Post create/edit/delete (author only; admin override).
  - Up to 5 images, JPG/JPEG/PNG only, max 5MB each.
  - Image cleanup on post deletion.
  - Comments add/edit/delete (author only; admin override).
  - Comment upvote/downvote.
  - Full-text search on title/content.
- Admin panel:
  - Activate/deactivate/delete users.
  - Moderate posts, images, comments.
  - View all predictions.
  - Action logging in `admin_logs`.
## Project Structure
- `sql/schema.sql` - DB schema with constraints/indexes.
- `public/` - PHP pages and API endpoints.
- `app/bootstrap.php` - DB/session/auth/csrf/helpers.
- `python_api/main.py` - FastAPI AI service.

## Setup
1. Create DB and tables:
   - Run `sql/schema.sql` on MySQL 8+.
2. Configure PHP app:
   - Copy `.env.example` to `.env` and fill in your database, URL, OAuth, and API values.
   - `config/config.php` now reads those values from environment variables.
3. Configure admin:
   - Register a user from `register.php`.
   - Run: `UPDATE users SET role='admin' WHERE email='your-email';`
4. Quick start (auto start API + open website):
   - `powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1`
   - This starts Python API, tries to start PHP built-in server, and opens the web app in your browser.
5. Manual start (if needed):
   - `cd python_api`
   - `pip install -r requirements.txt`
   - `uvicorn main:app --host 127.0.0.1 --port 8001`
   - Serve PHP app through Apache/Nginx/PHP built-in server with `public/` as web root.

## Render Deployment
This project can run on Render as a single Docker web service.

1. Create a MySQL database somewhere external.
   - Render does not provide a free managed MySQL database, so use an external MySQL host.
   - Import `sql/schema.sql` into that database.
2. Set environment variables from `.env.example`.
   - At minimum: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_BASE_URL`.
   - Add OAuth/API keys only if you want those features enabled.
3. Connect the repo to Render.
   - Use the `render.yaml` blueprint in the repo root.
   - Render will build the included `Dockerfile`.
4. Set your domain and OAuth redirect URLs.
   - Google callback: `/oauth_google_callback.php`
   - Facebook callback: `/oauth_facebook_callback.php`
5. Deploy.
   - The PHP app will be served from `public/`.
   - The FastAPI service runs inside the same container on `127.0.0.1:8001`.
   - The health check is `/health.php`.

Notes:
- The free Render service is suitable for demos and light traffic.
- Uploaded images are stored on the container filesystem, so they are not guaranteed to persist across redeploys on free hosting.
- If you want durable uploads, you will need external storage such as S3 or Cloudinary.

## Notes
- This environment did not have `php` CLI installed, so PHP lint/runtime checks could not be executed here.
- Python syntax check passed: `python -m py_compile python_api/main.py`.
