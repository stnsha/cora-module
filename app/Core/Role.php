<?php

declare(strict_types=1);

namespace Cora\Core;

/**
 * CORA role tier, stored as staff.cora (sql/add_staff_cora_column.sql).
 * 0 = no access. Lower number = broader access.
 */
enum Role: int
{
    case SuperAdmin = 1;
    case CorporateAdmin = 2;
    case OutletPic = 3;
    case OutletUser = 4;

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CorporateAdmin => 'Corporate Admin',
            self::OutletPic => 'Outlet PIC',
            self::OutletUser => 'Outlet User',
        };
    }

    /** Pill colour class used by the pages (never Bootstrap .badge). */
    public function pill(): string
    {
        return match ($this) {
            self::SuperAdmin => 'cora-pill-danger',
            self::CorporateAdmin => 'cora-pill-primary',
            self::OutletPic => 'cora-pill-info',
            self::OutletUser => 'cora-pill-secondary',
        };
    }

    /** @return array<int, array{label: string, cls: string}> */
    public static function map(): array
    {
        $out = [];
        foreach (self::cases() as $role) {
            $out[$role->value] = ['label' => $role->label(), 'cls' => $role->pill()];
        }

        return $out;
    }
}
