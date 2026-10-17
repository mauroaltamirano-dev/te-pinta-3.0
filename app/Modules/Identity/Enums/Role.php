<?php

namespace App\Modules\Identity\Enums;

use App\Modules\Identity\Enums\Permission as P;

/**
 * Code-defined staff roles of a restaurant membership. There is no runtime
 * role editor: this enum is the single role → permission mapping.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dueño',
            self::Manager => 'Encargado',
            self::Cashier => 'Cajero',
            self::Waiter => 'Mozo',
            self::Kitchen => 'Cocina',
            self::Accountant => 'Contador',
        };
    }

    /**
     * @return list<P> in declaration order of {@see P}
     */
    public function permissions(): array
    {
        $granted = match ($this) {
            self::Owner => P::cases(),
            self::Manager => [
                P::DashboardView, P::OrdersView, P::OrdersManage, P::CustomersView, P::CustomersManage,
                P::CatalogView, P::CatalogManage, P::InventoryView, P::InventoryManage, P::FinanceView,
                P::FinanceManage, P::MarketingManage, P::FloorManage,
            ],
            self::Cashier => [P::DashboardView, P::OrdersView, P::OrdersManage, P::CustomersView, P::CustomersManage, P::CatalogView],
            self::Waiter => [P::OrdersView, P::OrdersManage, P::CustomersView, P::CatalogView],
            self::Kitchen => [P::OrdersView, P::CatalogView, P::InventoryView],
            self::Accountant => [P::DashboardView, P::OrdersView, P::CustomersView, P::CatalogView, P::InventoryView, P::FinanceView],
        };

        return array_values(array_filter(P::cases(), fn (P $permission) => in_array($permission, $granted, true)));
    }

    public function grants(P $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Roles that may confirm a payment while creating a POS order: the former
     * `operate-cashier` capability (administrators and cashiers).
     */
    public function operatesCashier(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Cashier], true);
    }
}
