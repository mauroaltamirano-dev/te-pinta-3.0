<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Marketing\Actions\CreateFeaturedProduct;
use App\Modules\Marketing\Actions\DisableFeaturedProduct;
use App\Modules\Marketing\Actions\UpdateFeaturedProduct;
use App\Modules\Marketing\Data\FeaturedProductData;
use App\Modules\Marketing\Enums\FeaturedProductStatus;
use App\Modules\Marketing\Http\Requests\StoreFeaturedProductRequest;
use App\Modules\Marketing\Http\Requests\UpdateFeaturedProductRequest;
use App\Modules\Marketing\Http\Resources\AdminFeaturedProductResource;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Marketing\Queries\FeaturedProductResolver;
use App\Modules\Marketing\Queries\SelectableFeaturedProducts;
use App\Modules\Marketing\Support\MarketingClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class MarketingManagementController
{
    public function __construct(
        private FeaturedProductResolver $resolver,
        private SelectableFeaturedProducts $selectableProducts,
        private BuildFeaturedProductPublicView $publicView,
        private MarketingClock $clock,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FeaturedProduct::class);
        $now = $this->clock->now();
        $records = $this->resolver->withAdministrativeProduct()
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
        $current = $records->first(fn (FeaturedProduct $item): bool => $item->statusAt($now) === FeaturedProductStatus::Active);
        $upcoming = $records->filter(fn (FeaturedProduct $item): bool => $item->statusAt($now) === FeaturedProductStatus::Scheduled);
        $history = $records
            ->reject(fn (FeaturedProduct $item): bool => $item->statusAt($now) === FeaturedProductStatus::Active
                || $item->statusAt($now) === FeaturedProductStatus::Scheduled)
            ->sortByDesc(fn (FeaturedProduct $item): int => $item->starts_at->getTimestamp())
            ->values();

        return Inertia::render('admin/marketing/index', [
            'current' => $current === null ? null : $this->resource($current, $request),
            'upcoming' => $this->resources($upcoming, $request),
            'history' => $this->resources($history, $request),
            'selectable_products' => $this->selectableProducts->query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($product): array => [
                    'id' => $product->id,
                    'product' => $this->publicView->product($product),
                ])->all(),
            'form_defaults' => [
                'starts_at' => $this->clock->formatLocal($now),
                'ends_at' => null,
                'is_enabled' => true,
            ],
            'business_timezone' => $this->clock->timezone(),
        ]);
    }

    public function store(
        StoreFeaturedProductRequest $request,
        CreateFeaturedProduct $create,
    ): RedirectResponse {
        $create->execute(
            FeaturedProductData::fromRequest($request, $this->clock),
            (int) $request->user()->getAuthIdentifier(),
        );

        return back()->with('success', 'Recomendación creada.');
    }

    public function update(
        UpdateFeaturedProductRequest $request,
        FeaturedProduct $featuredProduct,
        UpdateFeaturedProduct $update,
    ): RedirectResponse {
        $update->execute(
            $featuredProduct,
            FeaturedProductData::fromRequest($request, $this->clock),
            (int) $request->user()->getAuthIdentifier(),
        );

        return back()->with('success', 'Recomendación actualizada.');
    }

    public function disable(
        Request $request,
        FeaturedProduct $featuredProduct,
        DisableFeaturedProduct $disable,
    ): RedirectResponse {
        Gate::authorize('update', $featuredProduct);
        $disable->execute($featuredProduct, (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Recomendación desactivada.');
    }

    /** @return array<string, mixed> */
    private function resource(FeaturedProduct $featuredProduct, Request $request): array
    {
        return (new AdminFeaturedProductResource(
            $featuredProduct,
            $this->clock,
            $this->resolver,
            $this->publicView,
        ))->resolve($request);
    }

    /** @param Collection<int, FeaturedProduct> $records
     * @return array<int, array<string, mixed>>
     */
    private function resources(Collection $records, Request $request): array
    {
        return $records
            ->values()
            ->map(fn (FeaturedProduct $featuredProduct): array => $this->resource($featuredProduct, $request))
            ->all();
    }
}
