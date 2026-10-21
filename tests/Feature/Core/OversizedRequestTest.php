<?php

namespace Tests\Feature\Core;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OversizedRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_body_over_post_max_size_returns_413_with_a_generic_spanish_message(): void
    {
        $admin = User::factory()->administrator()->create();
        $tooLarge = (string) (ini_parse_quantity((string) ini_get('post_max_size')) + 1);
        $clients = [
            'json' => ['Accept' => 'application/json'],
            'inertia' => ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, application/xhtml+xml'],
        ];

        foreach ($clients as $headers) {
            $this->actingAs($admin)
                ->call('POST', route('admin.products.store'), server: $this->transformHeadersToServerVars($headers) + ['CONTENT_LENGTH' => $tooLarge])
                ->assertStatus(413)
                ->assertExactJson(['message' => 'El archivo es demasiado grande.']);
        }

        $this->assertDatabaseCount('products', 0);
    }
}
