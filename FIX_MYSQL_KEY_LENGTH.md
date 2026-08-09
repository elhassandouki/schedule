# Fix: MySQL Key Length Error (1071)

## Problem

When running `php artisan migrate:fresh --seed`, you get:

```
SQLSTATE[42000]: Syntax error or access violation: 1071 La clé est trop longue. 
Longueur maximale: 1000
```

This happens on `failed_jobs` table index creation.

---

## Root Cause

MySQL has a maximum key length of 1000 bytes. The `failed_jobs` index tries to create:
```sql
ALTER TABLE failed_jobs ADD INDEX (connection, queue, failed_at)
```

With your MySQL configuration, this exceeds the limit.

---

## Solution (Choose One)

### ✅ Option A: Disable Failed Jobs (RECOMMENDED)

**Edit `.env`**:

```env
QUEUE_CONNECTION=sync
```

This disables the failed_jobs queue entirely (uses synchronous processing instead).

**Then run**:

```bash
php artisan migrate:fresh --seed
```

✅ Should work immediately!

---

### Option B: Fix MySQL Configuration

If you need failed_jobs functionality:

**In phpMyAdmin or MySQL Console**:

```sql
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
```

**Then run**:

```bash
php artisan migrate:fresh --seed
```

Note: This requires MySQL 5.7.7+ and InnoDB engine.

---

### Option C: Use SQLite Instead

For development/testing, SQLite has no key length limits.

**Edit `.env`**:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite
```

**Then run**:

```bash
php artisan migrate:fresh --seed
```

---

## After Fix

**Verify setup worked**:

```bash
php artisan check:groups 1
```

**Then test generation**:

1. Go to: http://127.0.0.1:8000/dashboard
2. Click "Générer l'emploi du temps"
3. Select Semester 1
4. Click Generate

Should work! ✅

---

## Why This Happens

Laravel's default migrations include `failed_jobs` table for queue failures. With certain MySQL versions/configurations, the composite index on multiple VARCHAR columns exceeds the 1000-byte limit.

**Fix**: Use QUEUE_CONNECTION=sync (no queue, process jobs immediately) or upgrade MySQL.

---

## Recommended Setup

For this project:
- **Development**: Use `QUEUE_CONNECTION=sync`
- **Production**: Set up proper MySQL 5.7.7+ with InnoDB

```env
# Development
QUEUE_CONNECTION=sync

# Production (if needed)
QUEUE_CONNECTION=database
```

---

## Quick Reference

```bash
# Step 1: Update .env
echo "QUEUE_CONNECTION=sync" >> .env

# Step 2: Fresh database
php artisan migrate:fresh --seed

# Step 3: Verify
php artisan check:groups 1

# Step 4: Test in browser
php artisan serve
# Visit http://127.0.0.1:8000/dashboard
```
