<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\WalletData;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateWallet
{
    public function execute(WalletData $data, int $adminId): Wallet
    {
        return DB::transaction(function () use ($data, $adminId): Wallet {
            if ($data->isOrderPaymentDefault) {
                DB::statement('LOCK TABLE wallets IN SHARE ROW EXCLUSIVE MODE');
                Wallet::query()->where('is_order_payment_default', true)->update(['is_order_payment_default' => false]);
            }
            $wallet = Wallet::query()->create([
                'name' => $data->name,
                'sort_order' => $data->sortOrder,
                'is_order_payment_default' => $data->isOrderPaymentDefault,
            ]);
            Log::info('[Management] wallet.created', ['module' => 'Management', 'wallet_id' => $wallet->id, 'admin_id' => $adminId]);

            return $wallet;
        });
    }
}
