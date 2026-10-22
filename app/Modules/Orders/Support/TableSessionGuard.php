<?php

namespace App\Modules\Orders\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\TableSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Table service authorization: waiter actions need POS access and, for a waiter, their own table;
 * owner, manager and cashier act on any table, and only they print bills, charge or cancel.
 */
final class TableSessionGuard
{
    public function lockOpen(TableSession $session, User $actor, bool $cashierOnly): TableSession
    {
        if (! $actor->canUsePos() || ($cashierOnly && ! $actor->canOperateCashier())) {
            throw new AuthorizationException('No tenés permiso para esta acción de la mesa. Pedile a caja o a un administrador que la haga.');
        }
        $locked = TableSession::query()->lockForUpdate()->findOrFail($session->id);
        if ($locked->waiter_id !== $actor->id && ! $actor->canOperateCashier()) {
            throw new AuthorizationException('Solo el mozo de la mesa o caja pueden operar esta mesa. Pedile al mozo asignado o a caja que lo haga.');
        }
        if ($locked->status !== TableSessionStatus::Open) {
            throw ValidationException::withMessages(['table' => 'Esta mesa ya fue cobrada o cerrada. Actualizá la pantalla.']);
        }

        return $locked;
    }
}
