# Schedule Application — Complete Overview (A to Z)

## 1. What is This Application?

**Purpose**: A Laravel-based university/school timetable management system that automates the creation and management of class schedules (emploi du temps) for an academic institution.

**Core Functionality**:
- Define academic structure: departments, programs (filières), semesters, modules (courses), student groups
- Register teachers and assign them to subjects within semesters
- Create/manage classrooms, time slots, and days
- **Auto-generate timetable sessions** (class slots) or allow manual creation
- Enforce constraints: no teacher/classroom/student group conflicts, capacity limits
- Role-based access control: admin, department heads, program heads, professors

**User Personas**:
- **Super Admin**: Full system access, can view/manage all institutions' data
- **Department Chef (chef_départment)**: Manages their department's programs and schedules
- **Program Chef (chef_filière)**: Manages their program's schedules
- **Professor (prof)**: Views only their own assigned teaching sessions
- **Sous Admin**: Supports admin with permissions

---

## 2. Data Model (Schema)

### Core Entities & Relationships

```
DEPARTMENT (1) ──→ (N) PROGRAM
PROGRAM (1) ──→ (N) SEMESTER
PROGRAM (1) ──→ (N) MODULE
PROGRAM (1) ──→ (N) SECTION

SEMESTER (1) ──→ (N) SUBJECT
SEMESTER (1) ──→ (N) STUDENT_GROUP
SEMESTER (1) ──→ (N) TIMETABLE_SESSION

TEACHER (1) ──→ (N) SUBJECT
TEACHER (1) ──→ (N) TIMETABLE_SESSION
TEACHER ──FK→ USER (login account, optional/nullable)

SUBJECT (1) ──→ (N) TIMETABLE_SESSION

SECTION (1) ──→ (N) STUDENT
SECTION (1) ──→ (N) TIMETABLE_SESSION

CLASSROOM (1) ──→ (N) TIMETABLE_SESSION

DAY (1) ──→ (N) TIMETABLE_SESSION
  (5 fixed days: Monday–Friday, position 1–5)

TIMESLOT (1) ──→ (N) TIMETABLE_SESSION
  (Fixed time ranges, e.g., 08:00–10:00, 10:00–12:00, etc.)

TIMETABLE_SESSION (central pivot)
  - Links: Subject, Teacher, Section, Classroom, Day, Timeslot, Semester
  - Unique constraint: (semester_id, day_id, timeslot_id, teacher_id) 
    → ensures no teacher teaches two places at same time
  - Also unique on: (semester_id, day_id, timeslot_id, classroom_id)
    → ensures no classroom is double-booked
  - Also unique on: (semester_id, day_id, timeslot_id, section_id)
    → ensures no student group is double-booked
```

### Table Breakdown

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| **departments** | Academic divisions | id, name, code |
| **programs** | Degree programs/filières | id, department_id, name, code |
| **semesters** | Academic periods per program | id, program_id, name, number (1–6) |
| **modules** | Subjects offered by a program in a semester | id, program_id, semester_id, name, code, weekly_hours |
| **student_groups** | Named cohorts of students per semester | id, semester_id, name, student_count |
| **teachers** | Academic staff records | id, user_id (FK, nullable), name, email, phone |
| **users** | Login accounts | id, name, email, role, password, department_id, program_id |
| **subjects** | Instance of a module taught by a teacher in a semester | id, semester_id, teacher_id, name, code, sessions_per_week |
| **sections** | Program subdivisions (groups of students) | id, program_id, name, capacity |
| **students** | Individual students in a section | id, section_id, name |
| **classrooms** | Physical/virtual spaces | id, name, capacity, type (classroom/lab/amphitheatre) |
| **days** | Weekdays | id, name (Monday–Friday), position (1–5) |
| **timeslots** | Fixed teaching time windows | id, starts_at (HH:MM), ends_at (HH:MM), position (1–N) |
| **timetable_sessions** | **Scheduled class instances** | id, subject_id, teacher_id, classroom_id, section_id, semester_id, day_id, timeslot_id |
| **professor_module** | M:N pivot (which professors can teach which modules) | professor_id, module_id |

---

## 3. Business Logic & Workflows

### 3.1 Manual Timetable Creation (Current Active System)

**User**: Department/Program Head or Admin

**Workflow**:
1. **Create Subjects**: For a semester, define which teachers teach which modules.
   - Input: Select semester, teacher, module → creates Subject record
   - Constraint: One teacher per subject per semester (for now)

2. **Create Sections**: Group students by program.
   - Input: Program, name, capacity → creates Section record

3. **Create Timetable Sessions** (Manual Entry):
   - Admin/Head fills form: select Subject, Section, Day, Timeslot, Classroom
   - System validates via `SessionConflictChecker`:
     - ✓ Teacher not already teaching at that day/timeslot?
     - ✓ Classroom not already booked at that day/timeslot?
     - ✓ Section not already scheduled at that day/timeslot?
     - ✓ Classroom capacity ≥ section size?
   - If valid → insert TimetableSession with unique constraints (DB enforces, UI pre-checks)
   - If conflict → reject with clear error message

