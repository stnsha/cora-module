<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;

/**
 * One employee bulk upload (CSV / XLSX). Rows are staged in
 * cora_employee_import_rows and only written to the Employee Master on
 * confirm, after validation has completed.
 * Table: cora_employee_imports.
 */
final class EmployeeImport extends Model
{
    public const VALIDATED = 1;
    public const CONFIRMED = 2;
    public const DISCARDED = 3;

    public const STATUSES = [
        self::VALIDATED => 'Validated - awaiting confirmation',
        self::CONFIRMED => 'Confirmed',
        self::DISCARDED => 'Discarded',
    ];

    protected const TABLE = 'cora_employee_imports';

    protected const FILLABLE = ['file_name', 'total_rows', 'summary', 'status', 'confirmed_at', 'created_by', 'created_by_name'];

    protected const CASTS = [
        'total_rows'   => 'int',
        'summary'      => 'json',
        'status'       => 'int',
        'confirmed_at' => 'datetime',
    ];

    protected const ORDER = '`id` DESC';
}
