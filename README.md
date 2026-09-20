# SIAKAD — Academic Enrollment API

Laravel 12 API for a single-page academic KRS management system. The API is designed for server-side pagination, sorting, searching, advanced filtering, atomic enrollment creation, and large CSV exports.

## Live demo

- [Academic dashboard](https://akademik-frontend-1swy.vercel.app)
- [Paginated API sample](https://akademik-api-production-6aad.up.railway.app/api/enrollments?page=1&page_size=2)
- [CSV export](https://akademik-api-production-6aad.up.railway.app/api/enrollments/export)

The Railway demo is initially seeded with **1,000 synthetic enrollments**; the current count may change as visitors test CRUD. The five-million-row dataset is generated and tested locally; it is **not** hosted on Railway. The Railway service currently uses trial credit, so demo availability depends on the account's remaining credit and service status. CRUD endpoints have no authentication: do not submit real student data or secrets.

## Requirements

- PHP 8.2 or newer
- Composer 2
- MySQL 8, MariaDB, or PostgreSQL
- Required PDO driver for the selected database

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database and frontend origins in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akademik_api
DB_USERNAME=root
DB_PASSWORD=
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173
```

Run migrations and start the API:

```bash
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

## Railway deployment (demo)

The live backend is deployed from this repository's `Dockerfile` alongside a Railway PostgreSQL service named `akademik-db`. The image includes both MySQL/MariaDB and PostgreSQL PDO drivers. It runs migrations when the container starts; it never generates `APP_KEY` or connects to the database during image build. Create `APP_KEY` locally with `php artisan key:generate --show`, and store it in Railway Variables. Do not commit a production `.env`.

Set these variables on the **API service**, using a Railway reference variable for `DB_URL` (select `akademik-db` → `DATABASE_URL` in the UI):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY
APP_URL=https://akademik-api-production-6aad.up.railway.app
DB_CONNECTION=pgsql
DB_URL=${{akademik-db.DATABASE_URL}}
CORS_ALLOWED_ORIGINS=https://akademik-frontend-1swy.vercel.app
LOG_CHANNEL=stderr
SESSION_DRIVER=cookie
CACHE_STORE=file
QUEUE_CONNECTION=sync
ACADEMIC_DEMO_SEED_COUNT=1000
```

Replace `APP_URL` and `CORS_ALLOWED_ORIGINS` if your deployed domains differ. The CORS value must be the exact frontend origin, without an API path or trailing slash. After deploying, verify that `/api/enrollments?page=1&page_size=2` returns JSON with `data` and `total`; an “Online” service badge alone does not prove the database connection works. `ACADEMIC_DEMO_SEED_COUNT=1000` seeds only when the enrollment table is empty. Remove it after the first deploy if preferred; **never seed five million rows on the small hosted database**. The frontend must set `VITE_API_BASE_URL` to this API's public URL plus `/api` and then rebuild.

For MySQL/MariaDB elsewhere, set `DB_CONNECTION=mysql` (or `mariadb`) and either `DB_URL` or the usual `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` variables. `render.yaml` remains in the repository as an alternative Render Blueprint, but it is **not used** by the Railway demo and provisions a separate Render database. The built-in PHP server is adequate for a short-lived assessment demo; use a proper PHP-FPM/web-server setup and authentication for sustained production traffic.

## Dataset generation

The scalable seeder accepts a target row count and insert batch size:

```bash
php artisan academic:seed 5000000 --chunk=5000
```

The command creates 10,000 students, 500 courses, and deterministic enrollment combinations. It uses bulk inserts, actual database IDs, disabled query logging, and bounded batches. The enrollment table must be empty; the command stops instead of silently creating duplicates.

Verify the result:

```sql
SELECT COUNT(*) FROM enrollments;
```

After a large seed on MySQL/MariaDB, refresh optimizer statistics before benchmarking list queries:

```sql
ANALYZE TABLE students, courses, enrollments;
```

For a lightweight development dataset, use a smaller count on a fresh database:

```bash
php artisan academic:seed 1000 --chunk=500
```

## Enrollment API

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/enrollments` | Paginated list, search, filter, and sort |
| POST | `/api/enrollments` | Atomic student + course + enrollment create |
| PUT | `/api/enrollments/{id}` | Update enrollment and optional related data |
| DELETE | `/api/enrollments/{id}` | Soft-delete enrollment only |
| GET | `/api/enrollments/export` | Stream all matching records as CSV |
| GET | `/api/enrollments/stats` | Status totals for the dashboard |

Student and course CRUD endpoints are available under `/api/students` and `/api/courses`.

The list endpoint returns Laravel's paginator shape (`data`, `current_page`, `per_page`, `total`, and related links), not a custom `success/meta` envelope. Validation errors use HTTP 422; successful enrollment creation uses HTTP 201.

### Query parameters

- `page`: page number
- `page_size`: 1–100 (default 25)
- `search`: NIM, student name, or course code
- `quick_status`, `quick_semester`: quick filters, always combined with AND
- `logic`: `AND` or `OR`
- `sorts[index][field|dir]`: ordered multi-column sorting
- `filters[index][field|operator|value]`: advanced conditions

Public fields and operators are explicitly whitelisted. Raw SQL and arbitrary database identifiers are never accepted.
Search and quick filters narrow the result first; `logic` applies only within the advanced `filters` group. This behavior is identical for list and CSV export. Live search resolves up to 1,000 matching student/course IDs into bounded lists for indexed enrollment lookups. Broader terms fall back to subqueries without loading an unbounded set of IDs into PHP memory.

Supported operators:

- `equal`
- `contains`
- `startsWith`
- `in` with an array value
- `between` with exactly two values

## Data integrity

Enrollment creation runs inside one `DB::transaction()`. A failure while creating the student, course, or enrollment rolls back the complete operation. The database also enforces uniqueness across:

```text
student_id + course_id + academic_year + semester
```

Enrollment deletion uses Laravel soft deletes and preserves student/course master records. Editing related master data from the enrollment form may affect other enrollments referencing the same record; the UI explicitly communicates this.

## Large CSV export

Export applies the same search and filter constraints as the list endpoint. It uses `lazyById()` in batches of 5,000, writes directly to `php://output`, disables proxy buffering, and escapes spreadsheet formula prefixes. It does not call `Enrollment::all()` or build the file in application memory.

## Tests and formatting

```bash
php artisan test
vendor/bin/pint --test
```

The supplied `phpunit.xml` uses an in-memory SQLite database. On Windows, if SQLite PDO is installed but not globally enabled:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit
```

## Performance notes

The schema includes foreign-key indexes, the enrollment uniqueness index, `(status, semester)`, and `(academic_year, semester)`. The active-row indexes `(deleted_at, status, semester)` and `(deleted_at, semester)` cover exact pagination counts for unfiltered and quick-filtered KRS lists. Pagination counts directly from `enrollments` when search and filters do not need student/course columns. For these queries, the API also pages enrollment IDs first, then joins only the current page's rows. Single-column sorting on indexed student/course fields uses a MySQL/MariaDB-specific ordered join so the database can start from the sorted master table instead of sorting millions of joined rows. Other database drivers and multi-column sorts use the portable joined query. Indexes on `courses.name` and `students.name` support these ordered joins; code, NIM, and email already have unique indexes. Check query plans again if data distribution or deployment database changes.
