# Academic Enrollment API

Laravel 12 API for a single-page academic KRS management system. The API is designed for server-side pagination, sorting, searching, advanced filtering, atomic enrollment creation, and large CSV exports.

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

### Query parameters

- `page`: page number
- `page_size`: 1–100
- `search`: NIM, student name, or course code
- `logic`: `AND` or `OR`
- `sorts[index][field|dir]`: ordered multi-column sorting
- `filters[index][field|operator|value]`: advanced conditions

Public fields and operators are explicitly whitelisted. Raw SQL and arbitrary database identifiers are never accepted.

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

The schema includes foreign-key indexes, the enrollment uniqueness index, `(status, semester)`, and `(academic_year, semester)`. The active-row indexes `(deleted_at, status, semester)` and `(deleted_at, semester)` cover exact pagination counts for unfiltered and quick-filtered KRS lists. Pagination counts directly from `enrollments` when search and filters do not need student/course columns. For these queries, the API also pages enrollment IDs first, then joins only the current page's rows; this avoids an optimizer plan that sorts millions of joined rows for a 25-row page. Related-field search, filters, and sorts retain the joined query for correct results. Add further indexes only after inspecting real 5-million-row query plans with `EXPLAIN ANALYZE`; unnecessary indexes increase seed time, storage, and write cost.
