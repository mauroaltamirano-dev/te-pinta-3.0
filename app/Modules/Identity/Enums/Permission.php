<?php

namespace App\Modules\Identity\Enums;

/**
 * Code-defined permissions. Route middleware uses them as `can:<value>`;
 * {@see Role::permissions()} is the only place that grants them.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case OrdersView = 'orders.view';
    /** Create, edit, status and payment of orders. */
    case OrdersManage = 'orders.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case CatalogView = 'catalog.view';
    /** Products, categories, tags and ordering settings. */
    case CatalogManage = 'catalog.manage';
    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    /** Management overview, audit and purchases. */
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    /** Featured products and Instagram. */
    case MarketingManage = 'marketing.manage';
    /** Restaurant floor layout. */
    case FloorManage = 'floor.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
}
