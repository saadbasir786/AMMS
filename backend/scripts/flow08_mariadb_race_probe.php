<?php
declare(strict_types=1);

$dsn = 'mysql:host='.($_ENV['AMMS_MYSQL_HOST'] ?? '127.0.0.1').';port='.($_ENV['AMMS_MYSQL_PORT'] ?? '3306').';dbname='.($_ENV['AMMS_MYSQL_DATABASE'] ?? 'amms_concurrency_test').';charset=utf8mb4';
$user = $_ENV['AMMS_MYSQL_USERNAME'] ?? 'amms_test';
$password = $_ENV['AMMS_MYSQL_PASSWORD'] ?? 'amms_test_password';
$connect = static fn (): PDO => new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo = $connect();
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_flow08_race_employee_lock (employee_id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_flow08_race_occupancy (meeting_id BIGINT UNSIGNED NOT NULL, employee_id BIGINT UNSIGNED NOT NULL, meeting_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, PRIMARY KEY (meeting_id, employee_id), KEY employee_slot (employee_id, meeting_date, start_time, end_time)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_flow08_race_meeting (meeting_id BIGINT UNSIGNED PRIMARY KEY, current_schedule_version INT NOT NULL, participant_baseline_schedule_version INT NOT NULL, current_participant_version INT NOT NULL, participant_transition_count INT NOT NULL DEFAULT 0, reschedule_transition_count INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$pdo->exec('DELETE FROM amms_flow08_race_occupancy');
$pdo->exec('DELETE FROM amms_flow08_race_meeting');
$pdo->exec('DELETE FROM amms_flow08_race_employee_lock');
$pdo->exec('INSERT INTO amms_flow08_race_employee_lock (employee_id) VALUES (9008)');

$runTwo = static function (callable $worker): array {
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            fclose($pipe[0]);
            try { fwrite($pipe[1], $worker($i)); } catch (Throwable $e) { fwrite($pipe[1], 'ERROR:'.$e->getMessage()); }
            fclose($pipe[1]); exit(0);
        }
        fclose($pipe[1]); $children[] = [$pid, $pipe[0]];
    }
    $results = [];
    foreach ($children as [$pid, $pipe]) { $results[] = trim(stream_get_contents($pipe)); fclose($pipe); pcntl_waitpid($pid, $status); }
    return $results;
};

$employeeRaceResults = $runTwo(static function (int $worker) use ($connect): string {
    $db = $connect();
    $meetingId = 8101 + $worker;
    $db->beginTransaction();
    $db->query('SELECT employee_id FROM amms_flow08_race_employee_lock WHERE employee_id=9008 FOR UPDATE')->fetch();
    $busy = $db->prepare('SELECT COUNT(*) FROM amms_flow08_race_occupancy WHERE employee_id=9008 AND meeting_date=? AND start_time<? AND end_time>?');
    $busy->execute(['2026-10-01','11:00:00','10:00:00']);
    if ((int) $busy->fetchColumn() > 0) { $db->rollBack(); return 'PARTICIPANT_SCHEDULE_CONFLICT'; }
    $insert = $db->prepare('INSERT INTO amms_flow08_race_occupancy (meeting_id,employee_id,meeting_date,start_time,end_time) VALUES (?,?,?,?,?)');
    $insert->execute([$meetingId,9008,'2026-10-01','10:00:00','11:00:00']);
    usleep(150000);
    $db->commit();
    return 'APPLIED_MEETING_'.$meetingId;
});
$employeePdo = $connect();
$employeeRows = (int) $employeePdo->query('SELECT COUNT(*) FROM amms_flow08_race_occupancy WHERE employee_id=9008')->fetchColumn();
$employeeApplied = count(array_filter($employeeRaceResults, fn (string $v): bool => str_starts_with($v, 'APPLIED_')));
$employeeConflicts = count(array_filter($employeeRaceResults, fn (string $v): bool => $v === 'PARTICIPANT_SCHEDULE_CONFLICT'));
if ($employeeApplied !== 1 || $employeeConflicts !== 1 || $employeeRows !== 1) throw new RuntimeException(json_encode(['employee_race'=>$employeeRaceResults,'rows'=>$employeeRows]));

$runScheduleRace = static function (bool $rescheduleFirst) use ($connect, $runTwo): array {
    $setup = $connect();
    $setup->exec('DELETE FROM amms_flow08_race_meeting');
    $setup->exec('INSERT INTO amms_flow08_race_meeting (meeting_id,current_schedule_version,participant_baseline_schedule_version,current_participant_version,participant_transition_count,reschedule_transition_count) VALUES (8300,1,1,1,0,0)');
    $setup = null;
    return $runTwo(static function (int $worker) use ($connect, $rescheduleFirst): string {
        $isReschedule = $rescheduleFirst ? $worker === 0 : $worker === 1;
        if ($rescheduleFirst ? !$isReschedule : $isReschedule) usleep(100000);
        $db = $connect();
        $db->beginTransaction();
        $row = $db->query('SELECT * FROM amms_flow08_race_meeting WHERE meeting_id=8300 FOR UPDATE')->fetch();
        if ($isReschedule) {
            usleep(150000);
            if ((int) $row['current_schedule_version'] !== 1) { $db->rollBack(); return 'RESCHEDULE_REJECTED_STALE'; }
            $db->exec('UPDATE amms_flow08_race_meeting SET current_schedule_version=2,reschedule_transition_count=reschedule_transition_count+1 WHERE meeting_id=8300');
            $db->commit(); return 'RESCHEDULE_APPLIED';
        }
        if ((int) $row['participant_baseline_schedule_version'] !== (int) $row['current_schedule_version']) {
            $db->rollBack(); return 'PARTICIPANT_REJECTED_STALE';
        }
        $db->exec('UPDATE amms_flow08_race_meeting SET current_participant_version=current_participant_version+1,participant_transition_count=participant_transition_count+1 WHERE meeting_id=8300');
        $db->commit(); return 'PARTICIPANT_APPLIED';
    });
};

$first = $runScheduleRace(true);
$second = $runScheduleRace(false);
$final = $connect()->query('SELECT * FROM amms_flow08_race_meeting WHERE meeting_id=8300')->fetch();
$firstValid = in_array('RESCHEDULE_APPLIED', $first, true) && in_array('PARTICIPANT_REJECTED_STALE', $first, true);
$secondValid = in_array('RESCHEDULE_APPLIED', $second, true) && in_array('PARTICIPANT_APPLIED', $second, true);
if (!$firstValid || !$secondValid || (int)$final['current_schedule_version'] !== 2 || (int)$final['participant_transition_count'] !== 1 || (int)$final['reschedule_transition_count'] !== 1) {
    throw new RuntimeException(json_encode(['reschedule_first'=>$first,'participant_first'=>$second,'final'=>$final]));
}

echo 'MariaDB FLOW-08 race checks passed: same employee in two overlapping Meetings serialized to one applied candidate and one PARTICIPANT_SCHEDULE_CONFLICT; FLOW-07-first ordering rejected stale FLOW-08 confirmation; FLOW-08-first ordering applied participant before the later schedule transition; final schedule and participant transitions remained single and consistent.', PHP_EOL;