### 3.2 Auto-Generation (Legacy System, Still Active)

**User**: Admin (via Dashboard → "Generate Schedules")

**Workflow** (via `ScheduleGenerator` service):
1. Takes minute-based input (custom start/end times, not just fixed slots)
2. For each TeachingSession:
   - Loops through possible (day, start_minute, end_minute) combinations
   - Checks `SessionConflictChecker` for minute-level overlaps
   - Respects: `professor_max_weekly_hours`, `student_group_max_daily_hours`
   - Creates `Schedule` record (a "version" of generated timetable)
   - Inserts `TimetableEntry` rows (minute-precision scheduling)

**Status**: Works, but **decoupled from TimetableSession** (creates its own data structure).

### 3.3 Access Control & Authorization

**Implemented via Middleware: `EnsureRole`** + **Manual Controller Checks**

**Rules**:

| Route | Super Admin | Chef Dept | Chef Filière | Prof | Sous Admin |
|-------|:----------:|:--------:|:----------:|:---:|:---------:|
| View all schedules | ✓ | ✓ | ✓ | ✗ (own only) | ✓ |
| Create/Edit sessions | ✓ | ✓ | ✓ | ✗ | ✓ |
| View own sessions | ✓ | ✓ | ✓ | ✓ | ✓ |
| Export timetable | ✓ | ✓ | ✓ | ✓ (own only) | ✓ |
| Manage users/roles | ✓ | ✗ | ✗ | ✗ | ✗ |

**Prof Access Restriction** (Recent Fix):
- `GET /schedules/{schedule}` now checks:
  - If user is `prof` → must have ≥1 TimetableSession in that schedule
  - Rows displayed filtered to prof's sessions only
  - Returns 403 Forbidden if no sessions for this prof

---

## 4. API Routes & Controllers

### Main Routes (web.php)

```php
// CRUD for each resource (via generic CrudController or dedicated ones)
Route::resource('departments', DepartmentController);
Route::resource('programs', ProgramController);
Route::resource('semesters', SemesterController);
Route::resource('modules', ModuleController);
Route::resource('teachers', TeacherController);
Route::resource('classrooms', ClassroomController);
Route::resource('days', SchoolDayController);
Route::resource('timeslots', TimeslotController);

// Timetable Sessions (Day/Timeslot based, unified system)
Route::resource('timetable/sessions', TimetableSessionController);

// Schedules (Minute-based, legacy auto-generation)
Route::resource('schedules', ScheduleController);
Route::post('/schedules/generate', [DashboardController::class, 'generate'])->name('schedules.generate');

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/schedules/{schedule}', [DashboardController::class, 'show'])->name('schedules.show');

// Auth (Laravel default)
Route::middleware('auth')->group([...]);
```

### Key Controllers

| Controller | Responsibility |
|------------|---|
| `DashboardController` | Homepage, schedule view (with access control), auto-generation trigger |
| `TimetableSessionController` | CRUD timetable sessions (manual entry, with conflict validation) |
| `CrudController` | Generic CRUD for simple resources (departments, classrooms, etc.) |
| `ScheduleController` | Manage legacy Schedule records |

---

## 5. Key Services (Business Logic)

### `SessionConflictChecker`
**Purpose**: Validates a single timetable session before creation.

**Checks**:
- Teacher availability (not teaching elsewhere at same day/timeslot)
- Classroom availability (not booked elsewhere at same day/timeslot)
- Student group availability (not scheduled elsewhere at same day/timeslot)
- Classroom capacity ≥ section size

**Returns**: Boolean (valid/invalid) + error messages

### `ScheduleGenerator`
**Purpose**: Auto-generate minute-based schedules (legacy system).

**Algorithm**:
1. Reads `TeachingSession` records (module, teacher, student_group, semester)
2. For each session, tries combinations of (day, start_minute, end_minute)
3. Applies conflict checking + hour limits
4. Writes `TimetableEntry` rows under a `Schedule` record
5. Returns conflict summary if generation incomplete

**Constraints Applied**:
- `professor_max_weekly_hours` (max hours/week per teacher)
- `student_group_max_daily_minutes` (max minutes/day per group)
- `professor_availabilities` (blackout times if defined)

### `ProfessorModuleEligibility`
**Purpose**: Check if a professor is authorized to teach a module (via `professor_module` pivot table).

**Usage**: Can be integrated into validation flow to prevent assigning unauthorized teachers.

---

## 6. Constraints & Validations

### Database Constraints (Enforced at DB Level)

**Unique Constraints on `timetable_sessions`**:
```sql
UNIQUE(semester_id, day_id, timeslot_id, teacher_id)
UNIQUE(semester_id, day_id, timeslot_id, classroom_id)
UNIQUE(semester_id, day_id, timeslot_id, section_id)
```
→ Prevents double-booking at the DB level.

