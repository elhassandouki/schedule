# 🔴 ARCHITECTURAL AUDIT — CRITICAL ISSUES

**Date**: August 6, 2026  
**Severity**: CRITICAL  
**Status**: Application Cannot Run

---

## 1. FATAL ERROR — Missing Models & Services

### DashboardController Imports Non-Existent Classes

**File**: `app/Http/Controllers/DashboardController.php`

```php
use App\Models\Schedule;           // ❌ DOES NOT EXIST
use App\Services\ScheduleGenerator; // ❌ DOES NOT EXIST
```

**What Exists**:
```
✅ Models:           (13 total)
   Classroom, Department, Module, Program, SchoolDay, Section, 
   Semester, Student, StudentGroup, Subject, Teacher, Timeslot, 
   TimetableSession, User

❌ Missing:          Schedule

✅ Services:         (5 total)
   AutoGenerateTimetable, ProfessorModuleEligibility, 
   SessionConflictChecker, TimetableConflictValidator, 
   TimetableQualityAnalyzer

❌ Missing:          ScheduleGenerator
```

**Impact**: 🔴 **CRITICAL** — App won't boot. PHP fatal error on require.

**Fix**: Either:
- A) Create the missing Schedule model & ScheduleGenerator
- B) Migrate DashboardController to use new system (AutoGenerateTimetable + TimetableSession)

---

## 2. TWO SEPARATE SCHEDULING SYSTEMS IN DATABASE

### System 1 — OLD (Minute-Based)

**Created by**: Migration `2026_07_24_000003_create_timetable_management_tables.php`

Tables:
- `schedules` — Container for a timetable version
- `timetable_entries` — Individual scheduled sessions (minute-based)
  - `start_minute` (e.g., 480 = 8:00 AM)
  - `end_minute` (e.g., 600 = 10:00 AM)
- `teaching_sessions` — Academic sessions (not for scheduling)
- `professor_availabilities` — Teacher availability (minute-based)

```
Schedule
  ├─ timetable_entries[*]
  │  ├─ start_minute
  │  ├─ end_minute
  │  └─ teaching_session_id
  └─ semester_id
```

### System 2 — NEW (Slot-Based)

**Created by**: Migration `2026_07_27_000005_create_manual_timetable_tables.php`

Tables:
- `timetable_sessions` — The actual scheduled lessons
  - Foreign keys: subject, teacher, classroom, section, timeslot, day
  - NO minute-based times (uses day_id + timeslot_id)
- `timeslots` — Time definitions (08:00-10:00, 10:00-12:00, etc.)
- `days` — Day definitions (Monday, Tuesday, etc.)
- `teachers` — Different from `users` (not linked initially)
- `subjects` — Different from `modules`
- `sections` — Different from `student_groups`

```
TimetableSession
  ├─ semester_id
  ├─ subject_id ──────→ Subject
  ├─ teacher_id ──────→ Teacher
  ├─ classroom_id ─────→ Classroom
  ├─ section_id ──────→ Section
  ├─ day_id ──────────→ Day
  └─ timeslot_id ─────→ Timeslot
```

### The Problem

**Both systems exist simultaneously in the same database!**

| Concern | Old System | New System |
|---------|-----------|-----------|
| **Model** | (none - legacy) | TimetableSession ✓ |
| **Time Format** | Minute-based | Slot-based |
| **Teacher Link** | `users` (professor_id) | `teachers` table |
| **Module Link** | `teaching_sessions.module_id` | `subjects.name` + link |
| **Student Link** | `teaching_sessions.student_group_id` | `sections` + `section_id` |
| **Routes** | `schedules.generate`, `schedules.show` | None (only manual CRUD) |
| **Generator** | `ScheduleGenerator` (missing) | `AutoGenerateTimetable` ✓ |

**Result**: 🔴 Inconsistent data, confusing schema, no unified source of truth

---

## 3. DUPLICATE CONFLICT VALIDATION LOGIC

### Problem: Two Conflict Checkers

