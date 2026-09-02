<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ArchiveWallet
{
    public function execute(Wallet $wallet, int $adminId): Wallet
    {
        return DB::transaction(function () use ($wallet, $adminId): Wallet {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($locked->is_order_payment_default) {
                throw ValidationException::withMessages([
                    'wallet' => 'Asigná otra billetera para cobros de pedidos antes de archivar esta billetera.',
                ]);
            }
            if ($locked->archived_at === null) {
                $locked->update(['archived_at' => now()]);
                Log::info('[Management] wallet.archived', ['module' => 'Management', 'wallet_id' => $locked->id, 'admin_id' => $adminId]);
            }

            return $locked;
        });
    }
}
