# Schedule App - Developer Continuation Prompt

## Where We Are Now

You have a **fully unified timetable management system** (Laravel 11) ready for production development. The old minute-based legacy system has been completely removed. Here's what's already done:

### ✅ Completed
1. **Unified Data Model** (Day/Timeslot based)
   - TimetableSession as single source of truth
   - All constraints enforced at DB level

2. **Auto-Generation System**
   - `AutoGenerateTimetable` service (greedy slot-filling)
   - `GenerateTimetable` artisan command
   - Full constraint checking (no double-booking, capacity validation, etc.)

3. **Manual Entry System**
   - `TimetableSessionController` CRUD
   - `SessionConflictChecker` validation
   - Form with real-time conflict detection

4. **Access Control**
   - Role-based authorization (super_admin, chef_dept, chef_filière, prof)
   - Prof can only view own sessions

5. **Demo Data**
   - `UnifiedDemoSeeder` creates complete structure
   - Ready to generate timetables

6. **Documentation**
   - APP_OVERVIEW.md (full technical reference)
   - AGENT_PROMPT.md (for AI agents)
   - This prompt (for you)

### ❌ Still TODO
1. **Testing** (no tests yet)
2. **Export Feature** (PDF/Excel timetables)
3. **Notifications** (email on schedule changes)
4. **Performance** (optimization for large datasets)
5. **UI/UX** (frontend refinement)
6. **Deployment** (production checklist)

---

## Immediate Next Steps (Recommended Order)

### Phase 1: Get It Running Locally (This Week)

#### 1.1 Setup
```bash
git clone https://github.com/elhassandouki/schedule.git
cd schedule
composer install
npm install
cp .env.example .env
php artisan key:generate

# Fix MySQL charset issue (if needed)
# In config/database.php, ensure:
# 'charset' => 'utf8'  (not utf8mb4)
```

#### 1.2 Database & Demo Data
```bash
php artisan migrate:fresh --seed
```

**What this does:**
- Creates all tables
- Seeds: 2 departments, 2 programs, 3 semesters, 4 subjects, 3 teachers, 4 classrooms, etc.
- Creates 5 demo users (1 admin, 3 profs, 1 chef)
- **Zero timetable sessions** (ready for generation)

#### 1.3 Auto-Generate Timetables
```bash
php artisan timetable:generate 1
php artisan timetable:generate 2
php artisan timetable:generate 3
```

**What you'll see:**
- Per-subject generation summary
- Skipped slots (if any) with reasons
- Total sessions created

#### 1.4 Verify Data
```bash
php artisan timetable:diagnose
```

Should show:
- departments: 2, programs: 2, semesters: 3, subjects: 4, timetable_sessions: ??? (depends on generation)
- All should be > 0 except timetable_sessions (which auto-generates)

#### 1.5 Run Locally
```bash
php artisan serve
npm run dev  # (in separate terminal)
```

Visit: http://localhost:8000

**Test logins:**
- admin@school.local / password (super_admin)
- alice@school.local / password (prof)
- chef@school.local / password (chef_departement)

**Test access control:**
- Login as alice (prof) → Go to Dashboard → Try to view different schedules
  - If alice has sessions in that schedule → ✓ Allowed
  - If alice has NO sessions → 403 Forbidden
- Login as admin → Can view all → ✓

---

### Phase 2: Add Your Real Data (Week 2)

#### 2.1 Backup & Prepare
```bash
# Backup current demo database
mysqldump -u root -p schedule > schedule_demo_backup.sql

# Create a migration for your real data
php artisan make:migration ImportRealData --create=false
```

#### 2.2 Data Import Strategy

Choose ONE approach:

**Option A: CSV Import**
1. Prepare CSVs for: departments, programs, semesters, modules, student_groups, teachers, users
2. Create a custom seeder that reads CSVs and inserts
3. Example structure:
```php
// database/seeders/ImportRealDataSeeder.php
public function run()
{
    $departments = collect(array_map('str_getcsv', file('path/to/departments.csv')));
    foreach ($departments as $row) {
        DB::table('departments')->insert([
            'name' => $row[0],
            'code' => $row[1],
            ...
        ]);
    }
}
```

**Option B: SQL Dump**
1. Export your existing database to SQL
2. Create migration that runs raw SQL (with proper FK handling)

**Option C: API/Batch Upload**
1. Create bulk upload endpoint
2. Accept JSON payload
3. Validate & insert

#### 2.3 Run Generation
After importing your data:
```bash
# Delete old demo sessions (optional)
php artisan tinker
> DB::table('timetable_sessions')->delete()
> exit

# Generate for all semesters
php artisan timetable:generate 1
php artisan timetable:generate 2
php artisan timetable:generate 3
...
```

---

### Phase 3: Add Missing Features (Week 3-4)

#### 3.1 Testing (PHPUnit)

**Create test files:**
```bash
php artisan make:test Services/AutoGenerateTimetableTest --unit
php artisan make:test Services/SessionConflictCheckerTest --unit
php artisan make:test Http/Controllers/TimetableSessionControllerTest --feature
```