```
SessionConflictChecker      TimetableConflictValidator
         ↓                               ↓
    ??? (unclear what)          ??? (unclear what)
         ↑                               ↑
         └───────────┬───────────┘
                    Both validate similar things?
```

**Files**:
- `app/Services/SessionConflictChecker.php`
- `app/Services/TimetableConflictValidator.php`

**Question**: Do they check the same thing? Different things? Why both exist?

**Impact**: 🟠 **HIGH** — Business logic duplication, maintenance nightmare

---

## 4. DATA MODEL AMBIGUITY

### Overlapping Concepts

```
Module (from academic_years migration)
   ↓
   "A course or subject offered in a program"
   
Subject (from new system)
   ↓
   "An instance of a module taught by a teacher in a semester"

StudentGroup (from academic_years migration)
   ↓
   "A group of students in a semester"
   
Section (from new system)
   ↓
   "A classroom group?" OR "Same as StudentGroup?"
```

**Question**: What's the difference between:
- `Module` vs `Subject`?
- `StudentGroup` vs `Section`?
- `Teacher` (new table) vs `Professor` (users with role='prof')?

**Impact**: 🟠 **MEDIUM** — Confusion when extending features

---

## 5. INCONSISTENT TEACHER REFERENCES

### Problem: Multiple Ways to Reference Teachers

```
users (role='prof')
  ├─ id = 5
  └─ name = "Dr. Alice"

teachers
  ├─ id = 3
  ├─ name = "Dr. Alice"
  └─ user_id = NULL (initially, maybe added later?)
```

**Question**: Are they the same person?  
**Current State**: Probably not linked properly

**Impact**: 🔴 **CRITICAL** — Can't map scheduled sessions to actual users

---

## 6. MISSING UNIQUE CONSTRAINTS (Old System)

### `timetable_entries` Has No Conflict Prevention

The OLD system (`timetable_entries`) has NO UNIQUE constraints to prevent:

```
❌ Teacher double-booking (teacher A teaching 2 classes at same time)
❌ Classroom conflicts (room 101 in 2 places at once)
❌ Student group conflicts (group L3-A in 2 classes at once)
```

The NEW system (`timetable_sessions`) has them correctly:
```
✅ UNIQUE(teacher_id, semester_id, day_id, timeslot_id)
✅ UNIQUE(classroom_id, semester_id, day_id, timeslot_id)
✅ UNIQUE(section_id, semester_id, day_id, timeslot_id)
```

**Impact**: 🔴 **CRITICAL** — Old system is unreliable

---

## 7. GENERIC CRUD CONTROLLER RISKS

### Problem: One Controller for Everything

**File**: `app/Http/Controllers/CrudController.php`

Handles:
- Departments
- Programs
- Semesters
- Modules
- Classrooms
- Student Groups
- Teachers
- Subjects
- Sections
- ... more?

**Method**: Configuration array + generic create/edit/delete logic

```php
private function resources(): array {
    return [
        'departments' => [...],
        'programs' => [...],
        'semesters' => [...],
        // ...
    ];
}
```

**Risk**: Each entity has different business rules!

Example:
- Deleting a `Department` should cascade to `Programs` ✓ (maybe)
- Deleting a `Teacher` with scheduled sessions should... block? soft-delete?
- Deleting a `Semester` with generated timetables should... prevent or archive?

**Impact**: 🟠 **MEDIUM** — Works for simple CRUD, breaks with complex rules

---

## 8. ROUTES WITHOUT MODELS

### Routes Reference Missing Models

```php
// routes/web.php
Route::post('/schedules/generate', [DashboardController::class, 'generate'])
Route::get('/schedules/{schedule}', [DashboardController::class, 'show'])
```

But:
- `$schedule` parameter expects `App\Models\Schedule` (implicit route model binding)
- Model doesn't exist!

**Impact**: 🔴 **CRITICAL** — Routes will 404 or crash

---

## 9. MISSING TESTS

**Current state**: Only 54 tests exist (mostly quality report tests)

