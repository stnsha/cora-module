<?php

declare(strict_types=1);

namespace Cora\Services;

/**
 * Data masking for outlet roles: outlet staff see only what servicing
 * needs. IC / passport and phone keep their last 4 characters, the address
 * is never sent.
 */
final class Masking
{
    /** Employee fields outlet roles never receive. */
    private const HIDDEN = ['address', 'ic_norm', 'import_id', 'created_by', 'created_by_name'];

    public static function tail(?string $value, int $keep = 4): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $len = mb_strlen($value);
        if ($len <= $keep) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - $keep) . mb_substr($value, -$keep);
    }

    /**
     * @param array<string, mixed> $employee presented employee row
     * @return array<string, mixed>
     */
    public static function employee(array $employee): array
    {
        foreach (self::HIDDEN as $key) {
            unset($employee[$key]);
        }
        $employee['ic_no'] = self::tail(isset($employee['ic_no']) ? (string) $employee['ic_no'] : null);
        $employee['phone'] = self::tail(isset($employee['phone']) ? (string) $employee['phone'] : null);

        return $employee;
    }

    /**
     * @param array<string, mixed> $claim
     * @return array<string, mixed>
     */
    public static function claim(array $claim): array
    {
        $claim['employee_ic'] = self::tail(isset($claim['employee_ic']) ? (string) $claim['employee_ic'] : null);

        return $claim;
    }
}
