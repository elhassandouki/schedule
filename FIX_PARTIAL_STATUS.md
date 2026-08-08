# Fix: Data Truncated for 'status' Column

## Problem
When generating a timetable, you got this error:
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status' at row 1
```

## Root Cause
The `schedule_histories` table's `status` column was defined as an ENUM with only:
- `draft`
- `generated`
- `failed`

But the code was trying to insert `partial` (when some sessions are generated, some skipped).

## Solution

### Option 1: Run the Migration (RECOMMENDED)

Pull the latest main:
```bash
git pull origin main
```

Run the migration:
```bash
php artisan migrate
```

This will add `partial` to the status enum.

### Option 2: Run SQL Directly

If you can't run the migration, run this in your MySQL client:

```sql
ALTER TABLE schedule_histories MODIFY status ENUM('draft', 'generated', 'failed', 'partial') DEFAULT 'draft';
```

## Verify the Fix

After running the migration or SQL, try generating again:

```bash
# In Laravel app
POST /timetable/generate
# Select a semester and click "Générer"
```

The generation should now succeed, and the status will be one of:
- `draft` — not yet generated
- `generated` — all sessions placed successfully
- `partial` — some sessions placed, some skipped (conflicts, capacity limits, etc.)
- `failed` — generation failed completely

## What Changed

**Migration**: `database/migrations/2026_08_08_000016_add_partial_status_to_schedule_histories.php`

- Added `partial` to the status enum
- Allows recording partial generations as valid states
- Non-breaking change (existing draft/generated/failed data unaffected)

## Status Meanings

| Status | Meaning |
|--------|---------|
| `draft` | Just created, not generated yet |
| `generated` | All required sessions placed successfully |
| `partial` | Some sessions placed, some skipped (normal for larger datasets) |
| `failed` | Generation algorithm failed (check logs) |

---

**After fix**: Try generating again. It should work! ✅
