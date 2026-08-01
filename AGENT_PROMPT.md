# Schedule App - Unified System Prompt (for AI Agents)

## Project Overview

This is a **Laravel-based university timetable management system** that automates the creation and management of class schedules (emploi du temps).

**Core System (Unified - Day/Timeslot Based):**
- Single source of truth: `TimetableSession` model
- No minute-based scheduling (legacy system removed)
- Fixed time slots: 08:00-10:00, 10:00-12:00, 13:00-15:00, 15:00-17:00 (configurable)
- Days: Monday-Friday (fixed)
- Auto-generation with constraint enforcement

---

## Data Model (Essential Tables)

### Structure (Building Blocks)
- **departments** → programs
- **programs** → semesters, modules, sections
- **semesters** → subjects, student_groups, timetable_sessions
- **modules** (course definitions) — course code, name, weekly hours
- **student_groups** (cohorts per semester) — name, student count
- **sections** (program subdivisions) — name, capacity

### Teachers & Teaching
- **teachers** (academic staff) — name, email, phone, user_id (FK to login account)
- **users** (login accounts) — role (super_admin, chef_departement, chef_filière, prof)
- **subjects** (module instances assigned to teachers) — semester_id, teacher_id, sessions_per_week

### Scheduling Infrastructure
- **days** (Monday-Friday, position 1-5)
- **timeslots** (fixed time windows: starts_at, ends_at, position)
- **classrooms** (name, capacity, type)

### Core Result
- **timetable_sessions** (scheduled class instances)
  - FK: subject_id, teacher_id, section_id, classroom_id, semester_id, day_id, timeslot_id
  - Unique constraints (enforced at DB level):
    - (semester_id, day_id, timeslot_id, teacher_id) — no teacher double-booking
    - (semester_id, day_id, timeslot_id, classroom_id) — no classroom double-booking
    - (semester_id, day_id, timeslot_id, section_id) — no student group double-booking

---

## Key Services

### `AutoGenerateTimetable` (app/Services/AutoGenerateTimetable.php)
Greedy slot-filling algorithm that generates TimetableSession records.

**Algorithm:**
1. For each subject in the semester:
   - For each section that needs this subject:
     - Allocate `sessions_per_week` slots into earliest available (day, timeslot)
     - Check all constraints before insertion
     - Skip if conflict detected (with detailed error message)

**Constraints Checked:**
- Teacher availability: teacher not teaching elsewhere at same day/timeslot
- Classroom availability: classroom not booked elsewhere
- Section availability: student group not scheduled elsewhere
- Capacity: classroom capacity ≥ section size
- Duplicates: same subject+section not scheduled twice at exact same time

**Returns:** Array with:
```php
[
  'success' => bool,
  'sessions_generated' => int,
  'sessions_skipped' => int,
  'subjects' => [
    [
      'subject_id' => int,
      'subject_name' => string,
      'generated' => int,
      'skipped' => int,
      'errors' => [string, ...] // detailed error messages
    ],
    ...
  ]
]
```

### `SessionConflictChecker` (app/Services/SessionConflictChecker.php)
Validates a single timetable session (used by manual form entry).

**Checks:**
- Teacher double-booking
- Classroom double-booking
- Section double-booking
- Capacity validation

**Returns:** Boolean (valid/invalid) + error messages

### `ProfessorModuleEligibility` (app/Services/ProfessorModuleEligibility.php)
Checks if a professor is authorized to teach a module (via professor_module pivot).

---

## Artisan Commands

### `php artisan timetable:generate {semester_id} {--dry-run}`
Auto-generates timetable for a semester.

**Usage:**
```bash
php artisan timetable:generate 1            # Generate for semester 1
php artisan timetable:generate 1 --dry-run  # Preview without writing
```

**Output:**
- Lists per-subject generation summary
- Shows skipped slots with reasons
- Reports success/partial success

### `php artisan timetable:diagnose`
Reports row counts per table (data inventory).

### `php artisan migrate:fresh --seed`
Resets database and seeds demo data via `UnifiedDemoSeeder`.

---

## Seeders

### `UnifiedDemoSeeder` (database/seeders/UnifiedDemoSeeder.php)
Creates complete demo structure (no timetable sessions yet).