### Application-Level Validations

**Forms/Controllers check**:
- Required fields (subject, section, day, timeslot, classroom)
- Classroom capacity ≥ section size
- Teacher/classroom/section not already booked at that slot
- No duplicate entries

---

## 7. Current State & Known Issues

### What's Working
✅ Role-based access control (middleware + controller checks)
✅ Manual timetable session CRUD with conflict checking
✅ Auto-generation of minute-based schedules (legacy system)
✅ Admin dashboard with schedule display
✅ Prof access restriction (only view own sessions)

### What's Pending / To Refactor
⚠️ **Two Scheduling Systems in Parallel**:
   - `TimetableSession` (day/timeslot, unified, active in routes)
   - `ScheduleGenerator` → `TimetableEntry` (minute-based, legacy, still functional)
   - **Plan**: Merge `ScheduleGenerator` to write `TimetableSession` instead of `TimetableEntry`

❌ **No Tests**: `ScheduleGenerator`, `SessionConflictChecker` lack unit tests

⚠️ **Generic CrudController**: Whitelist of resources is hardcoded; needs review for mass-assignment safety

⚠️ **No Export Feature**: No PDF/Excel export of timetables yet

⚠️ **No Notifications**: Professors not notified of schedule changes

---

## 8. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Web Browser (UI)                         │
│                  (Blade Templates)                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   Laravel Routes                            │
│  (web.php → Controllers → Middleware:EnsureRole)           │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
    ┌───▼────┐   ┌────▼────┐   ┌────▼────┐
    │  CRUD  │   │Timetable│   │Dashboard│
    │        │   │ Session │   │         │
    └────────┘   │Controller   │Generator│
                 └────┬────────┘   └────┬────┘
                      │                 │
        ┌─────────────▼─────────────────▼──────┐
        │      Business Logic Services         │
        │  - SessionConflictChecker             │
        │  - ScheduleGenerator (legacy)         │
        │  - ProfessorModuleEligibility         │
        └─────────────┬────────────────────────┘
                      │
        ┌─────────────▼─────────────────────────┐
        │        Eloquent ORM / Models          │
        │  (User, Teacher, Subject, Semester...) │
        └─────────────┬─────────────────────────┘
                      │
        ┌─────────────▼─────────────────────────┐
        │         MySQL Database                │
        │  (Timetable_sessions with unique      │
        │   constraints, referential integrity) │
        └───────────────────────────────────────┘
```

---

## 9. Development Workflow

### To Run the App Locally

```bash
# Clone repo
git clone https://github.com/elhassandouki/schedule.git
cd schedule

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate:fresh --seed
  # (Creates demo data: departments, programs, semesters, subjects, timetable_sessions)

# Start dev server
php artisan serve
npm run dev  # (Vite for frontend assets)
```

### Default Demo Credentials (after `--seed`)
```
Email: admin@school.local        (super_admin) | Password: password
Email: alice@school.local        (prof)        | Password: password
Email: bob@school.local          (prof)        | Password: password
Email: chef@school.local         (chef_dept)   | Password: password
```

### Testing Access Control
```bash
# Login as prof (alice@school.local)
# Try to access /schedules/1 (if you're assigned a session there) → ✓ allowed
# Try to access /schedules/2 (if you have no sessions) → 403 Forbidden

# Login as admin → view all schedules ✓
```

---

## 10. Future Enhancements

1. **Unify Scheduling Systems**: Refactor `ScheduleGenerator` to use `TimetableSession` + day/timeslot model (instead of minutes)
2. **Add Tests**: PHPUnit coverage for generators & conflict checkers
3. **Export Features**: PDF/Excel timetable exports
4. **Student Portal**: Students view their own schedule (read-only)
5. **Conflict Resolution UI**: When auto-generation fails, suggest resolutions
6. **Notifications**: Email/SMS alerts on schedule changes
7. **API Endpoints**: REST API for mobile app / external integrations
8. **Performance**: Optimize queries for large institutions (1000+ sessions, 100+ teachers)

---

## 11. Key Files to Know

| File | Purpose |
|------|---------|
| `app/Models/TimetableSession.php` | Core model for unified system |
| `app/Services/SessionConflictChecker.php` | Validates single sessions |
| `app/Services/ScheduleGenerator.php` | Auto-generates minute-based schedules (legacy) |
| `app/Http/Controllers/TimetableSessionController.php` | CRUD for sessions |
| `app/Http/Middleware/EnsureRole.php` | Role-based authorization |
| `database/migrations/` | Schema definitions |
| `database/seeders/DemoTimetableSeeder.php` | Demo data generation |
| `routes/web.php` | All routes |
| `resources/views/` | Blade templates |

---

**Version**: v1.0 (Post-unification phase)
**Last Updated**: August 2026
**Maintainer**: Development Team
