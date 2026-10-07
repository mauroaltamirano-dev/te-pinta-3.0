<?php

namespace App\Modules\Management\Queries;

use App\Modules\Management\Models\Purchase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class PurchaseListQuery
{
    /** @return LengthAwarePaginator<int, Purchase> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Purchase::query()->with(['wallet', 'reversal', 'financialTransaction' => fn ($query) => $query->withCount('attachments')])
            ->withCount('lines')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('effective_date', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('effective_date', '<=', $request->string('to')->toString()))
            ->when($request->filled('wallet_id'), fn ($query) => $query->where('wallet_id', $request->integer('wallet_id')))
            ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_name', 'ilike', '%'.$request->string('supplier')->toString().'%'))
            ->when($request->input('status') === 'confirmed', fn ($query) => $query->whereDoesntHave('reversal'))
            ->when($request->input('status') === 'reversed', fn ($query) => $query->whereHas('reversal'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($nested) => $nested->where('supplier_name', 'ilike', $search)->orWhere('description', 'ilike', $search));
            })
            ->latest('effective_date')->latest('id')->paginate(20)->withQueryString();
    }
}
