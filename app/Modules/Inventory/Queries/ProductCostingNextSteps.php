<?php

namespace App\Modules\Inventory\Queries;

/** Turns what blocks a current cost estimate into concrete admin actions, without duplicates. */
final class ProductCostingNextSteps
{
    private const PURCHASE = '/admin/management/purchases/create';

    private const PRODUCTION = '/admin/inventory/production/create';

    private const ADJUSTMENTS = '/admin/inventory/adjustments';

    /**
     * @param  array<string, mixed>  $economics
     * @param  array<int, array{status:string,missing:list<array{name:string,kind:?string,fallback:?string}>}>  $recipes
     * @return list<array{text:string,href:string}>
     */
    public function build(array $economics, array $recipes): array
    {
        $steps = [];
        $add = function (string $text, string $href) use (&$steps): void {
            $steps[$text] ??= ['text' => $text, 'href' => $href];
        };
        $product = $economics['product_name'];
        $policy = $economics['costing_policy'];
        $policyHref = '/admin/inventory/costing-policies'.($policy === null ? '' : '?edit='.$policy['id']);
        foreach ($economics['presentations'] as $presentation) {
            $label = $this->label($presentation['pricing_mode'], $presentation['commercial_quantity']);
            foreach ($presentation['warnings'] as $warning) {
                match ($warning) {
                    'consumption_missing' => $add("Indicá qué insumos lleva {$product}.", '/admin/inventory/product-consumption/'.$economics['product_id']),
                    'policy_missing' => $add("Elegí qué packaging y gastos generales usa {$product}.", '#packaging-y-gastos'),
                    'policy_inactive' => $add("Activá «{$policy['name']}» o elegí otro packaging para {$product}.", $policyHref),
                    'packaging_missing' => $add("Indicá qué caja o envoltorio lleva {$label} en «{$policy['name']}».", $policyHref),
                    'pricing_unavailable' => $add("Cargá el precio de {$label} de {$product}.", '/admin/settings/products'),
                    default => null,
                };
            }
            foreach ([...$presentation['direct_components'], ...$presentation['packaging_components']] as $line) {
                if ($line['warning'] === null) {
                    continue;
                }
                $recipe = $recipes[$line['inventory_item_id']] ?? null;
                if ($recipe === null || $line['warning'] === 'insufficient_stock') {
                    $this->itemStep($add, $line['item_name'], $line['item_kind'], $line['warning']);
                } elseif ($recipe['status'] === 'missing') {
                    $add("Creá la receta de {$line['item_name']}.", '/admin/inventory/recipes/create');
                } elseif ($recipe['status'] === 'ambiguous') {
                    $add("Dejá una sola receta activa para {$line['item_name']}.", '/admin/inventory/recipes');
                } elseif ($recipe['missing'] === []) {
                    $this->itemStep($add, $line['item_name'], $line['item_kind'], $line['warning']);
                } else {
                    foreach ($recipe['missing'] as $ingredient) {
                        $this->itemStep($add, $ingredient['name'], $ingredient['kind'], $ingredient['fallback'] ?? 'no_current_valuation');
                    }
                }
            }
        }

        return array_values($steps);
    }

    /** @param callable(string, string): void $add */
    private function itemStep(callable $add, string $name, ?string $kind, string $warning): void
    {
        match ($warning) {
            'packaging_item_ineligible' => $add("Revisá {$name}: está desactivado o no es packaging.", '/admin/inventory'),
            'negative_stock' => $add("Corregí el stock de {$name}: está en negativo. Registrá un conteo.", self::ADJUSTMENTS),
            'unreliable_valuation', 'unreliable_input' => $add("Revisá el stock de {$name}: tiene movimientos con costo dudoso.", self::ADJUSTMENTS),
            default => $kind === 'semi_finished'
                ? $add("Registrá una producción de {$name}.", self::PRODUCTION)
                : $add("Registrá una compra de {$name}.", self::PURCHASE),
        };
    }

    private function label(string $mode, int $quantity): string
    {
        return match (true) {
            $mode === 'dozen' => 'la docena',
            $mode === 'half_dozen' => 'la media docena',
            $quantity === 1 => 'la unidad',
            default => "{$quantity} unidades",
        };
    }
}
