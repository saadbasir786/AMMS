<?php

declare(strict_types=1);

$dsn = 'mysql:host='.($_ENV['AMMS_MYSQL_HOST'] ?? '127.0.0.1')
    .';port='.($_ENV['AMMS_MYSQL_PORT'] ?? '3306')
    .';dbname='.($_ENV['AMMS_MYSQL_DATABASE'] ?? 'amms_concurrency_test')
    .';charset=utf8mb4';
$user = $_ENV['AMMS_MYSQL_USERNAME'] ?? 'amms_test';
$password = $_ENV['AMMS_MYSQL_PASSWORD'] ?? 'amms_test_password';
$connect = static fn (): PDO => new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo = $connect();
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_schedule_conflict_race_lock (schedule_date DATE PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_schedule_conflict_race_reservation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_type VARCHAR(20) NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    KEY resource_slot (resource_type, resource_id, meeting_date, start_time, end_time)
) ENGINE=InnoDB');

$runTwo = static function (callable $worker): array {
    if (!function_exists('pcntl_fork')) throw new RuntimeException('pcntl extension is required for this race probe.');
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pipe === false) throw new RuntimeException('Unable to create worker pipe.');
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Unable to fork worker.');
        if ($pid === 0) {
            fclose($pipe[0]);
            try {
                fwrite($pipe[1], $worker($i));
            } catch (Throwable $exception) {
                fwrite($pipe[1], 'ERROR:'.$exception->getMessage());
            }
            fclose($pipe[1]);
            exit(0);
        }
        fclose($pipe[1]);
        $children[] = [$pid, $pipe[0]];
    }

    $results = [];
    foreach ($children as [$pid, $pipe]) {
        $results[] = trim(stream_get_contents($pipe));
        fclose($pipe);
        pcntl_waitpid($pid, $status);
    }
    return $results;
};

$runResourceRace = static function (string $resourceType, int $resourceId, string $conflictCode) use ($connect, $runTwo): void {
    $setup = $connect();
    $setup->exec('DELETE FROM amms_schedule_conflict_race_reservation');
    $setup->exec('DELETE FROM amms_schedule_conflict_race_lock');
    $setup = null;

    $date = '2026-10-15';
    $results = $runTwo(static function (int $worker) use ($connect, $resourceType, $resourceId, $conflictCode, $date): string {
        $db = $connect();
        $db->beginTransaction();

        $insertLock = $db->prepare('INSERT IGNORE INTO amms_schedule_conflict_race_lock (schedule_date) VALUES (?)');
        $insertLock->execute([$date]);
        $lock = $db->prepare('SELECT schedule_date FROM amms_schedule_conflict_race_lock WHERE schedule_date=? FOR UPDATE');
        $lock->execute([$date]);
        $lock->fetch();

        $busy = $db->prepare('SELECT COUNT(*) FROM amms_schedule_conflict_race_reservation WHERE resource_type=? AND resource_id=? AND meeting_date=? AND start_time<? AND end_time>?');
        $busy->execute([$resourceType, $resourceId, $date, '11:00:00', '10:00:00']);
        if ((int) $busy->fetchColumn() > 0) {
            $db->rollBack();
            return $conflictCode;
        }

        $insert = $db->prepare('INSERT INTO amms_schedule_conflict_race_reservation (resource_type,resource_id,meeting_date,start_time,end_time) VALUES (?,?,?,?,?)');
        $insert->execute([$resourceType, $resourceId, $date, '10:00:00', '11:00:00']);
        usleep(150000);
        $db->commit();
        return 'APPLIED_'.$worker;
    });

    $check = $connect();
    $rows = (int) $check->query('SELECT COUNT(*) FROM amms_schedule_conflict_race_reservation')->fetchColumn();
    $applied = count(array_filter($results, static fn (string $value): bool => str_starts_with($value, 'APPLIED_')));
    $conflicts = count(array_filter($results, static fn (string $value): bool => $value === $conflictCode));

    if ($applied !== 1 || $conflicts !== 1 || $rows !== 1) {
        throw new RuntimeException(json_encode([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'results' => $results,
            'rows' => $rows,
        ], JSON_UNESCAPED_SLASHES));
    }
};

$runResourceRace('EMPLOYEE', 9001, 'PARTICIPANT_SCHEDULE_CONFLICT');
$runResourceRace('VENUE', 7001, 'INTERNAL_VENUE_SCHEDULE_CONFLICT');

echo 'MariaDB schedule conflict race checks passed: date-level InnoDB locking serialized simultaneous employee and internal-venue reservations so exactly one candidate applied and the competing overlapping candidate was rejected.', PHP_EOL;
