<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class SpanishLocalizationTest extends TestCase
{
    public function test_first_party_validation_and_authentication_messages_are_available_in_spanish(): void
    {
        $validator = Validator::make([], ['name' => ['required']]);

        $this->assertSame('es', app()->getLocale());
        $this->assertSame('El campo name es obligatorio.', $validator->errors()->first('name'));
        $this->assertSame('Las credenciales ingresadas no son correctas.', __('auth.failed'));
        $this->assertSame('Te enviamos el enlace para restablecer tu contraseña.', __('passwords.sent'));
    }
}
