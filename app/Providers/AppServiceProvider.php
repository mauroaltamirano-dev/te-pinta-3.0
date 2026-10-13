<?php

namespace App\Providers;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Policies\CustomerPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Listeners\RecordDeliveredOrderConsumption;
use App\Modules\Inventory\Listeners\ReverseCancelledOrderConsumption;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Policies\InventoryCategoryPolicy;
use App\Modules\Inventory\Policies\InventoryItemPolicy;
use App\Modules\Inventory\Policies\PurchasePresentationPolicy;
use App\Modules\Management\Listeners\RecordConfirmedOrderPayment;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Policies\DistributionRulePolicy;
use App\Modules\Management\Policies\FinancialTransactionPolicy;
use App\Modules\Management\Policies\ManagementAttachmentPolicy;
use App\Modules\Management\Policies\ManagementDistributionPolicy;
use App\Modules\Management\Policies\ManagementOutflowPolicy;
use App\Modules\Management\Policies\ManagementTransferPolicy;
use App\Modules\Management\Policies\PurchasePolicy;
use App\Modules\Management\Policies\WalletPolicy;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Policies\FeaturedProductPolicy;
use App\Modules\Orders\Events\OrderDelivered;
use App\Modules\Orders\Events\OrderDeliveryCancelled;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Policies\CategoryPolicy;
use App\Modules\Products\Policies\ProductPolicy;
use App\Modules\Products\Pricing\DatabaseProductPriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProductPriceProvider::class, DatabaseProductPriceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");
        $url = config("database.connections.{$connection}.url");

        TestingDatabaseGuard::ensureSafe(
            $this->app->environment(),
            is_string($database) ? $database : null,
            is_string($url) ? $url : null,
        );

        $this->configureDefaults();

        Event::listen(OrderPaymentConfirmed::class, RecordConfirmedOrderPayment::class);
        Event::listen(OrderDelivered::class, RecordDeliveredOrderConsumption::class);
        Event::listen(OrderDeliveryCancelled::class, ReverseCancelledOrderConsumption::class);

        RateLimiter::for('instagram-automation', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        Gate::define('administrator', fn (User $user): bool => $user->isAdministrator());
        Gate::define('use-pos', fn (User $user): bool => $user->canUsePos());
        Gate::define('operate-cashier', fn (User $user): bool => $user->canOperateCashier());

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(FeaturedProduct::class, FeaturedProductPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(FinancialTransaction::class, FinancialTransactionPolicy::class);
        Gate::policy(DistributionRule::class, DistributionRulePolicy::class);
        Gate::policy(ManagementDistribution::class, ManagementDistributionPolicy::class);
        Gate::policy(ManagementAttachment::class, ManagementAttachmentPolicy::class);
        Gate::policy(ManagementOutflow::class, ManagementOutflowPolicy::class);
        Gate::policy(ManagementTransfer::class, ManagementTransferPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(InventoryCategory::class, InventoryCategoryPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(PurchasePresentation::class, PurchasePresentationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}
