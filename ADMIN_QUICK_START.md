# Quick Start Guide - Attendance Management System

## For System Administrators

### 1. INITIAL SETUP (One-Time)

#### A. Set Employee Numbers
```sql
-- Match employee numbers to device IDs
UPDATE users SET employee_no = 'DEVICE_ID' WHERE id = USER_ID;

-- Example:
UPDATE users SET employee_no = '1001' WHERE name = 'John Doe';
```

#### B. Enable Scheduler (Cron Job)
Add to crontab:
```bash
* * * * * cd /path/to/muni-ehrms && php artisan schedule:run >> /dev/null 2>&1
```

### 2. DAILY OPERATIONS

#### Check System Status
```bash
# View attendance commands
php artisan list | grep attendance

# Process any backlog
php artisan attendance:process-events

# Run yesterday's evaluation
php artisan attendance:evaluate-eod
```

#### View Processing Status
```sql
-- Today's attendance summary
SELECT status, COUNT(*) as count
FROM attendance_records
WHERE attendance_date = CURDATE()
GROUP BY status;

-- Event processing status
SELECT process_status, COUNT(*) as count
FROM event_logs
GROUP BY process_status;
```

### 3. TROUBLESHOOTING

#### Problem: Events not processing
```bash
# Check for unprocessed events
php artisan attendance:process-events --limit=10

# View logs
tail -f storage/logs/laravel.log | grep "Event processing"
```

#### Problem: Wrong attendance status
```bash
# Re-evaluate yesterday
php artisan attendance:evaluate-eod 2026-01-26

# Check user availability
SELECT id, name, status, work_days, start_working_date
FROM users WHERE id = USER_ID;
```

#### Problem: User not found in events
```sql
-- Check employee number matching
SELECT u.id, u.name, u.employee_no, e.employee_no as event_emp_no
FROM users u
LEFT JOIN event_logs e ON u.employee_no = e.employee_no
WHERE e.process_status = 'failed';
```

### 4. COMMON TASKS

#### Manual Processing
```bash
# Process events from specific date
php artisan attendance:process-events

# Evaluate specific date
php artisan attendance:evaluate-eod 2026-01-20
```

#### Check Logs
```bash
# View all logs
tail -100 storage/logs/laravel.log

# Filter successes
grep "Event processed successfully" storage/logs/laravel.log

# Filter failures
grep "Event processing failed" storage/logs/laravel.log
```

#### Database Queries
```sql
-- Failed events with reasons
SELECT employee_no, employee_name, event_time, process_error
FROM event_logs
WHERE process_status = 'failed'
ORDER BY created_at DESC
LIMIT 20;

-- Users without employee numbers
SELECT id, name, username
FROM users
WHERE employee_no IS NULL
AND status = 'Active';

-- Late arrivals today
SELECT u.name, a.check_in_time, a.is_late
FROM attendance_records a
JOIN users u ON a.user_id = u.id
WHERE a.attendance_date = CURDATE()
AND a.is_late = 'Yes';
```

### 5. SCHEDULED TASKS

These run automatically (do not run manually unless needed):

- **11:59 PM Daily:** End-of-day evaluation
- **Every 5 Minutes:** Process unprocessed events (limit 500)

### 6. EMERGENCY PROCEDURES

#### System Not Processing Events
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify cron is running: `crontab -l`
3. Test webhook endpoint: `curl POST /api/hikvision/events`
4. Check database connection
5. Clear cache: `php artisan cache:clear`

#### Mass Reprocessing Needed
```bash
# Process all unprocessed events
php artisan attendance:process-events --limit=5000

# Re-evaluate last 7 days
for i in {1..7}; do
    php artisan attendance:evaluate-eod $(date -d "$i days ago" +%Y-%m-%d)
done
```

### 7. PERFORMANCE MONITORING

```sql
-- Processing statistics
SELECT 
    DATE(created_at) as date,
    process_status,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_processing_seconds
FROM event_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), process_status;

-- Today's attendance completion rate
SELECT 
    ROUND(
        SUM(CASE WHEN status IN ('Present', 'Half Day', 'On Leave') THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
        2
    ) as completion_percentage
FROM attendance_records
WHERE attendance_date = CURDATE();
```

### 8. CONTACT & ESCALATION

**Technical Issues:**
- Check IMPLEMENTATION_COMPLETE.md
- Review ATTENDANCE_SYSTEM_ANALYSIS.md
- Check Laravel logs
- Contact IT department

**Business Rule Changes:**
- Requires code modification
- Contact development team
- Document requested changes

---

**Quick Commands Reference:**

| Command | Purpose |
|---------|---------|
| `php artisan attendance:process-events` | Process unprocessed events |
| `php artisan attendance:evaluate-eod [date]` | Evaluate attendance for date |
| `php artisan schedule:list` | View scheduled tasks |
| `php artisan migrate` | Run database migrations |
| `tail -f storage/logs/laravel.log` | Monitor live logs |

---

**Important:** Always backup database before running manual evaluations on historical data!
