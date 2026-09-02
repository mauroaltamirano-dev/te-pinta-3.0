<?php

namespace App\Modules\Management\Data;

use UnexpectedValueException;

final readonly class ManagementAuditSummaryCounts
{
    public function __construct(
        public int $pending,
        public int $warnings,
        public int $critical,
        public int $affectedOperations,
    ) {}

    public static function fromDatabaseRow(?object $row): self
    {
        if ($row === null) {
            throw new UnexpectedValueException('The audit summary query did not return a row.');
        }
        $data = get_object_vars($row);

        return new self(
            pending: self::integer($data, 'pending'),
            warnings: self::integer($data, 'warnings'),
            critical: self::integer($data, 'critical'),
            affectedOperations: self::integer($data, 'affected_operations'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException("Audit summary column {$key} must be an integer value.");
    }
}
