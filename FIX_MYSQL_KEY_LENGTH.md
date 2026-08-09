# Fix: MySQL Key Length Error (1071)

## Problem

When running `php artisan migrate:fresh --seed`, you get:

```
SQLSTATE[42000]: Syntax error or access violation: 1071 La clé est trop longue
```

This happens because MySQL has a default key length limit of 1000 bytes for InnoDB.

---

## ✅ Solutions

### Solution 1: Disable Failed Jobs (RECOMMENDED - Simplest)

Edit `.env`:

```env
QUEUE_CONNECTION=sync
```

This disables the `failed_jobs` table which has the problematic index.

Then run:

```bash
php artisan migrate:fresh --seed
```

**Pros**: Simple, works immediately  
**Cons**: Can't track failed jobs (not needed for development)

---

### Solution 2: Configure MySQL

Run in MySQL/phpMyAdmin:

```sql
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
```

Or edit MySQL config file (`my.ini` on Windows, `my.cnf` on Linux):

```ini
[mysqld]
innodb_large_prefix = ON
innodb_file_format = Barracuda
innodb_file_per_table = ON
```

Restart MySQL service, then run:

```bash
php artisan migrate:fresh --seed
```

**Pros**: Proper fix, works with all features  
**Cons**: Requires MySQL restart and config changes

---

### Solution 3: Use SQLite for Development

Edit `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite
```

Then run:

```bash
php artisan migrate:fresh --seed
```

SQLite doesn't have key length limits.

**Pros**: No MySQL config needed, works immediately  
**Cons**: Different database engine than MySQL (production likely uses MySQL)

---

## 🎯 Quick Start (Choose One)

### For Quick Testing:
```bash
# Option 1: Edit .env to use SQLite
DB_CONNECTION=sqlite

# Then run
php artisan migrate:fresh --seed
```

### For MySQL Users:
```bash
# Option 1: Edit .env to disable failed_jobs
QUEUE_CONNECTION=sync

# Then run
php artisan migrate:fresh --seed
```

### For Production-Like Setup:
```bash
# Option 2: Configure MySQL properly
# Run in MySQL: SET GLOBAL innodb_large_prefix = ON;
# Edit my.ini and add: innodb_large_prefix = ON
# Restart MySQL

# Then run
php artisan migrate:fresh --seed
```

---

## ✅ Verify Success

After fixing, check that data is seeded:

```bash
php artisan check:groups 1
```

Should show:
```
📋 STUDENT GROUPS DIAGNOSTIC

✅ Columns in student_groups table:
   - id
   - semester_id
   - name
   - student_count
   ...

Total student groups: 3

📊 Student Groups Data:
  ID: 1
  Name: L1 Groupe A
  Semester ID: 1 ✅
  Capacity: 60
```

---

## If Migration Still Fails

Try complete fresh database:

```bash
# 1. Delete old database
rm database/database.sqlite  # if using SQLite

# 2. Clear Laravel cache
php artisan cache:clear
php artisan config:clear

# 3. Fresh migration
php artisan migrate:fresh --seed

# 4. Verify
php artisan check:groups
```

---

## For Production Deployment

Use **MySQL 8.0+** or **MariaDB 10.3+** — they handle this better by default.

Or set these in your MySQL configuration permanently:

```ini
[mysqld]
innodb_large_prefix = ON
innodb_file_format = Barracuda
innodb_file_per_table = ON
```

---

## References

- [MySQL InnoDB Key Length Limits](https://dev.mysql.com/doc/refman/5.7/en/innodb-limits.html)
- [Laravel Database Configuration](https://laravel.com/docs/database)
- [SQLite vs MySQL](https://www.sqlite.org/whybother.html)
