<?php

namespace App\Modules\PublicSite\Http\Controllers;

use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Marketing\Queries\FeaturedProductResolver;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PublicHomeController
{
    public function __invoke(
        FeaturedProductResolver $resolver,
        BuildFeaturedProductPublicView $publicView,
    ): Response {
        if (! config('public_site.open')) {
            $phone = preg_replace('/\D+/', '', OrderSetting::string(OrderSettingKey::WhatsappOrderPhone));

            return Inertia::render('public/coming-soon', [
                'whatsappUrl' => $phone === '' ? null : 'https://wa.me/'.$phone,
            ]);
        }

        $featuredProduct = $resolver->publiclyVisible();

        return Inertia::render('public/home', [
            'featuredProduct' => $featuredProduct === null ? null : $publicView->execute($featuredProduct),
        ]);
    }
}
