<?php

declare(strict_types=1);

$host = getenv('AMMS_MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('AMMS_MYSQL_PORT') ?: '3306';
$db = getenv('AMMS_MYSQL_DATABASE') ?: 'amms_concurrency_test';
$user = getenv('AMMS_MYSQL_USERNAME') ?: 'amms_test';
$password = getenv('AMMS_MYSQL_PASSWORD') ?: 'amms_test_password';
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$connect = static fn (): PDO => new PDO($dsn, $user, $password, $options);

$pdo = $connect();
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_probe (id BIGINT UNSIGNED PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_meeting_probe (meeting_request_id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow02_request_probe (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow03_target_probe (id BIGINT UNSIGNED PRIMARY KEY, finance_status VARCHAR(32) NOT NULL, hr_status VARCHAR(32) NOT NULL, status VARCHAR(64) NOT NULL, multilateral_transitions INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow03_acceptance_probe (proposal_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, accepted_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (proposal_id, unit_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow03_meeting_unit_probe (meeting_request_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (meeting_request_id, unit_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow03_request_probe (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow04_request_probe (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(64) NOT NULL, office_status VARCHAR(32) NOT NULL, authority_status VARCHAR(32) NOT NULL, requester_ack INT NOT NULL, requester_confirm INT NOT NULL, authority_confirm INT NOT NULL, finalize_transitions INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow04_ack_probe (proposal_id BIGINT UNSIGNED NOT NULL, requester_unit_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (proposal_id, requester_unit_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow04_authority_context_probe (meeting_request_id BIGINT UNSIGNED PRIMARY KEY, authority_employee_id BIGINT UNSIGNED NOT NULL, authority_type VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow05_request_probe (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(64) NOT NULL, office_status VARCHAR(32) NOT NULL, authority_status VARCHAR(32) NOT NULL, finalize_transitions INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow05_ack_probe (proposal_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, acknowledged_by BIGINT UNSIGNED NOT NULL, PRIMARY KEY (proposal_id, unit_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow05_context_probe (meeting_request_id BIGINT UNSIGNED PRIMARY KEY, authority_employee_id BIGINT UNSIGNED NOT NULL, authority_type VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow05_meeting_unit_probe (meeting_request_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (meeting_request_id, unit_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow06_request_probe (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(64) NOT NULL, requesting_unit_id BIGINT UNSIGNED NULL, participating_units INT NOT NULL, finalize_transitions INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow06_context_probe (meeting_request_id BIGINT UNSIGNED PRIMARY KEY, authority_employee_id BIGINT UNSIGNED NOT NULL, authority_type VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS amms_concurrency_flow06_meeting_unit_probe (meeting_request_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL, role VARCHAR(32) NOT NULL, PRIMARY KEY (meeting_request_id, unit_id)) ENGINE=InnoDB');
$pdo->exec('DELETE FROM amms_concurrency_probe');
$pdo->exec('DELETE FROM amms_concurrency_meeting_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow02_request_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow03_target_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow03_acceptance_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow03_meeting_unit_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow03_request_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow04_request_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow04_ack_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow04_authority_context_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow05_request_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow05_ack_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow05_context_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow05_meeting_unit_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow06_request_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow06_context_probe');
$pdo->exec('DELETE FROM amms_concurrency_flow06_meeting_unit_probe');
$pdo->exec('INSERT INTO amms_concurrency_probe (id, value) VALUES (1, 0)');

$runConcurrent = static function (callable $work): array {
    $results = [];
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'amms_mysql_');
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Could not fork concurrency worker.');
        if ($pid === 0) {
            try {
                $work($tmp);
                file_put_contents($tmp, "ok\n");
                exit(0);
            } catch (Throwable $e) {
                file_put_contents($tmp, "error:" . $e->getMessage() . "\n");
                exit(1);
            }
        }
        $children[] = [$pid, $tmp];
    }
    foreach ($children as [$pid, $tmp]) {
        pcntl_waitpid($pid, $status);
        $results[] = ['status' => pcntl_wexitstatus($status), 'output' => trim((string) file_get_contents($tmp))];
        unlink($tmp);
    }
    return $results;
};

$runIndexedConcurrent = static function (callable $work): array {
    $results = [];
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'amms_mysql_');
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Could not fork indexed concurrency worker.');
        if ($pid === 0) {
            try {
                $work($i);
                file_put_contents($tmp, "ok\n");
                exit(0);
            } catch (Throwable $e) {
                file_put_contents($tmp, "error:" . $e->getMessage() . "\n");
                exit(1);
            }
        }
        $children[] = [$pid, $tmp];
    }
    foreach ($children as [$pid, $tmp]) {
        pcntl_waitpid($pid, $status);
        $results[] = ['status' => pcntl_wexitstatus($status), 'output' => trim((string) file_get_contents($tmp))];
        unlink($tmp);
    }
    return $results;
};

$lockResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $row = $db->query('SELECT value FROM amms_concurrency_probe WHERE id = 1 FOR UPDATE')->fetch();
    usleep(200000);
    $db->prepare('UPDATE amms_concurrency_probe SET value = ? WHERE id = 1')->execute([(int) $row['value'] + 1]);
    $db->commit();
});
$pdo = $connect();
$finalValue = (int) $pdo->query('SELECT value FROM amms_concurrency_probe WHERE id = 1')->fetchColumn();
if ($finalValue !== 2 || count(array_filter($lockResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('Row-lock serialization failed: ' . json_encode(['results' => $lockResults, 'final' => $finalValue]));
}

$pdo->exec('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (1)');
$pdo = null;
$insertResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([1]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() !== '23000') throw $e;
    }
});
$pdo = $connect();
$insertCount = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 1')->fetchColumn();
if ($insertCount !== 1 || count(array_filter($insertResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('One-meeting uniqueness invariant failed: ' . json_encode(['results' => $insertResults, 'count' => $insertCount]));
}

$pdo->exec("INSERT INTO amms_concurrency_flow02_request_probe (id, status) VALUES (1, 'SCHEDULE_CONFIRMED')");
$pdo = null;
$finalizeResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $state = $db->query("SELECT status FROM amms_concurrency_flow02_request_probe WHERE id = 1 FOR UPDATE")->fetchColumn();
    if ($state !== 'SCHEDULE_CONFIRMED') {
        $db->commit();
        return;
    }
    $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([2]);
    usleep(150000);
    $db->prepare("UPDATE amms_concurrency_flow02_request_probe SET status = 'CONVERTED_TO_MEETING' WHERE id = 1")->execute();
    $db->commit();
});
$pdo = $connect();
$finalizeStatus = (string) $pdo->query('SELECT status FROM amms_concurrency_flow02_request_probe WHERE id = 1')->fetchColumn();
$finalizeMeetings = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 2')->fetchColumn();
if ($finalizeStatus !== 'CONVERTED_TO_MEETING' || $finalizeMeetings !== 1 || count(array_filter($finalizeResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-02 concurrent finalization failed: ' . json_encode(['results' => $finalizeResults, 'status' => $finalizeStatus, 'meetings' => $finalizeMeetings]));
}

$pdo->exec("INSERT INTO amms_concurrency_flow03_target_probe (id, finance_status, hr_status, status, multilateral_transitions) VALUES (1, 'PENDING_REVIEW', 'PENDING_REVIEW', 'UNDER_MULTI_TARGET_REVIEW', 0)");
$pdo = null;
$targetApprovalResults = $runIndexedConcurrent(static function (int $worker) use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $row = $db->query('SELECT finance_status, hr_status, status, multilateral_transitions FROM amms_concurrency_flow03_target_probe WHERE id = 1 FOR UPDATE')->fetch();
    if ($worker === 0) {
        $db->exec("UPDATE amms_concurrency_flow03_target_probe SET finance_status = 'APPROVED' WHERE id = 1");
    } else {
        $db->exec("UPDATE amms_concurrency_flow03_target_probe SET hr_status = 'APPROVED' WHERE id = 1");
    }
    $fresh = $db->query('SELECT finance_status, hr_status, status, multilateral_transitions FROM amms_concurrency_flow03_target_probe WHERE id = 1')->fetch();
    if ($fresh['finance_status'] === 'APPROVED' && $fresh['hr_status'] === 'APPROVED' && $fresh['status'] !== 'MULTILATERAL_APPROVED') {
        $db->exec("UPDATE amms_concurrency_flow03_target_probe SET status = 'MULTILATERAL_APPROVED', multilateral_transitions = multilateral_transitions + 1 WHERE id = 1");
    }
    $db->commit();
});
$pdo = $connect();
$targetState = $pdo->query('SELECT finance_status, hr_status, status, multilateral_transitions FROM amms_concurrency_flow03_target_probe WHERE id = 1')->fetch();
if ($targetState['finance_status'] !== 'APPROVED' || $targetState['hr_status'] !== 'APPROVED' || $targetState['status'] !== 'MULTILATERAL_APPROVED' || (int) $targetState['multilateral_transitions'] !== 1 || count(array_filter($targetApprovalResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-03 simultaneous target approval failed: ' . json_encode(['results' => $targetApprovalResults, 'state' => $targetState]));
}

$pdo = null;
$acceptResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO amms_concurrency_flow03_acceptance_probe (proposal_id, unit_id, accepted_by) VALUES (?,?,?)')->execute([1, 2, 10]);
        usleep(150000);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() !== '23000') throw $e;
    }
});
$pdo = $connect();
$acceptCount = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow03_acceptance_probe WHERE proposal_id = 1 AND unit_id = 2')->fetchColumn();
if ($acceptCount !== 1 || count(array_filter($acceptResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-03 schedule acceptance uniqueness failed: ' . json_encode(['results' => $acceptResults, 'count' => $acceptCount]));
}

$pdo->exec("INSERT INTO amms_concurrency_flow03_request_probe (id, status) VALUES (3, 'SCHEDULE_CONFIRMED')");
$pdo = null;
$flow03FinalizeResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $state = $db->query("SELECT status FROM amms_concurrency_flow03_request_probe WHERE id = 3 FOR UPDATE")->fetchColumn();
    if ($state === 'SCHEDULE_CONFIRMED') {
        $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([3]);
        foreach ([1, 2, 3] as $unitId) {
            $db->prepare('INSERT INTO amms_concurrency_flow03_meeting_unit_probe (meeting_request_id, unit_id) VALUES (?, ?)')->execute([3, $unitId]);
        }
        $db->exec("UPDATE amms_concurrency_flow03_request_probe SET status = 'CONVERTED_TO_MEETING' WHERE id = 3");
    }
    $db->commit();
});
$pdo = $connect();
$flow03Status = (string) $pdo->query('SELECT status FROM amms_concurrency_flow03_request_probe WHERE id = 3')->fetchColumn();
$flow03Meetings = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 3')->fetchColumn();
$flow03Units = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow03_meeting_unit_probe WHERE meeting_request_id = 3')->fetchColumn();
if ($flow03Status !== 'CONVERTED_TO_MEETING' || $flow03Meetings !== 1 || $flow03Units !== 3 || count(array_filter($flow03FinalizeResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-03 parallel finalization failed: ' . json_encode(['results' => $flow03FinalizeResults, 'status' => $flow03Status, 'meetings' => $flow03Meetings, 'units' => $flow03Units]));
}

$pdo->exec("INSERT INTO amms_concurrency_flow04_request_probe (id, status, office_status, authority_status, requester_ack, requester_confirm, authority_confirm, finalize_transitions) VALUES (4, 'UNDER_AUTHORITY_OFFICE_REVIEW', 'UNDER_REVIEW', 'PENDING', 0, 0, 0, 0)");
$pdo = null;
$flow04ForwardResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $row = $db->query("SELECT status, office_status, authority_status FROM amms_concurrency_flow04_request_probe WHERE id = 4 FOR UPDATE")->fetch();
    if ($row['status'] === 'UNDER_AUTHORITY_OFFICE_REVIEW' && $row['office_status'] === 'UNDER_REVIEW') {
        $db->exec("UPDATE amms_concurrency_flow04_request_probe SET status = 'PENDING_HIGH_AUTHORITY_DECISION', office_status = 'COMPLETED', authority_status = 'PENDING' WHERE id = 4");
    }
    $db->commit();
});
$pdo = $connect();
$flow04DecisionState = $pdo->query('SELECT status, office_status, authority_status FROM amms_concurrency_flow04_request_probe WHERE id = 4')->fetch();
if ($flow04DecisionState['status'] !== 'PENDING_HIGH_AUTHORITY_DECISION' || $flow04DecisionState['office_status'] !== 'COMPLETED' || count(array_filter($flow04ForwardResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-04 concurrent office forward failed: ' . json_encode(['results' => $flow04ForwardResults, 'state' => $flow04DecisionState]));
}

$pdo->exec('DELETE FROM amms_concurrency_flow04_ack_probe');
$pdo = null;
$flow04AckResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO amms_concurrency_flow04_ack_probe (proposal_id, requester_unit_id) VALUES (?, ?)')->execute([44, 1]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e->getCode() !== '23000') throw $e;
    }
});
$pdo = $connect();
$flow04AckCount = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow04_ack_probe WHERE proposal_id = 44 AND requester_unit_id = 1')->fetchColumn();
if ($flow04AckCount !== 1 || count(array_filter($flow04AckResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-04 acknowledgement uniqueness failed: ' . json_encode(['results' => $flow04AckResults, 'count' => $flow04AckCount]));
}

$pdo->exec("UPDATE amms_concurrency_flow04_request_probe SET status = 'SCHEDULE_CONFIRMED', requester_ack = 1, requester_confirm = 1, authority_confirm = 1, finalize_transitions = 0 WHERE id = 4");
$pdo->exec('DELETE FROM amms_concurrency_flow04_authority_context_probe');
$pdo = null;
$flow04FinalizeResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $state = $db->query("SELECT status, requester_ack, requester_confirm, authority_confirm FROM amms_concurrency_flow04_request_probe WHERE id = 4 FOR UPDATE")->fetch();
    if ($state['status'] === 'SCHEDULE_CONFIRMED' && (int) $state['requester_ack'] === 1 && (int) $state['requester_confirm'] === 1 && (int) $state['authority_confirm'] === 1) {
        $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([4]);
        $db->prepare('INSERT INTO amms_concurrency_flow04_authority_context_probe (meeting_request_id, authority_employee_id, authority_type) VALUES (?, ?, ?)')->execute([4, 30, 'DIRECTOR_GENERAL']);
        $db->exec("UPDATE amms_concurrency_flow04_request_probe SET status = 'CONVERTED_TO_MEETING', finalize_transitions = finalize_transitions + 1 WHERE id = 4");
    }
    $db->commit();
});
$pdo = $connect();
$flow04Status = (string) $pdo->query('SELECT status FROM amms_concurrency_flow04_request_probe WHERE id = 4')->fetchColumn();
$flow04Meetings = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 4')->fetchColumn();
$flow04Contexts = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow04_authority_context_probe WHERE meeting_request_id = 4')->fetchColumn();
$flow04Transitions = (int) $pdo->query('SELECT finalize_transitions FROM amms_concurrency_flow04_request_probe WHERE id = 4')->fetchColumn();
if ($flow04Status !== 'CONVERTED_TO_MEETING' || $flow04Meetings !== 1 || $flow04Contexts !== 1 || $flow04Transitions !== 1 || count(array_filter($flow04FinalizeResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-04 parallel finalization failed: ' . json_encode(['results' => $flow04FinalizeResults, 'status' => $flow04Status, 'meetings' => $flow04Meetings, 'contexts' => $flow04Contexts, 'transitions' => $flow04Transitions]));
}

$pdo->exec("INSERT INTO amms_concurrency_flow05_request_probe (id, status, office_status, authority_status, finalize_transitions) VALUES (5, 'UNDER_AUTHORITY_OFFICE_REVIEW', 'UNDER_REVIEW', 'PENDING', 0)");
$pdo = null;
$flow05ForwardResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect(); $db->beginTransaction();
    $row = $db->query("SELECT status, office_status FROM amms_concurrency_flow05_request_probe WHERE id = 5 FOR UPDATE")->fetch();
    if ($row['status'] === 'UNDER_AUTHORITY_OFFICE_REVIEW' && $row['office_status'] === 'UNDER_REVIEW') $db->exec("UPDATE amms_concurrency_flow05_request_probe SET status = 'PENDING_HIGH_AUTHORITY_DECISION', office_status = 'COMPLETED' WHERE id = 5");
    $db->commit();
});
$pdo = $connect(); $flow05ForwardState = $pdo->query('SELECT status, office_status FROM amms_concurrency_flow05_request_probe WHERE id = 5')->fetch();
if ($flow05ForwardState['status'] !== 'PENDING_HIGH_AUTHORITY_DECISION' || $flow05ForwardState['office_status'] !== 'COMPLETED' || count(array_filter($flow05ForwardResults, fn ($r) => $r['status'] !== 0)) > 0) throw new RuntimeException('FLOW-05 office forward serialization failed: '.json_encode(['results'=>$flow05ForwardResults,'state'=>$flow05ForwardState]));
$flow05AckResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect(); $db->beginTransaction();
    try { $db->prepare('INSERT INTO amms_concurrency_flow05_ack_probe (proposal_id, unit_id, acknowledged_by) VALUES (?,?,?)')->execute([55, 1, 101]); $db->commit(); }
    catch (PDOException $e) { if ($db->inTransaction()) $db->rollBack(); if ($e->getCode() !== '23000') throw $e; }
});
$pdo = $connect(); $flow05AckCount = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow05_ack_probe WHERE proposal_id = 55 AND unit_id = 1')->fetchColumn();
if ($flow05AckCount !== 1 || count(array_filter($flow05AckResults, fn ($r) => $r['status'] !== 0)) > 0) throw new RuntimeException('FLOW-05 acknowledgement uniqueness failed: '.json_encode(['results'=>$flow05AckResults,'count'=>$flow05AckCount]));
$pdo->exec("UPDATE amms_concurrency_flow05_request_probe SET status = 'SCHEDULE_CONFIRMED', office_status = 'COMPLETED', authority_status = 'APPROVED', finalize_transitions = 0 WHERE id = 5");
$pdo->exec('DELETE FROM amms_concurrency_flow05_context_probe'); $pdo->exec('DELETE FROM amms_concurrency_flow05_meeting_unit_probe'); $pdo = null;
$flow05FinalizeResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect(); $db->beginTransaction();
    $state = $db->query("SELECT status, office_status, authority_status FROM amms_concurrency_flow05_request_probe WHERE id = 5 FOR UPDATE")->fetch();
    if ($state['status'] === 'SCHEDULE_CONFIRMED' && $state['office_status'] === 'COMPLETED' && $state['authority_status'] === 'APPROVED') {
        try {
            $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([5]);
            foreach ([1,2,3] as $unitId) $db->prepare('INSERT INTO amms_concurrency_flow05_meeting_unit_probe (meeting_request_id, unit_id) VALUES (?,?)')->execute([5,$unitId]);
            $db->prepare('INSERT INTO amms_concurrency_flow05_context_probe (meeting_request_id, authority_employee_id, authority_type) VALUES (?,?,?)')->execute([5,30,'DIRECTOR_GENERAL']);
            $db->exec("UPDATE amms_concurrency_flow05_request_probe SET status = 'CONVERTED_TO_MEETING', finalize_transitions = finalize_transitions + 1 WHERE id = 5");
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
    }
    $db->commit();
});
$pdo = $connect(); $flow05Status = (string) $pdo->query('SELECT status FROM amms_concurrency_flow05_request_probe WHERE id = 5')->fetchColumn(); $flow05Meetings = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 5')->fetchColumn(); $flow05Contexts = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow05_context_probe WHERE meeting_request_id = 5')->fetchColumn(); $flow05Units = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow05_meeting_unit_probe WHERE meeting_request_id = 5')->fetchColumn(); $flow05Transitions = (int) $pdo->query('SELECT finalize_transitions FROM amms_concurrency_flow05_request_probe WHERE id = 5')->fetchColumn();
if ($flow05Status !== 'CONVERTED_TO_MEETING' || $flow05Meetings !== 1 || $flow05Contexts !== 1 || $flow05Units !== 3 || $flow05Transitions !== 1 || count(array_filter($flow05FinalizeResults, fn ($r) => $r['status'] !== 0)) > 0) throw new RuntimeException('FLOW-05 parallel finalization failed: '.json_encode(['results'=>$flow05FinalizeResults,'status'=>$flow05Status,'meetings'=>$flow05Meetings,'contexts'=>$flow05Contexts,'units'=>$flow05Units,'transitions'=>$flow05Transitions]));

$pdo->exec("INSERT INTO amms_concurrency_flow06_request_probe (id, status, requesting_unit_id, participating_units, finalize_transitions) VALUES (6, 'SCHEDULE_CONFIRMED', NULL, 3, 0)");
$pdo->exec('DELETE FROM amms_concurrency_flow06_context_probe WHERE meeting_request_id = 6');
$pdo->exec('DELETE FROM amms_concurrency_flow06_meeting_unit_probe WHERE meeting_request_id = 6');
$pdo = null;
$flow06FinalizeResults = $runConcurrent(static function () use ($connect): void {
    $db = $connect();
    $db->beginTransaction();
    $state = $db->query("SELECT status, requesting_unit_id, participating_units FROM amms_concurrency_flow06_request_probe WHERE id = 6 FOR UPDATE")->fetch();
    if ($state['status'] === 'SCHEDULE_CONFIRMED' && $state['requesting_unit_id'] === null && (int) $state['participating_units'] === 3) {
        $db->prepare('INSERT INTO amms_concurrency_meeting_probe (meeting_request_id) VALUES (?)')->execute([6]);
        foreach ([1, 2, 3] as $unitId) $db->prepare('INSERT INTO amms_concurrency_flow06_meeting_unit_probe (meeting_request_id, unit_id, role) VALUES (?, ?, ?)')->execute([6, $unitId, 'PARTICIPATING']);
        $db->prepare('INSERT INTO amms_concurrency_flow06_context_probe (meeting_request_id, authority_employee_id, authority_type) VALUES (?, ?, ?)')->execute([6, 9, 'DIRECTOR_GENERAL']);
        $db->exec("UPDATE amms_concurrency_flow06_request_probe SET status = 'CONVERTED_TO_MEETING', finalize_transitions = finalize_transitions + 1 WHERE id = 6");
    }
    $db->commit();
});
$pdo = $connect();
$flow06State = $pdo->query('SELECT status, requesting_unit_id, participating_units, finalize_transitions FROM amms_concurrency_flow06_request_probe WHERE id = 6')->fetch();
$flow06Meetings = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_meeting_probe WHERE meeting_request_id = 6')->fetchColumn();
$flow06Units = (int) $pdo->query("SELECT COUNT(*) FROM amms_concurrency_flow06_meeting_unit_probe WHERE meeting_request_id = 6 AND role = 'PARTICIPATING'")->fetchColumn();
$flow06RequestingUnits = (int) $pdo->query("SELECT COUNT(*) FROM amms_concurrency_flow06_meeting_unit_probe WHERE meeting_request_id = 6 AND role = 'REQUESTING'")->fetchColumn();
$flow06Contexts = (int) $pdo->query('SELECT COUNT(*) FROM amms_concurrency_flow06_context_probe WHERE meeting_request_id = 6')->fetchColumn();
$flow06AuthorityEmployee = (int) $pdo->query('SELECT authority_employee_id FROM amms_concurrency_flow06_context_probe WHERE meeting_request_id = 6')->fetchColumn();
if ($flow06State['status'] !== 'CONVERTED_TO_MEETING' || $flow06State['requesting_unit_id'] !== null || (int) $flow06State['participating_units'] !== 3 || (int) $flow06State['finalize_transitions'] !== 1 || $flow06Meetings !== 1 || $flow06Units !== 3 || $flow06RequestingUnits !== 0 || $flow06Contexts !== 1 || $flow06AuthorityEmployee !== 9 || count(array_filter($flow06FinalizeResults, fn ($r) => $r['status'] !== 0)) > 0) {
    throw new RuntimeException('FLOW-06 parallel finalization failed: ' . json_encode(['results' => $flow06FinalizeResults, 'state' => $flow06State, 'meetings' => $flow06Meetings, 'participating_units' => $flow06Units, 'requesting_units' => $flow06RequestingUnits, 'contexts' => $flow06Contexts, 'authority_employee' => $flow06AuthorityEmployee]));
}

echo "MariaDB concurrency checks passed: row lock final value={$finalValue}; one-meeting uniqueness held; FLOW-02 and FLOW-03 parallel finalization each produced one meeting; FLOW-03 target approvals produced one multilateral transition; FLOW-03 schedule acceptance uniqueness held; FLOW-04 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with one authority context; FLOW-05 office forward serialized, acknowledgement uniqueness held, and parallel finalization produced one meeting with three formal units and one authority context; FLOW-06 parallel finalization produced one meeting with three PARTICIPATING units, zero REQUESTING units, one authority context, and High Authority employee 9.\n";
