<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\ConfirmTableRound;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Http\Requests\StoreTableRoundRequest;
use App\Modules\Orders\Queries\TableServiceBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The waiter screens: tables, one table, and confirming a round on it. */
final class TableServiceController
{
    public function index(Request $request, TableServiceBoard $board): Response
    {
        return Inertia::render('admin/tables/index', ['tables' => $board->tables($this->actor($request))]);
    }

    public function show(Request $request, int $table, TableServiceBoard $board): Response
    {
        $data = $board->table($table, $this->actor($request));
        abort_if($data === null, 404);

        return Inertia::render('admin/tables/show', $data);
    }

    public function storeRound(StoreTableRoundRequest $request, int $table, ConfirmTableRound $confirm): RedirectResponse
    {
        $round = $confirm->execute(
            diningTableId: $table,
            expectedSessionId: $request->validated('expected_session_id') === null ? null : (int) $request->validated('expected_session_id'),
            kind: TableRoundKind::from($request->validated('kind')),
            items: $request->items(),
            addons: $request->addons(),
            note: $request->validated('note'),
            roundKey: $request->validated('round_key'),
            actor: $this->actor($request),
        );
        $message = $round->kind === TableRoundKind::Void
            ? "Anulación (ronda {$round->sequence}) confirmada y enviada a cocina."
            : "Ronda {$round->sequence} confirmada y enviada a cocina.";

        $this->toast($message);

        return to_route('admin.tables.show', $table);
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
