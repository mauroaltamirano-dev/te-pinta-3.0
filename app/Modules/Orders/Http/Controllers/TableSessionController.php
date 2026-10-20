<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CancelTableSession;
use App\Modules\Orders\Actions\ChargeTableSession;
use App\Modules\Orders\Actions\IssueTableBill;
use App\Modules\Orders\Actions\RequestTableBill;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Http\Requests\ChargeTableSessionRequest;
use App\Modules\Orders\Models\TableSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Bill request (waiter), bill printing, charging and cancelling (owner, manager, cashier). */
final class TableSessionController
{
    public function requestBill(Request $request, TableSession $session, RequestTableBill $requestBill): RedirectResponse
    {
        $requestBill->execute($session, $this->actor($request));

        $this->toast('Cuenta pedida. Avisale a caja.');

        return back();
    }

    public function bill(Request $request, TableSession $session, IssueTableBill $issue): RedirectResponse
    {
        $issue->execute($session, $this->actor($request));

        $this->toast('Cuenta enviada a la impresora.');

        return back();
    }

    public function charge(ChargeTableSessionRequest $request, TableSession $session, ChargeTableSession $charge): RedirectResponse
    {
        $charge->execute($session, PaymentMethod::from($request->validated('payment_method')), $this->actor($request));

        $this->toast('Mesa cobrada y cerrada.');

        return to_route('admin.tables.index');
    }

    public function cancel(Request $request, TableSession $session, CancelTableSession $cancel): RedirectResponse
    {
        $cancel->execute($session, $this->actor($request));

        $this->toast('Mesa cancelada.');

        return to_route('admin.tables.index');
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
