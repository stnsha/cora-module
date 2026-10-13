<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;

/**
 * A staged upload row and its validation result.
 * Table: cora_employee_import_rows.
 */
final class EmployeeImportRow extends Model
{
    /** result => label, in the order the summary shows them. */
    public const RESULTS = [
        'valid_new'       => 'Valid - new employee',
        'valid_update'    => 'Valid - updates an existing employee',
        'duplicate_ic'    => 'Duplicate IC / passport in the file',
        'invalid_date'    => 'Invalid entitlement dates',
        'missing_limit'   => 'Missing entitlement limit',
        'invalid_limit'   => 'Invalid entitlement limit',
        'unknown_company' => 'Company not found in the Company Master',
        'missing_field'   => 'Missing compulsory field',
    ];

    public const VALID = ['valid_new', 'valid_update'];

    protected const TABLE = 'cora_employee_import_rows';

    protected const FILLABLE = ['import_id', 'row_no', 'data', 'result', 'message', 'company_id', 'employee_id'];

    protected const CASTS = [
        'import_id'   => 'int',
        'row_no'      => 'int',
        'data'        => 'json',
        'company_id'  => 'int',
        'employee_id' => 'int',
    ];

    protected const ORDER = '`row_no` ASC';
}
