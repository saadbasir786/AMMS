<?php
declare(strict_types=1);

$dsn = 'mysql:host='.($_ENV['AMMS_MYSQL_HOST'] ?? '127.0.0.1').';port='.($_ENV['AMMS_MYSQL_PORT'] ?? '3306').';dbname='.($_ENV['AMMS_MYSQL_DATABASE'] ?? 'amms_concurrency_test').';charset=utf8mb4';
$user = $_ENV['AMMS_MYSQL_USERNAME'] ?? 'amms_test';
$password = $_ENV['AMMS_MYSQL_PASSWORD'] ?? 'amms_test_password';
$connect = static fn (): PDO => new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$root = $connect();
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_race_terminal (terminal_id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(20) NOT NULL, current_session_id BIGINT UNSIGNED NULL) ENGINE=InnoDB');
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_race_assignment (assignment_id BIGINT UNSIGNED PRIMARY KEY, terminal_id BIGINT UNSIGNED NOT NULL, meeting_id BIGINT UNSIGNED NOT NULL, valid_from DATETIME NOT NULL, valid_until DATETIME NOT NULL, status VARCHAR(20) NOT NULL, KEY terminal_window (terminal_id, valid_from, valid_until, status)) ENGINE=InnoDB');
$root->exec('CREATE TABLE IF NOT EXISTS amms_flow09_race_session (session_id BIGINT UNSIGNED PRIMARY KEY, terminal_id BIGINT UNSIGNED NOT NULL, assignment_id BIGINT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL, KEY terminal_session (terminal_id, status)) ENGINE=InnoDB');

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
    $db->exec('DELETE FROM amms_flow09_race_session');
    $db->exec('DELETE FROM amms_flow09_race_assignment');
    $db->exec('DELETE FROM amms_flow09_race_terminal');
};

$reset();
$db = $connect();
$db->exec("INSERT INTO amms_flow09_race_terminal (terminal_id,status,current_session_id) VALUES (9009,'ACTIVE',NULL)");
$db->exec("INSERT INTO amms_flow09_race_assignment (assignment_id,terminal_id,meeting_id,valid_from,valid_until,status) VALUES (9101,9009,9101,'2026-10-14 10:00:00','2026-10-14 13:00:00','ASSIGNED')");
$activation = $runTwo(static function (int $worker) use ($connect): string {
    $db = $connect(); $db->beginTransaction();
    $terminal = $db->query('SELECT * FROM amms_flow09_race_terminal WHERE terminal_id=9009 FOR UPDATE')->fetch();
    $assignment = $db->query("SELECT * FROM amms_flow09_race_assignment WHERE assignment_id=9101 FOR UPDATE")->fetch();
    if ($terminal['status'] !== 'ACTIVE' || $assignment['status'] !== 'ASSIGNED') { $db->rollBack(); return 'TERMINAL_ASSIGNMENT_UNAVAILABLE'; }
    if ($terminal['current_session_id'] !== null) { $db->rollBack(); return 'TERMINAL_SESSION_ALREADY_ACTIVE'; }
    $sessionId = 9200 + $worker;
    $db->prepare("INSERT INTO amms_flow09_race_session (session_id,terminal_id,assignment_id,status) VALUES (?,?,?,'ACTIVE')")->execute([$sessionId,9009,9101]);
    usleep(120000);
    $db->prepare('UPDATE amms_flow09_race_terminal SET current_session_id=? WHERE terminal_id=9009')->execute([$sessionId]);
    $db->commit(); return 'ACTIVE_SESSION_'.$sessionId;
});
$db = $connect();
$activeSessions = (int) $db->query("SELECT COUNT(*) FROM amms_flow09_race_session WHERE terminal_id=9009 AND status='ACTIVE'")->fetchColumn();
$currentSession = (int) $db->query('SELECT current_session_id FROM amms_flow09_race_terminal WHERE terminal_id=9009')->fetchColumn();
if (count(array_filter($activation, fn (string $v): bool => str_starts_with($v, 'ACTIVE_SESSION_'))) !== 1 || count(array_filter($activation, fn (string $v): bool => $v === 'TERMINAL_SESSION_ALREADY_ACTIVE')) !== 1 || $activeSessions !== 1 || $currentSession < 9200) throw new RuntimeException(json_encode(['activation'=>$activation,'active_sessions'=>$activeSessions,'current_session'=>$currentSession]));