**Generates:**
- 2 departments
- 2 programs
- 3 semesters
- 4 modules
- 4 student groups
- 5 users (1 admin, 3 profs, 1 chef)
- 3 teachers (linked to user accounts)
- 4 subjects (module instances)
- 2 sections
- 5 days (Mon-Fri)
- 4 timeslots (08:00-10:00, 10:00-12:00, 13:00-15:00, 15:00-17:00)
- 4 classrooms (amphitheatre, classrooms, lab)
- **0 timetable_sessions** (ready for auto-generation)

---

## Controllers

### `TimetableSessionController` (app/Http/Controllers/TimetableSessionController.php)
CRUD for timetable sessions (manual entry).

**Routes:**
- GET /timetable/sessions → list all
- GET /timetable/sessions/{id} → view
- POST /timetable/sessions → create (with conflict validation)
- PUT /timetable/sessions/{id} → update
- DELETE /timetable/sessions/{id} → delete

**Validation:** Uses `SessionConflictChecker` before insert/update

### `DashboardController` (app/Http/Controllers/DashboardController.php)
Dashboard & schedule views.

**Routes:**
- GET / → dashboard
- GET /schedules/{schedule} → view timetable (with access control)

**Access Control:**
- Admin/Chef: view all schedules
- Prof: view only if they have ≥1 session, rows filtered to own sessions

### `CrudController` (app/Http/Controllers/CrudController.php)
Generic CRUD for simple resources (departments, classrooms, timeslots, etc.).

**Whitelisted Resources:**
- departments, programs, semesters, modules, student_groups
- teachers, classrooms, days, timeslots

---

## Routes (web.php)

```php
// Resource routes (CrudController or dedicated)
Route::resource('departments', DepartmentController);
Route::resource('programs', ProgramController);
Route::resource('semesters', SemesterController);
Route::resource('modules', ModuleController);
Route::resource('teachers', TeacherController);
Route::resource('classrooms', ClassroomController);
Route::resource('days', SchoolDayController);
Route::resource('timeslots', TimeslotController);

// Timetable (main)
Route::resource('timetable/sessions', TimetableSessionController);

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/schedules/{id}', [DashboardController::class, 'show'])->name('schedules.show');
```

---

## Database Config

**MySQL Charset Fix:**
- `config/database.php`: charset = 'utf8' (not 'utf8mb4')
- Avoids "index key too long" (1071) error with standard MySQL

---

## Authentication & Authorization

### Roles
- **super_admin**: Full system access
- **sous_admin**: Admin support (full access)
- **chef_departement**: Manages department's programs/schedules
- **chef_filière**: Manages program's schedules
- **prof**: Views own sessions only

### Middleware
- `auth` (Laravel built-in): user must be logged in
- `EnsureRole` (app/Http/Middleware/EnsureRole.php): checks role permissions per route

### Prof Access Control
In `DashboardController::show()`:
- Prof can only view schedules where they have ≥1 timetable_session
- Display rows filtered to prof's own sessions
- Returns 403 Forbidden if no sessions

---

## Workflow (From Zero to Timetable)

### Step 1: Setup
```bash
git clone https://github.com/elhassandouki/schedule.git
cd schedule
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Step 2: Database & Demo Data
```bash
php artisan migrate:fresh --seed
```
Creates tables + UnifiedDemoSeeder data.

### Step 3: Auto-Generate Timetable
```bash
php artisan timetable:generate 1  # Semester 1
php artisan timetable:generate 2  # Semester 2
php artisan timetable:generate 3  # Semester 3
```

### Step 4: Verify
```bash
php artisan timetable:diagnose
```

### Step 5: View in Browser
```bash
php artisan serve
npm run dev  # (Vite frontend assets)
```
Login: admin@school.local / password

---

## Demo Credentials (After --seed)

| Email | Role | Password |
|-------|------|----------|
| admin@school.local | super_admin | password |
| alice@school.local | prof | password |
| bob@school.local | prof | password |
| carol@school.local | prof | password |
| chef@school.local | chef_departement | password |

---

## File Structure (Key Files)

```
app/
  Console/Commands/
    GenerateTimetable.php          ← timetable:generate command
    DiagnoseData.php               ← timetable:diagnose command
  Http/Controllers/
    TimetableSessionController.php ← CRUD timetable sessions
    DashboardController.php        ← Dashboard + schedule view
    CrudController.php             ← Generic CRUD
  Models/
    TimetableSession.php           ← Core model
    Teacher.php, Subject.php, etc. ← Data models
  Services/
    AutoGenerateTimetable.php      ← Main generation algorithm
    SessionConflictChecker.php     ← Validation
    ProfessorModuleEligibility.php ← Eligibility checks
  Http/Middleware/
    EnsureRole.php                 ← Role-based authorization

