<?php

namespace Tests\Unit;

use App\Models\Staff;
use Tests\TestCase;

class StaffQrDataUriTest extends TestCase
{
    public function test_qr_data_uri_is_svg_data_uri(): void
    {
        $staff = new Staff;
        $staff->qr_identifier = 'staff-qr-self-check';

        $uri = $staff->qrDataUri();

        $this->assertStringStartsWith('data:image/svg+xml', $uri);
        $this->assertNotSame('', $uri);
    }
}
