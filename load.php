<?php
// load.php: reads the three CSV files, cleans them, and loads them into MySQL. 

require 'db.php';
$report_date = '2026-10-01'; // The report is as of this date (from the brief).

// Reads a CSV file and returns its rows. Each row is keyed 
// by the column names from the header line, and every value is trimmed. 

function read_csv($path) {
    $handle = fopen($path, 'r');
    $header = array_map('trim', fgetcsv($handle, null, ',', '"', ''));
    $rows = [];
    $line_number = 1;
    while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $line_number++;
        if ($values === [null]) continue; // skip empty lines
        $values = array_map('trim', $values);
        $row = array_combine($header, $values);
        $row['_line'] = $line_number; // for debugging
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

// Turns date text into 'YYYY-MM-DD' format, or null if it isn't a real date. 
// Accepts 2025-11-04 (the normal format) and 11/04/2025 (US month/day/year).
function parse_date($text) {
    $formats = ['Y-m-d', 'm/d/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $text);
        if ($date && $date->format($format) === $text) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}
// Every problem we find in the data gets written down here. 
$issues = [];

// Start fresh, so this script can be run more than once. 
// Child table first, because of the foreign key constraints.
$pdo->exec('DELETE FROM completions');
$pdo->exec('DELETE FROM employees');
$pdo->exec('DELETE FROM courses');

// --- Courses ---
$courses = read_csv('data/courses.csv');
$insert = $pdo->prepare('INSERT INTO courses (course_name, renewal_months, required_for) VALUES (?, ?, ?)');
foreach ($courses as $course) {
    $insert->execute([$course['course_name'], (int) $course['renewal_months'], $course['required_for']]);
}
echo 'Courses loaded: ' . count($courses) . "\n";

// --- Employees ---
$employees = read_csv('data/employees.csv');
$insert = $pdo->prepare('INSERT INTO employees (employee_id, first_name, last_name, terminal, role, hire_date, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)');
$loaded = 0;
foreach ($employees as $employee) {
    $hire_date = parse_date($employee['hire_date']);
    if ($hire_date === null) {
        $issues[] = "Employee {$employee['employee_id']}: unreadable hire date '{$employee['hire_date']}', skipped";
        continue;
    }
    $insert->execute([
        $employee['employee_id'],
        $employee['first_name'],
        $employee['last_name'],
        $employee['terminal'],
        $employee['role'],
        $hire_date,
        $employee['status']
    ]);
    $loaded++;
}
echo "Employees loaded: $loaded of " . count($employees) . "\n";


// --- Completions ---
// Lookups built from what we already loaded: which employees exist (and when they were hired), 
// and the official spelling of the course names.
$hire_dates = [];
foreach ($pdo->query('SELECT employee_id, hire_date FROM employees') as $row) {
    $hire_dates[$row['employee_id']] = $row['hire_date'];
}
$official_course = [];
foreach ($pdo->query('SELECT course_name FROM courses') as $row) {
    $official_course[strtolower($row['course_name'])] = $row['course_name'];
}
$completions = read_csv('data/completions.csv');
$insert = $pdo->prepare('INSERT INTO completions (employee_id, course_name, completed_date) VALUES (?, ?, ?)');
$seen = [];
$loaded = 0;
foreach ($completions as $c) {
    $where = "completions.csv line {$c['_line']}";
    $id = $c['employee_id'];

    // 1. the employee must exist
    if (!isset($hire_dates[$id])) {
        $issues[] = "$where: employee $id is not in employees.csv, skipped";
        continue;
    }

    // 2. the course name must exist. Match ignoring case, then use the official spelling.
    $key = strtolower($c['course_name']);
    if (!isset($official_course[$key])) {
        $issues[] = "$where: unknown course '{$c['course_name']}'. Skipped";
        continue;
    }
    $course = $official_course[$key];
    if ($course !== $c['course_name']) {
        $issues[] = "$where: course written as '{$c['course_name']}' corrected to '$course'.";
    }

    // 3. the completion date must be a real date
    if ($c['completed_date'] === '') {
        $issues[] = "$where: $id $course has no completion date, skipped";
        continue;
    }
    $date = parse_date($c['completed_date']);
    if ($date === null) {
        $issues[] = "$where: $id $course has unreadable completion date '{$c['completed_date']}', skipped";
        continue;
    }
    if ($date !== $c['completed_date']) {
        $issues[] = "$where: $id $course date written as '{$c['completed_date']}', read as $date (month/day/year).";
    }

    // 4. Skip exact duplicates. 
    $fingerprint = "$id|$course|$date";
    if (isset($seen[$fingerprint])) {
        $issues[] = "$where: exact duplicate of line {$seen[$fingerprint]}. Skipped";
        continue;
    }
    $seen[$fingerprint] = $c['_line'];

    // 5. Flag (but keep) dates that need a human look at them.
    if ($date > $report_date) {
        $issues[] = "$where: $id $course completed $date, after the report date. Kept, but the report ignores it.";
    }
    if ($date < $hire_dates[$id]) {
        $issues[] = "$where: $id $course completed $date, before the hire date of {$hire_dates[$id]}. Kept, (likely earlier training).";
    }
    $insert->execute([$id, $course, $date]);
    $loaded++;
}
echo "Completions loaded: $loaded of " . count($completions) . "\n";

// --- Problems found ---
echo "\nProblems found: " . count($issues) . "\n";
foreach ($issues as $issue) {
    echo " - $issue\n";
}