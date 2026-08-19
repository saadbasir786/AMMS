<?php

declare(strict_types=1);

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('FLOW10A_DB_HOST') ?: '127.0.0.1', getenv('FLOW10A_DB_PORT') ?: '3306', getenv('FLOW10A_DB_DATABASE') ?: 'amms_concurrency_test');
$pdo = new PDO($dsn, getenv('FLOW10A_DB_USER') ?: 'amms_test', getenv('FLOW10A_DB_PASSWORD') ?: 'amms_test_password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE IF NOT EXISTS flow10a_template_race (employee_id BIGINT NOT NULL, finger_position VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, version_number INT NOT NULL, UNIQUE KEY flow10a_template_active (employee_id, finger_position, status)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS flow10a_attendance_race (meeting_id BIGINT NOT NULL, employee_id BIGINT NOT NULL, client_event_uuid CHAR(36) NOT NULL, UNIQUE KEY flow10a_attendance_unique (meeting_id, employee_id), UNIQUE KEY flow10a_attendance_event (client_event_uuid)) ENGINE=InnoDB');
$pdo->exec('DELETE FROM flow10a_template_race');
$pdo->exec('DELETE FROM flow10a_attendance_race');

function race(callable $insert): array {
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'flow10a_');
        $pid = pcntl_fork();
        if ($pid === 0) {
            try { $insert(); file_put_contents($tmp, 'PASS'); } catch (Throwable $e) { file_put_contents($tmp, 'DUPLICATE_OR_SERIALIZED:' . $e->getCode()); }
            exit(0);
        }
        $children[] = [$pid, $tmp];
    }
    foreach ($children as [$pid]) pcntl_waitpid($pid, $status);
    $results = array_map(fn ($item) => trim((string) file_get_contents($item[1])), $children);
    foreach ($children as [, $tmp]) @unlink($tmp);
    return $results;
}

$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
$templateResults = race(function () use ($dsn): void {
    $db = new PDO($dsn, getenv('FLOW10A_DB_USER') ?: 'amms_test', getenv('FLOW10A_DB_PASSWORD') ?: 'amms_test_password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->beginTransaction(); usleep(random_int(10000, 50000));
    $db->prepare('INSERT INTO flow10a_template_race (employee_id, finger_position, status, version_number) VALUES (?,?,?,?)')->execute([12, 'RIGHT_INDEX', 'ACTIVE', 1]); $db->commit();
});
$attendanceResults = race(function () use ($dsn): void {
    $db = new PDO($dsn, getenv('FLOW10A_DB_USER') ?: 'amms_test', getenv('FLOW10A_DB_PASSWORD') ?: 'amms_test_password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->beginTransaction(); usleep(random_int(10000, 50000));
    $db->prepare('INSERT INTO flow10a_attendance_race (meeting_id, employee_id, client_event_uuid) VALUES (?,?,?)')->execute([20, 12, sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffffffffffff))]); $db->commit();
});

$final = new PDO($dsn, getenv('FLOW10A_DB_USER') ?: 'amms_test', getenv('FLOW10A_DB_PASSWORD') ?: 'amms_test_password', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo json_encode(['engine' => 'MariaDB/InnoDB', 'template_race' => $templateResults, 'template_active_rows' => (int) $final->query("SELECT COUNT(*) FROM flow10a_template_race WHERE status='ACTIVE'")->fetchColumn(), 'attendance_race' => $attendanceResults, 'attendance_rows' => (int) $final->query('SELECT COUNT(*) FROM flow10a_attendance_race')->fetchColumn()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
