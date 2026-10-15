<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManagementOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinancialTransaction::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $periods = ['today', '7_days', '30_days', 'this_month', 'previous_month', 'custom'];

        return [
            'section' => ['nullable', Rule::in(['panorama', 'operations', 'wallets', 'movements'])],
            'transaction_id' => ['nullable', 'integer', Rule::exists('financial_transactions', 'id')],
            'wallet_id' => ['nullable', 'integer', Rule::exists('wallets', 'id')],
            'type' => ['nullable', Rule::in([...array_column(FinancialTransactionType::cases(), 'value'), ...array_column(ManagementOutflowType::cases(), 'value')])],
            'category_id' => ['nullable', 'integer', Rule::exists('management_outflow_categories', 'id')],
            'period' => ['required', Rule::in($periods)],
            'history_period' => ['required', Rule::in($periods)],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'history_from' => ['required', 'date_format:Y-m-d'],
            'history_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:history_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::today(config('orders.timezone'));
        $period = $this->period('period', 'from', 'to', '30_days');
        [$from, $to] = $this->range($period, 'from', 'to', $today);
        // Existing links with only from/to historically scoped both lists and totals.
        $legacyRange = ! $this->has('period') && $this->hasAny(['from', 'to']) && ! $this->hasAny(['history_period', 'history_from', 'history_to']);
        $historyPeriod = $legacyRange ? 'custom' : $this->period('history_period', 'history_from', 'history_to', '7_days');
        [$historyFrom, $historyTo] = $legacyRange ? [$from, $to] : $this->range($historyPeriod, 'history_from', 'history_to', $today);
        $this->merge(['period' => $period, 'from' => $from, 'to' => $to, 'history_period' => $historyPeriod, 'history_from' => $historyFrom, 'history_to' => $historyTo]);
    }

    private function period(string $key, string $from, string $to, string $default): mixed
    {
        return match ($value = $this->input($key, $this->hasAny([$from, $to]) ? 'custom' : $default)) {
            'week' => '7_days',
            'month' => '30_days',
            default => $value,
        };
    }

    /** @return array{mixed, mixed} */
    private function range(mixed $period, string $from, string $to, CarbonImmutable $today): array
    {
        return match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            '7_days' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            '30_days' => [$today->subDays(29)->toDateString(), $today->toDateString()],
            'previous_month' => [$today->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'custom' => [$this->input($from, $today->startOfMonth()->toDateString()), $this->input($to, $today->toDateString())],
            default => [$today->startOfMonth()->toDateString(), $today->toDateString()],
        };
    }
}
