🎓 Enrollment Academic System
Full-stack Academic Enrollment Management System built using:
Backend: Laravel (PHP 8.4.12)
Frontend: React + Vite + TypeScript
Database: PostgreSQL
Styling: Tailwind CSS
This project was developed as a technical test to demonstrate scalable backend architecture, efficient database design, and clean frontend implementation.

🚀 Overview
Enrollment Academic System is designed to manage:
Students
Courses
Enrollments
Real-time Dashboard Analytics
CSV Export for large datasets
The system supports large-scale data (millions of enrollment records) with optimized query performance.

🛠 Tech Stack
Backend :
Laravel
PHP 8.4.12
PostgreSQL
Eloquent ORM
Database Transactions
Chunked CSV Streaming
Advanced Filtering Engine

Frontend :
React (Vite)
TypeScript
Tailwind CSS
Recharts (Data Visualization)
React Icons
Axios

✨ Features
<img width="1919" height="1086" alt="image" src="https://github.com/user-attachments/assets/eed3c1cb-66f8-403a-9119-84743a307eeb" />
📊 Dashboard
Total Enrollment count
Approved / Draft / Rejected / Submitted stats
Pie chart status distribution
Real-time stats API

<img width="1918" height="1075" alt="image" src="https://github.com/user-attachments/assets/d37510f4-4409-44e2-b049-7e09e11680cd" />
📋 Enrollment Management
Server-side pagination
Advanced filtering (Status, Semester)
Global search (NIM, Student Name, Course Code)
Dynamic sorting per column
Create enrollment (new or existing Student/Course)
Edit enrollment
Soft delete
CSV export (streamed for performance)

<img width="1919" height="1087" alt="image" src="https://github.com/user-attachments/assets/2736a5ac-8211-4b02-a056-6600499eb2c8" />
👨‍🎓 Student Management
Full CRUD
Search by NIM / Name / Email
Modal-based create/edit
Pagination

<img width="1919" height="1082" alt="image" src="https://github.com/user-attachments/assets/21352218-f0bf-4845-8b1c-2ad40042c916" />
📚 Course Management
Full CRUD
Search by Code / Name
Modal-based create/edit
Pagination

🧪 API Endpoints
Enrollment
GET    /api/enrollments
POST   /api/enrollments
PUT    /api/enrollments/{id}
DELETE /api/enrollments/{id}
GET    /api/enrollments/export
GET    /api/enrollments/stats

Students
GET    /api/students
POST   /api/students
PUT    /api/students/{id}
DELETE /api/students/{id}
GET    /api/students/search

Courses
GET    /api/courses
POST   /api/courses
PUT    /api/courses/{id}
DELETE /api/courses/{id}
GET    /api/courses/search

🔧 Installation Guide
Backend Setup
composer install
cp .env.example .env
php artisan key:generate

Update .env for PostgreSQL:
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

Run migration & seeder:
php artisan migrate --seed

Start backend:
php artisan serve

Frontend Setup
npm install
npm run dev

📊 CSV Export
Streamed response
Memory efficient
Handles large datasets
Chunked database reading

🔐 Data Integrity Strategy
Enrollment creation logic:
If student_id exists → use existing student
If not → auto create/update student
Same logic applied to course
All wrapped in database transaction
Ensures:
No duplicate records
Atomic operations
Data consistency
