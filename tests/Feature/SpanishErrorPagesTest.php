<?php

namespace Tests\Feature;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SpanishErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_and_missing_pages_explain_the_error_in_spanish(): void
    {
        $waiter = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($waiter)->get('/admin/inventory')->assertForbidden()
            ->assertSee('Esta acción no está autorizada.')
            ->assertDontSee('This action is unauthorized');
        $this->get('/pagina-que-no-existe')->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertDontSee('Not Found');
    }

    public function test_every_default_error_page_title_is_translated(): void
    {
        $this->assertSame('es', app()->getLocale());
        foreach (['Unauthorized', 'Payment Required', 'Forbidden', 'Not Found', 'Page Expired', 'Too Many Requests', 'Server Error', 'Service Unavailable', 'This action is unauthorized.'] as $english) {
            $this->assertNotSame($english, __($english), $english.' is shown in English.');
        }
    }
}