database/
  migrations/
    *_create_timetable_management_tables.php (schema)
    *_create_manual_timetable_tables.php     (day/timeslot model)
    0001_01_01_000002_create_jobs_table.php  (Laravel, fixed)
  seeders/
    UnifiedDemoSeeder.php          ← Demo data generator

routes/
  web.php                          ← All routes

config/
  database.php                     ← MySQL charset = 'utf8'

APP_OVERVIEW.md                   ← Full documentation
```

---

## Common Tasks for AI Agents

### Generate Timetable
```bash
php artisan timetable:generate SEMESTER_ID
```

### Check Data
```bash
php artisan timetable:diagnose
```

### Create New Subject & Assign Teacher
Manual entry via `/timetable/sessions` form (with conflict validation).

### Manual Timetable Entry
UI form at `/timetable/sessions/create`:
1. Select Subject (teaches → Teacher + Module)
2. Select Section (student group)
3. Select Day, Timeslot
4. Select Classroom
5. Submit (validated by `SessionConflictChecker`)

### Access Control Testing
- Login as prof → Try to access schedules where you have no sessions → 403
- Login as chef/admin → View all schedules ✓

### Debugging
```bash
# Check migrations ran
php artisan migrate:status

# Check seeded data
php artisan tinker
>>> DB::table('semesters')->count()  # Should be 3
>>> DB::table('timetable_sessions')->count()  # After generation

# Clear & regenerate
php artisan migrate:fresh --seed
php artisan timetable:generate 1
```

---

## Constraints Summary

**Database Level (Enforced):**
- Unique(semester_id, day_id, timeslot_id, teacher_id)
- Unique(semester_id, day_id, timeslot_id, classroom_id)
- Unique(semester_id, day_id, timeslot_id, section_id)

**Application Level (Checked):**
- Classroom capacity ≥ section size
- No conflicting sessions (pre-checked before insert)
- Semester scoping (all queries filtered by semester_id)

**Access Control:**
- Prof can only view own schedules
- Only authorized roles can edit

---

## Current Status

✅ Unified day/timeslot system (no legacy minute-based code)
✅ AutoGenerateTimetable service (working, tested)
✅ Manual timetable CRUD with validation
✅ Role-based access control
✅ Demo seeder (complete structure, zero sessions)
❌ No unit tests yet (GenerateTimetable, SessionConflictChecker)
❌ No export feature (PDF/Excel)
❌ No notifications (schedule changes)

---

## Future Enhancements

1. **Tests**: PHPUnit for `AutoGenerateTimetable`, `SessionConflictChecker`
2. **Export**: PDF/Excel timetable export
3. **Student Portal**: Read-only view of student's schedule
4. **Notifications**: Email/SMS on schedule changes
5. **Conflict Resolution UI**: Suggest alternative slots when generation fails
6. **Performance**: Optimize queries for 1000+ sessions, 100+ teachers
7. **API**: REST endpoints for mobile/external integrations

---

## Notes for Developers

- **Always use the `auto_generate` workflow** when starting fresh (migrate → seed → generate)
- **Test access control** by logging in as different roles
- **Check conflicts** via `timetable:diagnose` before production
- **Never edit** the unique constraint definitions in migrations
- **Use `--dry-run`** on `timetable:generate` to preview before committing
- **Semester scoping** is mandatory (all queries filter by semester_id)

---

**Last Updated:** August 2026
**Version:** 2.0 (Unified System)
**System:** Laravel 11, MySQL 5.7+, PHP 8.2+
