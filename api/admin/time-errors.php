<?php
/**
 * GET /api/admin/time-errors.php?from=YYYY-MM-DD&to=YYYY-MM-DD&search=&format=json|csv
 * Per-agent summary of time errors. Read only.
 *
 * format=csv          one row per agent (summary)
 * format=csv&detail=1 one row per error (incident list)
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';

AdminMiddleware::requireManager();

$today  = TimeHelper::currentDate();
$to     = $_GET['to']   ?? $today;
$from   = $_GET['from'] ?? (new DateTime($to))->modify('-29 days')->format('Y-m-d');
$search = trim($_GET['search'] ?? '');
$format = $_GET['format'] ?? 'json';
$detail = !empty($_GET['detail']);

$isDate = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;
if (!$isDate($from) || !$isDate($to) || $from > $to) {
    Response::error('Invalid date range. Use from=YYYY-MM-DD&to=YYYY-MM-DD with from <= to.');
}

$summary = (new TimeErrorService())->summarize($from, $to, $search);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $name = $detail ? 'time_error_details' : 'time_errors_by_agent';
    header('Content-Disposition: attachment; filename="' . $name . '_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');

    if ($detail) {
        fputcsv($out, ['User ID', 'Name', 'Username', 'Date', 'Error', 'Detail', 'Time impact (HH:MM:SS)']);
        foreach ($summary['agents'] as $a) {
            foreach ($a['incidents'] as $i) {
                fputcsv($out, [
                    $a['user_id'], $a['name'], $a['username'], $i['work_date'], $i['label'], $i['detail'],
                    $i['impact_seconds'] === null ? '' : ($i['impact_seconds'] < 0 ? '-' : '') . TimeHelper::formatDuration(abs($i['impact_seconds'])),
                ]);
            }
        }
    } else {
        $header = ['User ID', 'Name', 'Username', 'Days checked', 'Days with errors', 'Total errors'];
        foreach ($summary['labels'] as $label) {
            $header[] = $label;
        }
        $header = array_merge($header, ['Days that cannot be verified', 'Recorded work (h)', 'Real work from actions (h)', 'Difference (h)']);
        fputcsv($out, $header);
        foreach ($summary['agents'] as $a) {
            $row = [$a['user_id'], $a['name'], $a['username'], $a['days_checked'], $a['days_with_errors'], $a['error_count']];
            foreach (array_keys($summary['labels']) as $code) {
                $row[] = $a['counts'][$code];
            }
            $row[] = $a['unverifiable_days'];
            $row[] = round($a['recorded_work_seconds'] / 3600, 2);
            $row[] = round($a['actual_work_seconds'] / 3600, 2);
            $row[] = round($a['difference_seconds'] / 3600, 2);
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

Response::success('Time error summary loaded.', $summary);
