<?php

namespace App\Modules\PublicSite\Http\Controllers;

use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Marketing\Queries\FeaturedProductResolver;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PublicHomeController
{
    public function __invoke(
        FeaturedProductResolver $resolver,
        BuildFeaturedProductPublicView $publicView,
    ): Response {
        $featuredProduct = $resolver->publiclyVisible();

        return Inertia::render('public/home', [
            'featuredProduct' => $featuredProduct === null ? null : $publicView->execute($featuredProduct),
        ]);
    }
}