**Example test (AutoGenerateTimetable):**
```php
// tests/Unit/Services/AutoGenerateTimetableTest.php
public function test_generates_sessions_without_conflicts()
{
    $semesterId = $this->createTestSemester();
    $generator = new AutoGenerateTimetable();
    $result = $generator->generate($semesterId);
    
    $this->assertTrue($result['success']);
    $this->assertGreater($result['sessions_generated'], 0);
}

public function test_rejects_teacher_double_booking()
{
    // Create two subjects with same teacher
    // Try to generate overlapping slots
    // Assert skipped with reason
}

public function test_respects_classroom_capacity()
{
    // Create section with 50 students
    // Only classrooms with capacity ≥ 50 should be used
}
```

**Run tests:**
```bash
php artisan test
```

#### 3.2 Export Feature (PDF/Excel)

**Install libraries:**
```bash
composer require maatwebsite/excel  # or use TCPDF/DomPDF
```

**Create export command:**
```bash
php artisan make:command ExportTimetable
```

**Implement:**
```php
// app/Console/Commands/ExportTimetable.php
protected $signature = 'timetable:export {semester_id} {--format=pdf}';

public function handle()
{
    $semester = Semester::find($this->argument('semester_id'));
    $sessions = TimetableSession::where('semester_id', $semester->id)
        ->with(['day', 'timeslot', 'subject', 'teacher', 'classroom', 'section'])
        ->orderBy('day_id')
        ->orderBy('timeslot_id')
        ->get();
    
    if ($this->option('format') === 'excel') {
        return $this->exportExcel($semester, $sessions);
    }
    return $this->exportPdf($semester, $sessions);
}
```

**Add UI buttons:**
- Dashboard: "Export as PDF" / "Export as Excel"
- Linked to `TimetableSessionController` methods

#### 3.3 Notifications

**Setup email:**
In `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  (or your provider)
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=schedule@school.local
```

**Create notification:**
```bash
php artisan make:notification ScheduleChanged
```

**Implement:**
```php
// app/Notifications/ScheduleChanged.php
public function via($notifiable)
{
    return ['mail'];
}

public function toMail($notifiable)
{
    return (new MailMessage)
        ->subject('Your Schedule Has Changed')
        ->line('A new session has been added to your timetable.')
        ->action('View Schedule', url('/schedules/..'))
        ->line('Thank you for using our system!');
}
```

**Trigger on session create:**
```php
// TimetableSessionController::store()
$session = TimetableSession::create($validated);
$teacher = $session->teacher->user;
if ($teacher) {
    $teacher->notify(new ScheduleChanged($session));
}
```

#### 3.4 UI/UX Improvements

**Current UI (Blade templates in resources/views/):**
- Basic CRUD forms
- Table lists
- No styling framework

**Improvements:**
1. **Add Bootstrap/Tailwind**
   ```bash
   npm install bootstrap
   # or
   npm install -D tailwindcss
   ```

2. **Calendar View**
   - Show timetable as calendar (day × timeslot grid)
   - Color-code by subject/teacher
   - Drag-to-reschedule (with validation)

3. **Better Forms**
   - Real-time conflict warnings
   - Autocomplete (teacher, classroom, section)
   - Validation feedback

4. **Dashboard Widgets**
   - Statistics (X sessions generated, Y conflicts found)
   - Recent changes
   - Alerts (missing assignments, etc.)

---

### Phase 4: Performance & Optimization (Week 4+)

#### 4.1 Query Optimization

**Identify slow queries:**
```bash
# Enable MySQL query log
# Or use Laravel Debugbar
composer require barryvdh/laravel-debugbar --dev
```

**Common bottlenecks:**
- Fetching timetable sessions with relationships (use eager loading)
- Constraint checking (create DB indexes)

**Fix:**
```php
// Before: N+1 queries
$sessions = TimetableSession::all();
foreach ($sessions as $s) {
    echo $s->teacher->name;  // Query per session!
}

// After: 1 query
$sessions = TimetableSession::with('teacher', 'subject', 'classroom', 'section', 'day', 'timeslot')
    ->where('semester_id', $semesterId)
    ->get();
```

#### 4.2 Caching

**Cache frequently accessed data:**
```php
// Services/AutoGenerateTimetable.php
$days = Cache::remember('days', 60*60, function () {
    return DB::table('days')->orderBy('position')->get();
});

$timeslots = Cache::remember('timeslots', 60*60, function () {
    return DB::table('timeslots')->orderBy('position')->get();
});
```

#### 4.3 Batch Operations

**For large institutions (1000+ sessions):**
- Use `insertIgnore()` instead of individual inserts
- Batch delete/update operations
- Disable indexing during bulk imports, rebuild after

---

### Phase 5: Deployment (Production)

#### 5.1 Pre-Deployment Checklist

