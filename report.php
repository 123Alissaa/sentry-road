<?php
// report.php: prints the training status report, grouped by terminal.
// The logic lives in report.sql. This file runs it and formats the results.

require 'db.php';

$sql = file_get_contents('report.sql');
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Group the rows by terminal
$by_terminal = [];
foreach ($rows as $row) {
    $by_terminal[$row['terminal']][] = $row;
}

$line = "%-8s %-15s %-26s %-12s %-12s %s\n";

echo "TRAINING STATUS REPORT as of October 1, 2026\n";
echo "Active employees only. One row per employee per required course.\n";
foreach ($by_terminal as $terminal => $terminal_rows) {
    $people = count(array_unique(array_column($terminal_rows, 'employee_id')));
    $counts = array_count_values(array_column($terminal_rows, 'status'));

    echo "\n=== $terminal ($people employees) ===\n";
    printf($line, 'ID', 'Name', 'Course', 'Completed', 'Expires', 'Status');
    foreach ($terminal_rows as $r) {
        printf(
            $line,
            $r['employee_id'],
            $r['name'],
            $r['course_name'],
            $r['latest_completion'] ?? '-',
            $r['expiry_date'] ?? '-',
            $r['status']
        );
    }
    $summary = [];
    foreach (['Overdue', 'Never completed', 'Due soon', 'New hire', 'Current'] as $status) {
        $summary[] = "$status: " . ($counts[$status] ?? 0);
    }
    echo "Summary: " . implode(', ', $summary) . "\n";
}
echo "\nTotal rows: " . count($rows) . "\n";
