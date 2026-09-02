<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\WalletData;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class UpdateWallet
{
    public function execute(Wallet $wallet, WalletData $data, int $adminId): Wallet
    {
        return DB::transaction(function () use ($wallet, $data, $adminId): Wallet {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($locked->is_order_payment_default && ! $data->isOrderPaymentDefault) {
                throw ValidationException::withMessages([
                    'is_order_payment_default' => 'Asigná otra billetera para cobros de pedidos en lugar de dejar el flujo sin destino.',
                ]);
            }
            if ($data->isOrderPaymentDefault) {
                DB::statement('LOCK TABLE wallets IN SHARE ROW EXCLUSIVE MODE');
                Wallet::query()->where('is_order_payment_default', true)->whereKeyNot($locked->id)->update(['is_order_payment_default' => false]);
            }
            $locked->update([
                'name' => $data->name,
                'sort_order' => $data->sortOrder,
                'is_order_payment_default' => $data->isOrderPaymentDefault,
            ]);
            Log::info('[Management] wallet.updated', ['module' => 'Management', 'wallet_id' => $locked->id, 'admin_id' => $adminId]);

            return $locked;
        });
    }
}
