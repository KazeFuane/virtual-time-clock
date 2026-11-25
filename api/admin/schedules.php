<?php
/**
 * Plan agent schedules (managers and administrators).
 *
 * GET  /api/admin/schedules.php?from=YYYY-MM-DD&to=YYYY-MM-DD[&search=][&format=csv]
 *      Team plan for the period: each agent's planned shifts per day, the
 *      schedules, and each agent's schedule history. format=csv downloads it.
 * POST /api/admin/schedules.php  {action, ...}
 *      save_template     {id?, name, week: {"1": [["08:00","12:00"], ...], ... "7": []}}
 *      delete_template   {id}
 *      set_default       {id}
 *      assign            {user_ids: [..], template_id, start_date}
 *      remove_assignment {id}
 *      set_day           {user_ids: [..], from, to?, shifts: [["08:00","12:00"], ...], note?}  ([] = day off)
 *      clear_day         {user_ids: [..], from, to?}                                           (back to the schedule)
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/LeaveService.php';
require_once ROOT_PATH . '/src/Services/ScheduleService.php';

$caller = AdminMiddleware::requireManager();

$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTime::createFromFormat('Y-m-d', $d)) && $dt->format('Y-m-d') === $d;

/** Each date from $from to $to (inclusive), at most MAX_RANGE_DAYS. */
$datesBetween = function (string $from, string $to) use ($isDate): array {
    if (!$isDate($from) || !$isDate($to) || $from > $to) {
        throw new RuntimeException('Invalid dates.');
    }
    $out = [];
    for ($d = new DateTime($from); $d->format('Y-m-d') <= $to; $d->modify('+1 day')) {
        $out[] = $d->format('Y-m-d');
        if (count($out) > ScheduleService::MAX_RANGE_DAYS) {
            throw new RuntimeException(t('Choose at most {n} days at once.', ['n' => ScheduleService::MAX_RANGE_DAYS]));
        }
    }
    return $out;
};

