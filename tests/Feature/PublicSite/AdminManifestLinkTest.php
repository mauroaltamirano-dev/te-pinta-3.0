<?php

namespace Tests\Feature\PublicSite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public gateway denies the admin PWA manifest, so storefront pages must not link it. */
final class AdminManifestLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_pages_do_not_link_the_admin_manifest(): void
    {
        foreach (['/', '/menu', '/cart'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('rel="manifest"', false);
        }
    }

    public function test_admin_entry_pages_keep_the_manifest(): void
    {
        $this->get('/login')->assertOk()->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
    }
}
