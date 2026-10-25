WITH settings AS (
    SELECT DATE('2026-10-01') AS report_date
),
latest AS (
    SELECT comp.employee_id, comp.course_name, MAX(comp.completed_date) AS latest_completion
    FROM completions comp
    CROSS JOIN settings s
    WHERE comp.completed_date <= s.report_date
    GROUP BY comp.employee_id, comp.course_name
),
required AS (
    SELECT e.terminal, e.employee_id, e.first_name, e.last_name, e.hire_date,
           c.course_name, c.renewal_months,
           l.latest_completion,
           DATE_ADD(l.latest_completion, INTERVAL c.renewal_months MONTH) AS expiry_date
    FROM employees e
    JOIN courses c ON c.required_for = 'All' OR c.required_for = e.role
    LEFT JOIN latest l ON l.employee_id = e.employee_id AND l.course_name = c.course_name
    WHERE e.status = 'Active'
),
statuses AS (
    SELECT r.terminal, r.employee_id,
           CASE
               WHEN r.latest_completion IS NULL
                    AND r.hire_date >= DATE_SUB(s.report_date, INTERVAL 30 DAY) THEN 'New hire'
               WHEN r.latest_completion IS NULL THEN 'Never completed'
               WHEN r.expiry_date < s.report_date THEN 'Overdue'
               WHEN r.expiry_date <= DATE_ADD(s.report_date, INTERVAL 30 DAY) THEN 'Due soon'
               ELSE 'Current'
           END AS status
    FROM required r
    CROSS JOIN settings s
),
per_employee AS (
    SELECT terminal, employee_id,
           MAX(status IN ('Overdue', 'Never completed')) AS has_problem
    FROM statuses
    GROUP BY terminal, employee_id
),
per_terminal AS (
    SELECT terminal,
           COUNT(*)         AS active_employees,
           SUM(has_problem) AS employees_with_problem,
           ROUND(100 * SUM(has_problem) / COUNT(*), 1) AS pct_with_problem
    FROM per_employee
    GROUP BY terminal
)
SELECT terminal, active_employees, employees_with_problem, pct_with_problem
FROM per_terminal
WHERE pct_with_problem = (SELECT MAX(pct_with_problem) FROM per_terminal);