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
    require_once ROOT_PATH . '/src/Helpers/CsvExport.php';
    $name = $detail ? 'time_error_details' : 'time_errors_by_agent';
    $csv = new CsvExport($name . '_' . $from . '_' . $to . '.csv');

    if ($detail) {
        $csv->row(array_map('t', ['Agent', 'Username', 'Day', 'Date', 'Error', 'Detail', 'Time impact']));
        $agents = $summary['agents'];
        usort($agents, fn($x, $y) => strcasecmp($x['name'], $y['name']));
        foreach ($agents as $a) {
            $lines = [];
            foreach ($a['incidents'] as $i) {
                $lines[] = [$i['work_date'], $i['label'], $i['detail'],
                    CsvExport::duration($i['impact_seconds'] === null ? null : (int) $i['impact_seconds'])];
            }
            // Leave is not an error, but listed so the sheet shows why a day has no clock times.
            foreach ($a['leave_days'] as $l) {
                $lines[] = [$l['work_date'],
                    t('Leave:') . ' ' . $l['label'] . ' ' . ($l['paid'] ? t('(paid)') : t('(unpaid)')),
                    $l['part_label'] . ($l['planned'] ? ', ' . t('planned') : '') . ($l['note'] !== '' ? '. ' . $l['note'] : ''),
                    CsvExport::duration((int) $l['seconds'])];
            }
            // One agent at a time, oldest day first.
            usort($lines, fn($x, $y) => strcmp($x[0], $y[0]));
            foreach ($lines as [$date, $error, $text, $impact]) {
                $d = new DateTime($date);
                $csv->row([
                    $a['name'], $a['username'], t($d->format('l')),
                    // DD/MM/YYYY as text, so Excel shows it exactly like the page
                    // instead of switching it to its own date format.
                    "\u{200B}" . $d->format('d/m/Y'),
                    $error, $text, $impact,
                ]);
            }
        }
    } else {
        // Only the error types that happened in this period, so the sheet stays readable.
        $codes = [];
        foreach (array_keys($summary['labels']) as $code) {
            foreach ($summary['agents'] as $a) {
                if ($a['counts'][$code] > 0) {
                    $codes[] = $code;
                    break;
                }
            }
        }
        $header = array_map('t', ['Agent', 'Username', 'Days checked', 'Days with errors', 'Total errors']);
        foreach ($codes as $code) {
            $header[] = $summary['labels'][$code];
            $header[] = $summary['labels'][$code] . ' ' . t('(time)');
        }
        // Leave types taken in this period (days, a half day = 0.5, and hours)
        $leaveCodes = [];
        foreach (array_keys($summary['leave_labels']) as $type) {
            foreach ($summary['agents'] as $a) {
                if (($a['leave_counts'][$type] ?? 0) > 0) {
                    $leaveCodes[] = $type;
                    break;
                }
            }
        }
        foreach ($leaveCodes as $type) {
            $header[] = $summary['leave_labels'][$type] . ' ' . t('(days)');
            $header[] = $summary['leave_labels'][$type] . ' ' . t('(time)');
        }
        $header = array_merge($header, array_map('t', ['Paid leave (time)', 'Unpaid leave (time)', 'Days that cannot be verified', 'Recorded work', 'Real work from actions', 'Difference']));
        $csv->row($header);
        foreach ($summary['agents'] as $a) {
            $row = [$a['name'], $a['username'], $a['days_checked'], $a['days_with_errors'], $a['error_count']];
            foreach ($codes as $code) {
                $row[] = $a['counts'][$code];
                $row[] = $a['durations'][$code] ? CsvExport::duration((int) $a['durations'][$code]) : '';
            }
            foreach ($leaveCodes as $type) {
                $row[] = $a['leave_counts'][$type] ?: '';
                $row[] = $a['leave_seconds'][$type] ? CsvExport::duration((int) $a['leave_seconds'][$type]) : '';
            }
            $row[] = $a['paid_leave_seconds'] ? CsvExport::duration((int) $a['paid_leave_seconds']) : '';
            $row[] = $a['unpaid_leave_seconds'] ? CsvExport::duration((int) $a['unpaid_leave_seconds']) : '';
            $row[] = $a['unverifiable_days'];
            $row[] = CsvExport::duration((int) $a['recorded_work_seconds']);
            $row[] = CsvExport::duration((int) $a['actual_work_seconds']);
            $row[] = CsvExport::duration((int) $a['difference_seconds']);
            $csv->row($row);
        }
    }
    $csv->close();
    exit;
}

Response::success('Time error summary loaded.', $summary);
