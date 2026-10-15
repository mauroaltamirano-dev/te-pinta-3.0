<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Management\Actions\CreateDistributionRule;
use App\Modules\Management\Actions\CreateManagementDistribution;
use App\Modules\Management\Actions\CreateManagementOutflow;
use App\Modules\Management\Actions\CreateManagementTransfer;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Actions\CreatePurchase;
use App\Modules\Management\Actions\ReversePurchase;
use App\Modules\Management\Actions\StoreManagementAttachment;
use App\Modules\Management\Data\DistributionAllocationData;
use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Data\PurchaseData;
use App\Modules\Management\Data\PurchaseLineData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Enums\PurchasePriceMode;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class ManagementOverviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Management overview fixtures are testing-only.');
        }
        // The shared browser DB is serialized; keep this fixture immutable across projects.
        if (Wallet::where('name', 'AUX2A Caja')->exists()) {
            return;
        }
        DB::transaction(function (): void {
            $admin = User::where('email', 'playwright-admin@example.test')->sole();
            $day = app(OrderBusinessDate::class)->today()->startOfDay();
            $cash = Wallet::create(['name' => 'AUX2A Caja', 'sort_order' => 100]);
            $bank = Wallet::create(['name' => 'AUX2A Banco', 'sort_order' => 101]);
            $reserve = Wallet::create(['name' => 'AUX2A Reserva', 'sort_order' => 102]);
            $historic = Wallet::create(['name' => 'AUX2A Histórica', 'sort_order' => 103]);
            $manual = app(CreateManualTransaction::class);
            foreach ([[$cash, 100000], [$bank, 50000], [$historic, 5000]] as [$wallet, $amount]) {
                $manual->execute(new ManualTransactionData($wallet->id, FinancialTransactionType::Opening, $amount, $day->subDays(45), 'AUX2A opening'), $admin->id);
            }
            $historic->update(['archived_at' => $day]);
            for ($i = 1; $i <= 40; $i++) {
                $manual->execute(new ManualTransactionData($cash->id, FinancialTransactionType::ManualIncome, 2500, $day, 'AUX2A income '.$i), $admin->id);
            }
            $manual->execute(new ManualTransactionData($bank->id, FinancialTransactionType::ManualIncome, 50000, $day, 'AUX2A bank income'), $admin->id);
            $manual->execute(new ManualTransactionData($bank->id, FinancialTransactionType::ManualExpense, 3000, $day, 'AUX2A manual expense'), $admin->id);
            $manual->execute(new ManualTransactionData($cash->id, FinancialTransactionType::AdjustmentIncrease, 2500, $day, 'AUX2A financial adjustment'), $admin->id);
            foreach (['expense' => 12000, 'personal_withdrawal' => 10000, 'purchase' => 5000] as $type => $amount) {
                $category = ManagementOutflowCategory::where('type', $type)->firstOrFail();
                app(CreateManagementOutflow::class)->execute(new ManagementOutflowData(ManagementOutflowType::from($type), $category->id, $cash->id, 'AUX2A '.$type, null, $amount, $day), $admin->id);
            }
            app(CreateManagementTransfer::class)->execute(new ManagementTransferData($cash->id, $bank->id, 15000, $day, 'AUX2A transfer'), $admin->id);
            $rule = app(CreateDistributionRule::class)->execute(new DistributionRuleData('AUX2A distribution', [new DistributionAllocationData($bank->id, 6000, 0), new DistributionAllocationData($reserve->id, 4000, 1)]), $admin->id);
            app(CreateManagementDistribution::class)->execute(new ManagementDistributionData($cash->id, $rule->versions->sole()->id, 30000, $day, 'AUX2A allocation'), $admin->id);
            $unit = InventoryUnit::where('code', 'unit')->sole();
            $category = InventoryCategory::create(['name' => 'AUX2A purchase fixtures']);
            $items = [];
            foreach ([true, false] as $stockable) {
                $items[] = InventoryItem::create(['inventory_category_id' => $category->id, 'name' => $stockable ? 'AUX2A Cajas' : 'AUX2A Consumible sin stock', 'tracks_stock' => $stockable, 'unit_family' => 'count', 'base_unit_id' => $unit->id, 'default_input_unit_id' => $unit->id, 'quantity_precision' => 6, 'is_active' => true]);
            }
            $purchase = app(CreatePurchase::class)->execute(new PurchaseData((string) Str::uuid(), $cash->id, $day, 'AUX2A Proveedor', 'AUX2A multi-line purchase', 500, [new PurchaseLineData($items[0]->id, null, $unit->id, '3', PurchasePriceMode::Total, 6000), new PurchaseLineData($items[1]->id, null, $unit->id, '1', PurchasePriceMode::Total, 4000)]), $admin->id);
            app(StoreManagementAttachment::class)->execute($purchase->financialTransaction, UploadedFile::fake()->image('aux2a-receipt.png', 200, 100), $admin->id);
            $reversed = app(CreatePurchase::class)->execute(new PurchaseData((string) Str::uuid(), $cash->id, $day->subDay(), 'AUX2A Proveedor', 'AUX2A reversed purchase', 100, [new PurchaseLineData($items[0]->id, null, $unit->id, '1', PurchasePriceMode::Total, 1000)]), $admin->id);
            app(ReversePurchase::class)->execute($reversed, new ReversalData($day->subDay(), 'AUX2A exact reversal'), $admin->id);
        });
    }
}
