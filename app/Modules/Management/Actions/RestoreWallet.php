<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RestoreWallet
{
    public function execute(Wallet $wallet, int $adminId): Wallet
    {
        return DB::transaction(function () use ($wallet, $adminId): Wallet {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($locked->archived_at !== null) {
                $locked->update(['archived_at' => null]);
                Log::info('[Management] wallet.restored', ['module' => 'Management', 'wallet_id' => $locked->id, 'admin_id' => $adminId]);
            }

            return $locked;
        });
    }
}
