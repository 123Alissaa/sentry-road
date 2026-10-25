# Example Freight Co. Training Compliance Report

## What the report shows

The report lists every active employee and each course their role requires,
as of October 1, 2026. Each row shows when the employee last completed the course,
when that completion expires, and a status. Employees are grouped by terminal, and
each terminal ends with a count of each status.

| Status | Meaning | What to do |
|--------|---------|------------|
| Overdue | Expired before October 1, 2026 | Out of compliance now |
| Never completed | No completion on record | Out of compliance now |
| Due soon | Expires October 1 to October 31, 2026 | Schedule within 30 days |
| New hire | Hired in the 30 days before October 1, not yet completed | Within their 30-day window |
| Current | Valid past October 31 | Nothing |

**Headline:** 18 of 32 active employees have at least one course that is Overdue or Never completed. Charlotte has the highest share: 5 of 6 employees (83%). Memphis is next at 63%, then Atlanta and Tampa at 44%.

## Problems in the data, and what I did

- **Unknown employee** (completions line 21, EX9999): left out. Please check who this is.
- **Course name typed in lowercase with an extra space** (line 39): matched to Fire Extinguisher Safety.
- **Date written in 11/04/2025 format** (line 112): read as November 4, 2025 (US format).
- **Completion with no date** (line 67): left out. The same employee has another dated completion.
- **Completion dated November 2, 2026** (line 81): after the report date, so not counted. Likely a typo; please confirm.
- **Same completion listed twice** (lines 26 and 155): counted once.
- **Five completions dated before the employee's hire date** (lines 38, 44, 72, 83, 101): kept, as likely training from a previous employer. This changes no statuses, because each employee also has a later completion.
- **Two terminated employees**: left out of the report.

**Rules I had to interpret:** a course expiring exactly on October 1 or October 31 counts as Due soon; an employee hired exactly on September 1 counts as a New hire; New hire and Due soon do not count toward the terminal percentage, because the question asks only about Overdue and Never completed.

## How to run it

Requires PHP 8 and MySQL 8 or newer. From the project folder:

```bash
export DB_PASS='your MySQL password'    # username defaults to root (set DB_USER to change)
mysql -u root -p < schema.sql           # creates the database and tables
php load.php                            # loads the files and lists every data problem
php report.php                          # prints the report
mysql -u root -p sentry_road < worst_terminal.sql
```

## With more time

The status rules are written in two places: the report and the terminal query. I would define them once in the database, so the two can never disagree if a rule changes.

## Where AI helped, and where it was wrong

AI helped me the most with PHP, since it was new to me. I used Claude mostly as a tutor: to understand the brief, plan the steps, make checklists, and explain the code. I typed and ran every line myself, and when I hit errors, I used it to help me understand what they meant. Before writing any code, I calculated what the correct report should be straight from the raw files, then checked each step against that.

Where it was wrong:
- It first said only employees.csv had Windows line endings. Checking the files showed all three did. It didn't change the code, but it taught me to verify claims myself instead of taking them as given.
- It first warned the line endings would make the 'Active' filter match nobody. Testing showed that's true for MySQL's LOAD DATA, but PHP's CSV reader removes them, so the warning didn't apply to the tool I used.

I also caught bugs by checking results myself: a summary whose counts didn't add up to the number of report rows, and date columns that printed blank.

Overall, I used AI like a tutor. It explained concepts and suggested structure, but I typed and ran everything, fixed my own mistakes, and checked every result against numbers I could verify.