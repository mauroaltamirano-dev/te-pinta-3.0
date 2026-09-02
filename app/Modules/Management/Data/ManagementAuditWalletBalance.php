<?php

namespace App\Modules\Management\Data;

use UnexpectedValueException;

final readonly class ManagementAuditWalletBalance
{
    public function __construct(
        public int $walletId,
        public string $walletName,
        public ?string $archivedAt,
        public int $derivedBalance,
        public int $transactionCount,
        public ?string $lastTransactionAt,
        public string $status,
    ) {}

    public static function fromDatabaseRow(object $row, string $status): self
    {
        $data = get_object_vars($row);
        $walletId = $data['id'] ?? null;
        $walletName = $data['name'] ?? null;
        $archivedAt = $data['archived_at'] ?? null;
        $derivedBalance = $data['derived_balance'] ?? null;
        $transactionCount = $data['transaction_count'] ?? null;
        $lastTransactionAt = $data['last_transaction_at'] ?? null;

        if (! is_int($walletId) && ! (is_string($walletId) && ctype_digit($walletId))) {
            throw new UnexpectedValueException('Audit wallet id must be an integer value.');
        }
        if (! is_string($walletName)) {
            throw new UnexpectedValueException('Audit wallet name must be a string.');
        }
        if ($archivedAt !== null && ! is_string($archivedAt)) {
            throw new UnexpectedValueException('Audit wallet archived_at must be a nullable string.');
        }
        if (! is_int($derivedBalance) && ! (is_string($derivedBalance) && is_numeric($derivedBalance))) {
            throw new UnexpectedValueException('Audit wallet derived balance must be numeric.');
        }
        if (! is_int($transactionCount) && ! (is_string($transactionCount) && ctype_digit($transactionCount))) {
            throw new UnexpectedValueException('Audit wallet transaction count must be an integer value.');
        }
        if ($lastTransactionAt !== null && ! is_string($lastTransactionAt)) {
            throw new UnexpectedValueException('Audit wallet last transaction date must be a nullable string.');
        }
        if (! in_array($status, ['ok', 'warning', 'critical'], true)) {
            throw new UnexpectedValueException('Audit wallet status is invalid.');
        }

        return new self(
            walletId: (int) $walletId,
            walletName: $walletName,
            archivedAt: $archivedAt,
            derivedBalance: (int) $derivedBalance,
            transactionCount: (int) $transactionCount,
            lastTransactionAt: $lastTransactionAt,
            status: $status,
        );
    }

    /** @return array{wallet_id: int, wallet_name: string, archived_at: ?string, derived_balance: int, transaction_count: int, last_transaction_at: ?string, status: string} */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->walletId,
            'wallet_name' => $this->walletName,
            'archived_at' => $this->archivedAt,
            'derived_balance' => $this->derivedBalance,
            'transaction_count' => $this->transactionCount,
            'last_transaction_at' => $this->lastTransactionAt,
            'status' => $this->status,
        ];
    }
}
