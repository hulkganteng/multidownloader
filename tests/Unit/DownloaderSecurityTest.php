<?php

namespace Tests\Unit;

use App\Downloaders\DirectMediaDownloader;
use App\Services\PlatformDetector;
use RuntimeException;
use Tests\TestCase;

class DownloaderSecurityTest extends TestCase
{
    public function test_private_ipv4_is_rejected_without_cidr_arithmetic_crash(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('private or reserved networks');

        (new DirectMediaDownloader)->analyze('http://127.0.0.1/media.mp4');
    }

    public function test_platform_detector_does_not_accept_spoofed_tiktok_domain(): void
    {
        $detector = new PlatformDetector;

        $this->assertSame('direct', $detector->detect('https://not-tiktok.com/video'));
        $this->assertSame('tiktok', $detector->detect('https://www.tiktok.com/@user/video/123'));
        $this->assertSame('instagram', $detector->detect('https://www.instagram.com/reel/Cx12345/'));
        $this->assertSame('facebook', $detector->detect('https://www.facebook.com/watch/?v=123456'));
        $this->assertSame('twitter', $detector->detect('https://x.com/user/status/123456789'));
    }
}