```bash
# 1. Run tests
php artisan test

# 2. Clear cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. Run migrations (on production server)
php artisan migrate --force

# 4. Seed if needed (careful with production!)
# php artisan db:seed --class=UnifiedDemoSeeder  (only for new instances)

# 5. Verify database
php artisan timetable:diagnose

# 6. Check .env
# - APP_DEBUG=false
# - APP_ENV=production
# - Database credentials correct
# - Mail settings configured
```

#### 5.2 Server Setup

**Requirements:**
- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 18+ (for Vite)
- Composer

**Steps:**
1. Clone repo
2. `composer install --no-dev`
3. `npm install && npm run build`
4. `cp .env.production .env`
5. `php artisan key:generate`
6. `php artisan migrate`
7. `php artisan storage:link`
8. Set permissions: `storage/` and `bootstrap/cache/` writable

#### 5.3 Monitoring

**After deployment:**
- Monitor error logs: `storage/logs/laravel.log`
- Check DB performance: query times, slowlog
- Set up email alerts for errors
- Monitor timetable generation success rate

---

## Architecture You're Working With

```
┌─────────────────────────────────────────┐
│     Web Browser (Blade Templates)       │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│   Laravel Routes (web.php)              │
│  TimetableSessionController, etc.       │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│   Business Logic (Services)             │
│  - AutoGenerateTimetable (algo)         │
│  - SessionConflictChecker (validation)  │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│   Eloquent Models (ORM)                 │
│  TimetableSession, Teacher, Subject...  │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│   MySQL Database                        │
│  (Unique constraints, FKs enforced)     │
└─────────────────────────────────────────┘
```

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `app/Services/AutoGenerateTimetable.php` | Main generation algorithm |
| `app/Services/SessionConflictChecker.php` | Validation logic |
| `app/Http/Controllers/TimetableSessionController.php` | CRUD endpoints |
| `app/Models/TimetableSession.php` | Core model |
| `database/seeders/UnifiedDemoSeeder.php` | Demo data |
| `routes/web.php` | All routes |
| `config/database.php` | DB config (charset critical) |
| `APP_OVERVIEW.md` | Technical reference |
| `AGENT_PROMPT.md` | For AI agents |

---

## Common Issues & Fixes

### Issue: "Illuminate\Database\QueryException: Index key too long"
**Fix:** In `config/database.php`, ensure `'charset' => 'utf8'` (not utf8mb4)

### Issue: "Class not found: App\Models\Schedule"
**Fix:** Run `composer dump-autoload` after deleting files

### Issue: Prof can view schedules they're not in
**Fix:** Check `DashboardController::show()` — must validate `professor_id` matches

### Issue: Auto-generation skips too many sessions
**Debug:**
```bash
php artisan timetable:generate 1 --dry-run  # Check error messages
# Then fix data (e.g., add more classrooms, adjust timeslots)
```

### Issue: Performance slow with 1000+ sessions
**Fix:** Add indexes to `timetable_sessions` on foreign keys
```php
// New migration
$table->index(['semester_id', 'day_id', 'timeslot_id']);
$table->index(['teacher_id', 'semester_id']);
$table->index(['section_id', 'semester_id']);
```

---

## Development Best Practices

1. **Always test access control** before pushing
   - Try different roles
   - Check 403 Forbidden triggers correctly

2. **Use `--dry-run`** when experimenting with generation
   ```bash
   php artisan timetable:generate 1 --dry-run
   ```

3. **Backup database** before major changes
   ```bash
   mysqldump -u root -p schedule > backup.sql
   ```

4. **Version your data**
   - Keep notes on what data you have (semesters, subjects, etc.)
   - Track changes in migrations

5. **Log generation attempts**
   - Add logging to `AutoGenerateTimetable`
   - Track success/failure rates over time

6. **Test with realistic data**
   - Use your real departments/programs/teachers early
   - Identify capacity issues before production

---

## Success Criteria (How to Know You're Done)

By the end of Phase 2, you should have:
✅ Real data imported (departments, programs, semesters, teachers, students)
✅ Timetables auto-generated for all semesters
✅ No conflicts in generated sessions
✅ Dashboard loads correctly
✅ Access control working (prof sees only own sessions)
✅ Manual entry form works (can add/edit/delete sessions)

By the end of Phase 3, you should have:
✅ Full test coverage (80%+)
✅ Export to PDF/Excel working
✅ Professors receiving email notifications
✅ UI looks professional

By the end of Phase 5, you should have:
✅ Deployed to production server
✅ Monitored for errors (first week)
✅ Users accessing the system successfully

---

## Questions? Issues?

1. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Debug in tinker:**
   ```bash
   php artisan tinker
   > DB::table('timetable_sessions')->count()
   > Semester::with('subjects')->first()
   ```

3. **Test commands:**
   ```bash
   php artisan timetable:generate 1 --dry-run
   php artisan timetable:diagnose
   ```

4. **Review code comments** in Services/ (they explain the algorithm)

---

**You're ready to go! Start with Phase 1 and work through systematically. Good luck! 🚀**

---

**Last Updated:** August 2026
**System Version:** 2.0 (Unified)
**PHP Version:** 8.2+
**Laravel Version:** 11
**Database:** MySQL 5.7+
