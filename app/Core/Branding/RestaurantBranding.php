<?php

namespace App\Core\Branding;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RestaurantBranding
{
    public function __construct(
        private readonly RestaurantContext $context,
        private readonly Request $request,
    ) {}

    public function palette(): AccentPalette
    {
        $key = 'branding.palette.'.$this->context->id();
        $cached = $this->request->attributes->get($key);

        if ($cached instanceof AccentPalette) {
            return $cached;
        }

        $accent = DB::table('restaurants')->where('id', $this->context->id())->value('accent_color');

        // Direct SQL can bypass the HTTP contrast validation; never render unsafe CSS.
        try {
            $palette = AccentPalette::fromHex(is_string($accent) ? $accent : AccentPalette::DEFAULT);
        } catch (InvalidAccentColor) {
            $palette = AccentPalette::fromHex(AccentPalette::DEFAULT);
        }

        $this->request->attributes->set($key, $palette);

        return $palette;
    }

    public function update(string $accent): void
    {
        $palette = AccentPalette::fromHex($accent);

        DB::table('restaurants')->where('id', $this->context->id())->update([
            'accent_color' => $palette->accent,
            'updated_at' => now(),
        ]);
        $this->request->attributes->remove('branding.palette.'.$this->context->id());
    }
}
