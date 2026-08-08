# Guide: Auto-Generate Timetables

## Overview

The system auto-generates timetables based on data you create:
1. **You create**: Subjects, Teachers, Sections, Classrooms, Days, Timeslots
2. **System generates**: TimetableSession records automatically

## Prerequisites

### 1. Run Database Setup
```bash
php artisan migrate:fresh --seed
```

This creates:
- ✅ All tables
- ✅ Sample users (admin, prof1, prof2, etc.)
- ✅ Academic structure (departments, programs, semesters)
- ✅ Time definitions (days, timeslots)
- ✅ Classrooms
- ✅ Teachers
- ✅ Subjects (with teacher assignments)
- ✅ Sections (student groups)

### 2. Verify Data Exists
```bash
php artisan timetable:check-ready {semester_id}
```

Example:
```bash
php artisan timetable:check-ready 1
```

Output should show:
```
✅ Subjects: 4
✅ Sections: 2
✅ Classrooms: 4
✅ Days: 5
✅ Timeslots: 4
```

## Generate Timetable

### Via Web UI (Recommended)

1. **Login**: http://127.0.0.1:8000/login
   - Email: `admin@school.local`
   - Password: `password`

2. **Dashboard**: http://127.0.0.1:8000/dashboard

3. **Generate Section** (bottom-left card):
   - Select a Semester (e.g., "L1 Informatique 2025")
   - Enter a name (e.g., "Version 1")
   - Click **Générer l'emploi** (Generate)

4. **Results**:
   - ✅ System shows: "Generated X sessions"
   - 📊 View timetable table
   - ⚠️ If partial: "Skipped Y sessions" (due to conflicts)

### Via Command Line

```bash
php artisan timetable:generate {semester_id}
```

Example:
```bash
php artisan timetable:generate 1
```

## Understanding Generation

### What Gets Generated

For each **Subject** in the semester:
- For each **Section** that needs it:
  - For each **Day** of the week:
    - Try to place **X sessions** (sessions_per_week)
    - Find available **Classroom**
    - Check **Conflicts**:
      - Teacher not double-booked?
      - Classroom not double-booked?
      - Section not double-booked?
      - Classroom capacity ≥ section size?

### Why Sessions Get Skipped

A session is skipped if:
1. ❌ No available classroom for that time slot
2. ❌ Teacher already teaching at that time
3. ❌ Classroom already booked at that time
4. ❌ Section already has class at that time
5. ❌ Classroom too small for section

**Partial generation is NORMAL** — It means:
- ✅ Some sessions placed successfully
- ⚠️ Some sessions couldn't be placed (conflicts)
- 📊 Report shows: generated vs. skipped count

## Manually Adding Data

If seeder didn't work, you can manually add:

### Add Subject
```bash
POST /admin/crud/subjects
```
Required:
- Semester
- Teacher
- Name
- Sessions per week

### Add Section
```bash
POST /admin/crud/sections
```
Required:
- Program
- Name
- Capacity

### Add Classroom
```bash
POST /admin/crud/classrooms
```
Required:
- Name
- Capacity (seats)

### Add Day
```bash
POST /admin/crud/days
```
Required:
- Name (Monday, Tuesday, etc.)
- Position (1-5)

### Add Timeslot
```bash
POST /admin/crud/timeslots
```
Required:
- Name (08:00-10:00)
- Starts at
- Ends at
- Position (1-4)

## Troubleshooting

### "No subjects found"
→ Create subjects first via admin panel or seeder

### "No sections found"
→ Create sections first. Sections must belong to the same program as the semester

### "No classrooms found"
→ Create classrooms with name and capacity

### All sessions skipped (partial generation)
→ May indicate insufficient classrooms or time slots
→ Check capacity vs. section sizes
→ Check if teachers are available

### Generation takes long time
→ Normal for large datasets
→ With 10+ subjects × 5+ sections = 50+ sessions to place
→ Can take 10-30 seconds

## View Generated Timetable

After generation succeeds:
1. Dashboard shows **Recent Generations**
2. Click **View** button on a generation
3. Or: `GET /timetable/{semester_id}`
4. See table with all sessions:
   - Day
   - Time
   - Course
   - Group
   - Teacher
   - Room

## API Status Codes

```
POST /timetable/generate
├─ 200 OK + Redirect → Success
├─ 422 Unprocessable → Missing semester_id or name
└─ 302 Redirect + Errors → Missing data (subjects, sections, etc.)

GET /timetable/{semester}
├─ 200 OK → Display timetable
├─ 403 Forbidden → Access denied
└─ 404 Not Found → Semester doesn't exist
```

## Demo Flow (5 minutes)

```bash
# 1. Setup
php artisan migrate:fresh --seed

# 2. Login
# http://127.0.0.1:8000/login
# admin@school.local / password

# 3. Check readiness
php artisan timetable:check-ready 1

# 4. Generate
# Dashboard → Select semester → Click "Générer"

# 5. View result
# Click "Voir le rapportde qualité" or table below
```

## FAQ

**Q: Does it create new Subjects/Sections?**
A: No. You create them first, system generates TimetableSession records.

**Q: Can I edit generated sessions?**
A: Yes. Go to "Emploi du temps" → "Séances" → edit each session.

**Q: What if generation fails?**
A: Check logs: `storage/logs/laravel.log`

**Q: How to regenerate?**
A: Click "Generate" again. Old sessions are replaced.

**Q: Why partial generation?**
A: Means some sessions were placed, some couldn't (conflicts). Check report for details.