$reset();
$db = $connect();
$db->exec("INSERT INTO amms_flow09_race_terminal (terminal_id,status,current_session_id) VALUES (9009,'ACTIVE',NULL)");
$assignmentRace = $runTwo(static function (int $worker) use ($connect): string {
    $db = $connect(); $db->beginTransaction();
    $db->query('SELECT terminal_id FROM amms_flow09_race_terminal WHERE terminal_id=9009 FOR UPDATE')->fetch();
    $from = '2026-10-14 10:00:00'; $until = '2026-10-14 13:00:00'; $assignmentId = 9300 + $worker;
    $check = $db->prepare("SELECT COUNT(*) FROM amms_flow09_race_assignment WHERE terminal_id=9009 AND status='ASSIGNED' AND valid_from < ? AND valid_until > ?");
    $check->execute([$until,$from]);
    if ((int) $check->fetchColumn() > 0) { $db->rollBack(); return 'TERMINAL_ASSIGNMENT_CONFLICT'; }
    $db->prepare("INSERT INTO amms_flow09_race_assignment (assignment_id,terminal_id,meeting_id,valid_from,valid_until,status) VALUES (?,?,?, ?,?,'ASSIGNED')")->execute([$assignmentId,9009,9400+$worker,$from,$until]);
    usleep(120000); $db->commit(); return 'ASSIGNMENT_APPLIED_'.$assignmentId;
});
$db = $connect();
$assignmentRows = (int) $db->query("SELECT COUNT(*) FROM amms_flow09_race_assignment WHERE terminal_id=9009 AND status='ASSIGNED'")->fetchColumn();
if (count(array_filter($assignmentRace, fn (string $v): bool => str_starts_with($v, 'ASSIGNMENT_APPLIED_'))) !== 1 || count(array_filter($assignmentRace, fn (string $v): bool => $v === 'TERMINAL_ASSIGNMENT_CONFLICT')) !== 1 || $assignmentRows !== 1) throw new RuntimeException(json_encode(['assignment_race'=>$assignmentRace,'rows'=>$assignmentRows]));

$reset();
$db = $connect();
$db->exec("INSERT INTO amms_flow09_race_terminal (terminal_id,status,current_session_id) VALUES (9009,'ACTIVE',9501)");
$db->exec("INSERT INTO amms_flow09_race_assignment (assignment_id,terminal_id,meeting_id,valid_from,valid_until,status) VALUES (9501,9009,9501,'2026-10-14 10:00:00','2026-10-14 13:00:00','ASSIGNED')");
$db->exec("INSERT INTO amms_flow09_race_session (session_id,terminal_id,assignment_id,status) VALUES (9501,9009,9501,'ACTIVE')");
$revocationRace = $runTwo(static function (int $worker) use ($connect): string {
    $db = $connect(); $db->beginTransaction();
    $terminal = $db->query('SELECT * FROM amms_flow09_race_terminal WHERE terminal_id=9009 FOR UPDATE')->fetch();
    $session = $db->query('SELECT * FROM amms_flow09_race_session WHERE session_id=9501 FOR UPDATE')->fetch();
    if ($worker === 0) { if ($terminal['status'] !== 'ACTIVE' || $session['status'] !== 'ACTIVE') { $db->rollBack(); return 'SESSION_REJECTED_AFTER_REVOKE'; } $db->commit(); return 'SESSION_OBSERVED_BEFORE_REVOKE'; }
    $db->exec("UPDATE amms_flow09_race_session SET status='REVOKED' WHERE session_id=9501");
    $db->exec("UPDATE amms_flow09_race_assignment SET status='REVOKED' WHERE assignment_id=9501");
    $db->exec("UPDATE amms_flow09_race_terminal SET status='REVOKED',current_session_id=NULL WHERE terminal_id=9009");
    $db->commit(); return 'REVOKE_COMMITTED';
});
$db = $connect();
$final = $db->query('SELECT t.status terminal_status,t.current_session_id,s.status session_status,a.status assignment_status FROM amms_flow09_race_terminal t JOIN amms_flow09_race_session s ON s.session_id=9501 JOIN amms_flow09_race_assignment a ON a.assignment_id=9501 WHERE t.terminal_id=9009')->fetch();
if (($final['terminal_status'] ?? null) !== 'REVOKED' || $final['current_session_id'] !== null || ($final['session_status'] ?? null) !== 'REVOKED' || ($final['assignment_status'] ?? null) !== 'REVOKED' || !in_array('REVOKE_COMMITTED', $revocationRace, true)) throw new RuntimeException(json_encode(['revocation_race'=>$revocationRace,'final'=>$final]));

echo 'MariaDB FLOW-09 race checks passed: concurrent activation produced exactly one ACTIVE session and one TERMINAL_SESSION_ALREADY_ACTIVE; overlapping assignment produced one success and one TERMINAL_ASSIGNMENT_CONFLICT; revocation committed with terminal, assignment, session, and current pointer invalidated.'.PHP_EOL;
