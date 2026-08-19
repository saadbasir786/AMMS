<?php
declare(strict_types=1);

$dsn = 'mysql:host='.($_ENV['AMMS_MYSQL_HOST'] ?? '127.0.0.1').';port='.($_ENV['AMMS_MYSQL_PORT'] ?? '3306').';dbname='.($_ENV['AMMS_MYSQL_DATABASE'] ?? 'amms_concurrency_test').';charset=utf8mb4';
$user = $_ENV['AMMS_MYSQL_USERNAME'] ?? 'amms_test';
$password = $_ENV['AMMS_MYSQL_PASSWORD'] ?? 'amms_test_password';
$connect = static fn (): PDO => new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$root = $connect();
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_version_race_meeting (meeting_id BIGINT UNSIGNED PRIMARY KEY, schedule_version INT NOT NULL, participant_version INT NOT NULL) ENGINE=InnoDB');
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_version_race_assignment (assignment_id BIGINT UNSIGNED PRIMARY KEY, meeting_id BIGINT UNSIGNED NOT NULL, baseline_schedule_version INT NOT NULL, baseline_participant_version INT NOT NULL, status VARCHAR(20) NOT NULL) ENGINE=InnoDB');
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_version_race_session (session_id BIGINT UNSIGNED PRIMARY KEY, assignment_id BIGINT UNSIGNED NOT NULL, meeting_id BIGINT UNSIGNED NOT NULL, schedule_version INT NOT NULL, participant_version INT NOT NULL, status VARCHAR(20) NOT NULL) ENGINE=InnoDB');

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
$reset = static function () use ($connect): void {
    $db = $connect();
    $db->exec('DELETE FROM amms_flow09_version_race_session');
    $db->exec('DELETE FROM amms_flow09_version_race_assignment');
    $db->exec('DELETE FROM amms_flow09_version_race_meeting');
    $db->exec("INSERT INTO amms_flow09_version_race_meeting (meeting_id,schedule_version,participant_version) VALUES (9909,1,1)");
    $db->exec("INSERT INTO amms_flow09_version_race_assignment (assignment_id,meeting_id,baseline_schedule_version,baseline_participant_version,status) VALUES (9910,9909,1,1,'ASSIGNED')");
};

$runVersionRace = static function (string $kind, bool $changeFirst) use ($connect, $runTwo, $reset): array {
    $reset();
    $results = $runTwo(static function (int $worker) use ($connect, $kind, $changeFirst): string {
        $isChange = $changeFirst ? $worker === 0 : $worker === 1;
        if (($changeFirst && !$isChange) || (!$changeFirst && $isChange)) usleep(120000);
        $db = $connect(); $db->beginTransaction();
        $meeting = $db->query('SELECT * FROM amms_flow09_version_race_meeting WHERE meeting_id=9909 FOR UPDATE')->fetch();
        $assignment = $db->query('SELECT * FROM amms_flow09_version_race_assignment WHERE assignment_id=9910 FOR UPDATE')->fetch();
        if ($isChange) {
            $column = $kind === 'schedule' ? 'schedule_version' : 'participant_version';
            $db->exec("UPDATE amms_flow09_version_race_meeting SET {$column}=2 WHERE meeting_id=9909");
            $session = $db->query('SELECT * FROM amms_flow09_version_race_session WHERE assignment_id=9910 AND status=\'ACTIVE\' FOR UPDATE')->fetch();
            if ($session) {
                $db->exec("UPDATE amms_flow09_version_race_session SET status='STALE' WHERE session_id=".(int) $session['session_id']);
                $db->commit();
                return strtoupper($kind).'_V2_APPLIED_SESSION_STALE';
            }
            $db->commit();
            return strtoupper($kind).'_V2_APPLIED';
        }
        $changed = $kind === 'schedule'
            ? (int) $assignment['baseline_schedule_version'] !== (int) $meeting['schedule_version']
            : (int) $assignment['baseline_participant_version'] !== (int) $meeting['participant_version'];
        if ($changed) { $db->rollBack(); return 'TERMINAL_ASSIGNMENT_STALE'; }
        $sessionId = $kind === 'schedule' ? 9920 + $worker : 9930 + $worker;
        $db->prepare('INSERT INTO amms_flow09_version_race_session (session_id,assignment_id,meeting_id,schedule_version,participant_version,status) VALUES (?,?,?,?,?,\'ACTIVE\')')->execute([$sessionId,9910,9909,$meeting['schedule_version'],$meeting['participant_version']]);
        usleep(80000); $db->commit(); return 'ACTIVATION_BEFORE_'.strtoupper($kind).'_V2';
    });
    $db = $connect();
    $meeting = $db->query('SELECT * FROM amms_flow09_version_race_meeting WHERE meeting_id=9909')->fetch();
    $active = (int) $db->query("SELECT COUNT(*) FROM amms_flow09_version_race_session WHERE assignment_id=9910 AND status='ACTIVE'")->fetchColumn();
    $stale = (int) $db->query("SELECT COUNT(*) FROM amms_flow09_version_race_session WHERE assignment_id=9910 AND status='STALE'")->fetchColumn();
    if (($kind === 'schedule' && (int) $meeting['schedule_version'] !== 2) || ($kind === 'participant' && (int) $meeting['participant_version'] !== 2) || $active !== 0 || $stale > 1 || !in_array('TERMINAL_ASSIGNMENT_STALE', $results, true) && !$stale) throw new RuntimeException(json_encode(['kind'=>$kind,'change_first'=>$changeFirst,'results'=>$results,'meeting'=>$meeting,'active'=>$active,'stale'=>$stale]));
    return ['kind'=>$kind,'change_first'=>$changeFirst,'results'=>$results,'final_version'=>$kind === 'schedule' ? (int) $meeting['schedule_version'] : (int) $meeting['participant_version'],'active_sessions'=>$active,'stale_sessions'=>$stale];
};

$results = [];
foreach (['schedule', 'participant'] as $kind) foreach ([true, false] as $changeFirst) $results[] = $runVersionRace($kind, $changeFirst);
echo 'MariaDB FLOW-07/FLOW-09 and FLOW-08/FLOW-09 version races passed: '.json_encode($results, JSON_UNESCAPED_SLASHES).PHP_EOL;
