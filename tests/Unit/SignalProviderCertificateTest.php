<?php

namespace Tests\Unit;

use App\Models\SignalProviderCertificate;
use Tests\TestCase;

class SignalProviderCertificateTest extends TestCase
{
    public function test_certificate_eligibility_includes_trading_related_roles(): void
    {
        $this->assertSame(
            [1, 2, 201, 202, 501, 502, 750, 760, 770],
            SignalProviderCertificate::eligibleRoleIds()
        );
    }
}