**Missing**:
- AutoGenerateTimetable algorithm tests
- SessionConflictChecker tests
- Cross-system interaction tests
- Authorization tests (actual data isolation)
- Data seeder integrity tests

**Impact**: 🔴 **CRITICAL** — No confidence in system behavior

---

## 10. MIGRATION EVOLUTION MESS

```
14 migrations, showing incremental schema changes:

000003: Create academic structure + old scheduling system
000004: Add constraints
000005: Create NEW scheduling system (completely different!)
000006-000014: Fix relationships, add columns, adjust constraints
```

**Problem**: Schema changed drastically mid-development

**Result**: Complex migration path, hard to understand "current truth"

---

## 11. DOCUMENTATION VS CODE MISMATCH

**APP_OVERVIEW.md says**:
- "TimetableSession is the single source of truth"
- "AutoGenerateTimetable is the generation engine"

**Code actually has**:
- `schedules` table (old system)
- `timetable_entries` table (old system)
- `Schedule` model (missing, referenced by controller)
- `ScheduleGenerator` service (missing, referenced by controller)
- `TimetableSession` table (new system)
- `AutoGenerateTimetable` service (new system, unused?)

**Result**: 🔴 Docs describe a different system than what's implemented

---

## Summary Table

| Issue | Type | Severity | Impact |
|-------|------|----------|--------|
| Schedule model doesn't exist | Code | 🔴 Critical | App won't boot |
| ScheduleGenerator doesn't exist | Code | 🔴 Critical | App won't boot |
| Two scheduling systems | Architecture | 🔴 Critical | Data inconsistency |
| Duplicate conflict checkers | Code | 🟠 Medium | Maintenance hell |
| Ambiguous data model | Design | 🟠 Medium | Confusion |
| Teacher table not linked to users | Data | 🔴 Critical | Can't map schedules |
| Old system has no constraints | Design | 🔴 Critical | Unreliable |
| Generic CRUD for complex entities | Code | 🟠 Medium | Fragile |
| Routes reference missing models | Routing | 🔴 Critical | 404/Crashes |
| No tests for core logic | Testing | 🔴 Critical | No confidence |
| Migration mess | Database | 🟠 Medium | Hard to understand |
| Docs vs code mismatch | Documentation | 🟠 Medium | Confusion |

---

## What Needs to Happen

### Phase 1: Fix Critical Bugs (TODAY)
- [ ] Create `Schedule` model (or remove its usage)
- [ ] Create `ScheduleGenerator` service (or replace with `AutoGenerateTimetable`)
- [ ] Link `teachers.user_id` to `users.id` properly
- [ ] Fix route model binding

### Phase 2: Choose One System (THIS WEEK)
- [ ] Decide: Use NEW system (TimetableSession) or OLD system (Schedule/TimetableEntry)?
- [ ] Delete the other
- [ ] Consolidate everything to one approach

### Phase 3: Unify Business Logic (NEXT WEEK)
- [ ] Merge `SessionConflictChecker` and `TimetableConflictValidator`
- [ ] Decide on Module vs Subject, StudentGroup vs Section
- [ ] Create single source of truth for all validation

### Phase 4: Add Tests (AFTER PHASE 3)
- [ ] Test conflict detection
- [ ] Test generation algorithm
- [ ] Test authorization
- [ ] Test data isolation

---

## Recommended Path Forward

**QUICK DECISION**:

The NEW system (TimetableSession) is clearly superior:
- ✅ Better constraint design
- ✅ Cleaner data model
- ✅ Has conflict prevention
- ✅ Already has services built

**ACTION**:
1. Delete all OLD system references (Schedule, TimetableEntry, ScheduleGenerator)
2. Create proper `Schedule` model if you want generation history
3. Use `AutoGenerateTimetable` for all generation
4. Migrate DashboardController to use new system

**Time estimate**: 4-6 hours

---

**Current State**: 🔴 **App does not work**  
**Next Priority**: Fix missing models so it boots  
**Then**: Choose and consolidate systems
