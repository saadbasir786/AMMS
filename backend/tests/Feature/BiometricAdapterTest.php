<?php

namespace Tests\Feature;

use App\Biometrics\FakeFingerprintAdapter;
use PHPUnit\Framework\TestCase;

class BiometricAdapterTest extends TestCase
{
    public function test_fake_adapter_is_explicitly_simulated_and_matches_only_candidate_fixture(): void
    {
        $adapter = new FakeFingerprintAdapter();
        $this->assertTrue($adapter->isAvailable());
        $this->assertTrue($adapter->getCapabilities()['simulated']);
        $result = $adapter->identify('EMPLOYEE_12', [
            ['employee_id' => 12, 'template_uuid' => '11111111-1111-1111-1111-111111111111', 'finger_position' => 'RIGHT_INDEX', 'fake_probe_fixture' => 'EMPLOYEE_12'],
        ]);
        $this->assertTrue($result->matched);
        $this->assertSame(12, $result->employeeId);
        $this->assertSame('MATCH', $result->outcome);
    }

    public function test_fake_adapter_returns_safe_nonmatch_and_quality_outcomes(): void
    {
        $adapter = new FakeFingerprintAdapter();
        $this->assertFalse($adapter->identify('UNKNOWN', [])->matched);
        $this->assertSame('NO_MATCH', $adapter->identify('UNKNOWN', [])->outcome);
        $this->assertSame('BELOW_THRESHOLD', $adapter->identify('LOW_SCORE', [])->outcome);
        $this->assertFalse($adapter->getQuality('LOW_CAPTURE')['quality_accepted']);
        $this->assertTrue($adapter->getQuality('GOOD_CAPTURE')['quality_accepted']);
    }
}
