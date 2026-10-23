<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    public function test_vite_asset_urls_use_https_behind_trusted_https_proxy(): void
    {
        // Avoid the local Vite HMR hot file so production /build asset URLs are used.
        Vite::useHotFile(storage_path('framework/testing/vite-absent.hot'));

        $response = $this->call('GET', 'http://lrms.test/entry/login', server: [
            'HTTP_HOST' => 'lrms.test',
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        ]);

        $response->assertOk();
        $response->assertSee('https://lrms.test/build/assets/', false);
        $this->assertStringNotContainsString(
            'http://lrms.test/build/assets/',
            $response->getContent(),
        );
    }

    public function test_plain_http_requests_keep_http_asset_urls(): void
    {
        Vite::useHotFile(storage_path('framework/testing/vite-absent.hot'));

        $response = $this->call('GET', 'http://lrms.test/entry/login', server: [
            'HTTP_HOST' => 'lrms.test',
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertOk();
        $response->assertSee('http://lrms.test/build/assets/', false);
    }
}
