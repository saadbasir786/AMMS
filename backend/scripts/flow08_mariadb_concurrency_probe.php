<?php
$dsn = 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=amms_concurrency_test;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_AUTOCOMMIT => true]);
$pdo->exec("CREATE TABLE IF NOT EXISTS amms_concurrency_flow08_version_probe (
    meeting_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    current_version INT NOT NULL,
    active_case_id BIGINT UNSIGNED NULL,
    transition_count INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS amms_concurrency_flow08_active_case_probe (
    meeting_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    active_case_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$meetingId = 8008;
$pdo->prepare('DELETE FROM amms_concurrency_flow08_version_probe WHERE meeting_id=?')->execute([$meetingId]);
$pdo->prepare('DELETE FROM amms_concurrency_flow08_active_case_probe WHERE meeting_id=?')->execute([$meetingId]);
$pdo->prepare('INSERT INTO amms_concurrency_flow08_version_probe (meeting_id,current_version,active_case_id,transition_count) VALUES (?,?,?,0)')->execute([$meetingId, 1, 99]);
$pdo->prepare('INSERT INTO amms_concurrency_flow08_active_case_probe (meeting_id,active_case_id) VALUES (?,?)')->execute([$meetingId, 99]);
$pdo = null;
$results = [];
$children = [];
for ($i = 0; $i < 2; $i++) {
    $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($pipe[0]);
        $db = new PDO($dsn, 'root', null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->beginTransaction();
        $row = $db->query("SELECT meeting_id,current_version,active_case_id FROM amms_concurrency_flow08_version_probe WHERE meeting_id={$meetingId} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if ($i === 0) usleep(250000);
        if ($row['active_case_id'] !== null) {
            $db->prepare('UPDATE amms_concurrency_flow08_version_probe SET current_version=current_version+1, active_case_id=NULL, transition_count=transition_count+1 WHERE meeting_id=?')->execute([$meetingId]);
            $db->prepare('DELETE FROM amms_concurrency_flow08_active_case_probe WHERE meeting_id=?')->execute([$meetingId]);
            $outcome = 'APPLIED';
        } else {
            $outcome = 'REJECTED_DUPLICATE';
        }
        $db->commit();
        fwrite($pipe[1], $outcome);
        fclose($pipe[1]);
        exit(0);
    }
    fclose($pipe[1]);
    $children[] = [$pid, $pipe[0]];
}
foreach ($children as [$pid, $pipe]) { $results[] = stream_get_contents($pipe); fclose($pipe); pcntl_waitpid($pid, $status); }
$pdo = new PDO($dsn, 'root', null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$final = $pdo->query("SELECT current_version,active_case_id,transition_count FROM amms_concurrency_flow08_version_probe WHERE meeting_id={$meetingId}")->fetch(PDO::FETCH_ASSOC);
$uniqueCheck = 'PASS';
try {
    $pdo->prepare('INSERT INTO amms_concurrency_flow08_active_case_probe (meeting_id,active_case_id) VALUES (?,?)')->execute([$meetingId, 100]);
    $pdo->prepare('INSERT INTO amms_concurrency_flow08_active_case_probe (meeting_id,active_case_id) VALUES (?,?)')->execute([$meetingId, 101]);
    $uniqueCheck = 'FAIL';
} catch (Throwable) { $uniqueCheck = 'PASS'; }
$pdo->prepare('DELETE FROM amms_concurrency_flow08_active_case_probe WHERE meeting_id=?')->execute([$meetingId]);
if ((int) $final['current_version'] !== 2 || (int) $final['transition_count'] !== 1 || $final['active_case_id'] !== null || count(array_filter($results, fn ($r) => trim($r) === 'APPLIED')) !== 1 || $uniqueCheck !== 'PASS') {
    fwrite(STDERR, json_encode(['results'=>$results,'final'=>$final,'unique_check'=>$uniqueCheck]), PHP_EOL);
    exit(1);
}
echo 'MariaDB FLOW-08 concurrency checks passed: duplicate confirmation serialized to one applied transition and one rejected duplicate; final participant version=2; active case cleared; one-active-case uniqueness held.', PHP_EOL;
