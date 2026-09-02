<?php

namespace App\Modules\Management\Data;

use UnexpectedValueException;

final readonly class ManagementAuditFindingRow
{
    public function __construct(
        public string $findingKey,
        public string $type,
        public string $severity,
        public int $severityRank,
        public string $entityType,
        public ?int $entityId,
        public ?int $financialTransactionId,
        public string $title,
        public string $message,
        public string $reference,
        public ?string $effectiveDate,
        public ?int $walletId,
        public ?string $walletName,
        public ?int $amountExpected,
        public ?int $amountFound,
        public ?string $actionUrl,
    ) {}

    public static function fromDatabaseRow(object $row): self
    {
        $data = get_object_vars($row);

        return new self(
            findingKey: self::requiredString($data, 'finding_key'),
            type: self::requiredString($data, 'type'),
            severity: self::requiredString($data, 'severity'),
            severityRank: self::requiredInt($data, 'severity_rank'),
            entityType: self::requiredString($data, 'entity_type'),
            entityId: self::nullableInt($data, 'entity_id'),
            financialTransactionId: self::nullableInt($data, 'financial_transaction_id'),
            title: self::requiredString($data, 'title'),
            message: self::requiredString($data, 'message'),
            reference: self::requiredString($data, 'reference'),
            effectiveDate: self::nullableString($data, 'effective_date'),
            walletId: self::nullableInt($data, 'wallet_id'),
            walletName: self::nullableString($data, 'wallet_name'),
            amountExpected: self::nullableInt($data, 'amount_expected'),
            amountFound: self::nullableInt($data, 'amount_found'),
            actionUrl: self::nullableString($data, 'action_url'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new UnexpectedValueException("Audit query column {$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException("Audit query column {$key} must be a nullable string.");
    }

    /** @param array<string, mixed> $data */
    private static function requiredInt(array $data, string $key): int
    {
        return self::normalizeInt($data[$key] ?? null, $key, false);
    }

    /** @param array<string, mixed> $data */
    private static function nullableInt(array $data, string $key): ?int
    {
        return self::normalizeInt($data[$key] ?? null, $key, true);
    }

    private static function normalizeInt(mixed $value, string $key, bool $nullable): ?int
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value) && (float) $value === (float) (int) $value) {
            return (int) $value;
        }

        throw new UnexpectedValueException("Audit query column {$key} must be an integer value.");
    }
}