try {
    $db = Database::getInstance()->getConnection();
    $service = new ScheduleService($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CsrfMiddleware::validate();
        $in = Validator::getJsonInput();
        $userIds = array_values(array_filter(array_map('intval', (array) ($in['user_ids'] ?? []))));

        switch ($in['action'] ?? '') {
            case 'save_template':
                $r = $service->saveTemplate($caller, !empty($in['id']) ? (int) $in['id'] : null, (string) ($in['name'] ?? ''), $in['week'] ?? null);
                Response::success('Schedule saved.', $r);
            case 'delete_template':
                $service->deleteTemplate($caller, (int) ($in['id'] ?? 0));
                Response::success('Schedule deleted.');
            case 'set_default':
                $service->setDefault($caller, (int) ($in['id'] ?? 0));
                Response::success('Default schedule changed.');
            case 'assign':
                if (!$userIds) {
                    throw new RuntimeException('Choose at least one agent.');
                }
                foreach ($userIds as $uid) {
                    $service->checkPermission($caller, $uid);
                }
                $db->beginTransaction();
                foreach ($userIds as $uid) {
                    $service->assign($caller, $uid, (int) ($in['template_id'] ?? 0), (string) ($in['start_date'] ?? ''));
                }
                $db->commit();
                Response::success(count($userIds) > 1 ? t('Schedule assigned to {n} agents.', ['n' => count($userIds)]) : 'Schedule assigned.');
            case 'remove_assignment':
                $service->removeAssignment($caller, (int) ($in['id'] ?? 0));
                Response::success('Assignment removed.');
            case 'set_day':
            case 'clear_day':
                if (!$userIds) {
                    throw new RuntimeException('Choose at least one agent.');
                }
                $from = (string) ($in['from'] ?? '');
                $dates = $datesBetween($from, (string) (($in['to'] ?? '') ?: $from));
                foreach ($userIds as $uid) {
                    $service->checkPermission($caller, $uid);
                }
                $db->beginTransaction();
                foreach ($userIds as $uid) {
                    foreach ($dates as $date) {
                        if ($in['action'] === 'set_day') {
                            $service->setDay($caller, $uid, $date, $in['shifts'] ?? null, (string) ($in['note'] ?? ''));
                        } else {
                            $service->clearDay($caller, $uid, $date);
                        }
                    }
                }
                $db->commit();
                Response::success($in['action'] === 'set_day' ? 'Day planned.' : 'Back to the schedule.');
            default:
                Response::error('Unknown action.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed.', 405);
    }

    $today = TimeHelper::currentDate();
    $from = $_GET['from'] ?? (new DateTime($today))->modify('monday this week')->format('Y-m-d');
    $to = $_GET['to'] ?? (new DateTime($from))->modify('+6 days')->format('Y-m-d');
    $dates = $datesBetween($from, $to);
    $search = mb_strtolower(trim($_GET['search'] ?? ''));

    $agents = $service->agents($caller);
    if ($search !== '') {
        $agents = array_values(array_filter($agents, fn($a) => str_contains(mb_strtolower($a['first_name'] . ' ' . $a['last_name'] . ' ' . $a['username']), $search)));
    }
    $ids = array_map(fn($a) => (int) $a['id'], $agents);
    $plan = $service->planFor($ids, $from, $to);
    $leave = LeaveService::expand(LeaveService::activeRows($db, $from, $to), $from, $to);

    $history = [];
    foreach ($service->assignments($ids) as $a) {
        $history[(int) $a['user_id']][] = $a;
    }
    // Schedule each agent follows today
    $currentPlan = $service->planFor($ids, $today, $today);

    $rows = [];
    foreach ($agents as $a) {
        $uid = (int) $a['id'];
        $days = [];
        $total = 0;
        foreach ($dates as $date) {
            $p = $plan[$uid][$date];
            $leaveParts = $p['shifts'] ? ($leave[$uid][$date] ?? []) : [];
            $leaveList = [];
            foreach ($leaveParts as $part => $row) {
                $leaveList[] = ['type' => $row['leave_type'], 'label' => LeaveService::typeLabel($row['leave_type']), 'part' => $part, 'part_label' => LeaveService::partLabel($part)];
            }
            $secs = ScheduleService::totalSeconds($p['shifts']);
            $total += $secs;
            $days[$date] = $p + ['seconds' => $secs, 'label' => ScheduleService::label($p['shifts']), 'leave' => $leaveList];
        }
        $current = $currentPlan[$uid][$today] ?? null;
        $assigned = null;
        foreach ($history[$uid] ?? [] as $h) {
            if ($h['start_date'] <= $today) {
                $assigned = $h;
                break; // newest first
            }
        }
        $rows[] = [
            'id'               => $uid,
            'username'         => $a['username'],
            'name'             => trim($a['first_name'] . ' ' . $a['last_name']),
            'role'             => $a['role'],
            'schedule_now'     => $assigned ? $assigned['template_name'] : null,
            'schedule_now_id'  => $assigned ? (int) $assigned['template_id'] : null,
            'history'          => $history[$uid] ?? [],
            'days'             => $days,
            'planned_seconds'  => $total,
        ];
    }

    if (($_GET['format'] ?? '') === 'csv') {
        require_once ROOT_PATH . '/src/Helpers/CsvExport.php';
        $csv = new CsvExport('schedule_' . $from . '_' . $to . '.csv');
        $csv->row(array_map('t', ['Agent', 'Username', 'Date', 'Day', 'Planned shifts', 'Planned time', 'From', 'Note', 'Leave']));
        foreach ($rows as $r) {
            foreach ($r['days'] as $date => $d) {
                $source = $d['source'] === 'day' ? t('Day change') : ($d['template_name'] ?? t('Default'));
                $csv->row([
                    $r['name'], $r['username'], $date, t(ScheduleService::WEEKDAYS[(int) (new DateTime($date))->format('N')]),
                    $d['label'], $d['seconds'] ? CsvExport::duration($d['seconds']) : '', $source, $d['note'] ?? '',
                    implode(', ', array_map(fn($l) => $l['label'] . ($l['part'] === 'FULL' ? '' : ' (' . mb_strtolower($l['part_label']) . ')'), $d['leave'])),
                ]);
            }
        }
        $csv->close();
        exit;
    }

    Response::success('Schedule loaded.', [
        'from'      => $from,
        'to'        => $to,
        'today'     => $today,
        'dates'     => $dates,
        'agents'    => $rows,
        'templates' => $service->templates(),
        'weekdays'  => ScheduleService::WEEKDAYS,
        'max_shifts' => ScheduleService::MAX_SHIFTS_PER_DAY,
    ]);
} catch (RuntimeException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Response::error('An unexpected error occurred while planning schedules.', 500);
}
