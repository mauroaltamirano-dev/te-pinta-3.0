<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PwaManifestTest extends TestCase
{
    public function test_manifest_defines_the_admin_mobile_entry_without_offline_behavior(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('/admin', $manifest['id']);
        $this->assertSame('/admin', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/admin/orders/create', $manifest['shortcuts'][0]['url']);
        $this->assertSame([
            '/icons/te-pinta-logo-192.png',
            '/icons/te-pinta-logo-512.png',
            '/icons/te-pinta-logo-maskable-512.png',
        ], array_column($manifest['icons'], 'src'));
        $this->assertSame(['192x192', '512x512', '512x512'], array_column($manifest['icons'], 'sizes'));
        $this->assertSame(['any', 'any', 'maskable'], array_column($manifest['icons'], 'purpose'));
        $this->assertSame('/icons/te-pinta-logo-192.png', $manifest['shortcuts'][0]['icons'][0]['src']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileDoesNotExist(public_path('service-worker.js'));
        $this->assertFileDoesNotExist(public_path('sw.js'));
    }

    public function test_application_layout_links_installation_metadata(): void
    {
        $layout = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="manifest" href="/manifest.webmanifest"', $layout);
        $this->assertStringContainsString('rel="icon" href="/icons/te-pinta-logo.svg" type="image/svg+xml" sizes="any"', $layout);
        $this->assertStringContainsString('rel="apple-touch-icon" href="/icons/te-pinta-logo-192.png"', $layout);
        $this->assertStringContainsString('name="theme-color" content="#1f1d2b"', $layout);
        $this->assertStringContainsString('name="apple-mobile-web-app-capable" content="yes"', $layout);

        $favicon = file_get_contents(public_path('icons/te-pinta-logo.svg'));

        $this->assertStringContainsString('<title id="te-pinta-title">Te Pinta</title>', $favicon);
        $this->assertStringContainsString('fill="#ea7c69"', $favicon);
        $this->assertStringNotContainsString('<image', $favicon);
        $this->assertStringNotContainsString('<script', $favicon);
    }
}
