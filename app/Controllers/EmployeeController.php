<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\HttpException;
use Cora\Core\ValidationException;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Models\EmployeeImport;
use Cora\Models\EmployeeImportRow;
use Cora\Services\AuditTrail;
use Cora\Services\Entitlement;
use Cora\Services\Import\EmployeeImportService;

/**
 * Employee Master (employees/) - Corporate Admin and Super Admin. Actions:
 *   employees_list    ?company_id= &q= &status=   listing with eligibility status
 *   employees_get     ?id=   employee + entitlement summary
 *   employees_create / employees_update / employees_delete
 *   import_template                 CSV template download
 *   import_validate   (multipart: file)  stage + validate an upload -> summary
 *   imports_list                    recent uploads
 *   import_rows       ?id= &result=  staged rows of one upload
 *   import_confirm    { id }        write the valid rows (only once validated)
 *   import_discard    { id }
 */
final class EmployeeController extends Controller
{
    private const FIELDS = [
        'company_id', 'employee_no', 'name', 'ic_no', 'phone', 'address', 'department', 'entitlement_limit',
        'start_date', 'end_date', 'status', 'info_remark', 'restriction_type', 'override_limit', 'override_until',
        'restriction_remark',
    ];

    protected function handle(string $action): mixed
    {
        $this->requireAdmin();
        $import = new EmployeeImportService($this->db, $this->auth);

        switch ($action) {
            case 'employees_list':
                return $this->listing();
            case 'employees_get':
                $employee = Employee::findOrFail($this->requireId('Employee'));
                return $employee + ['entitlement' => Entitlement::summary($employee)];
            case 'employees_create':
            case 'employees_update':
                return $this->save($action === 'employees_update' ? $this->requireId('Employee') : null);
            case 'employees_delete':
                return $this->delete($this->requireId('Employee'));
            case 'import_template':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="cora_employee_upload_template.csv"');
                echo EmployeeImportService::templateCsv();
                exit;
            case 'import_validate':
                $file = $_FILES['file'] ?? null;
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                    throw ValidationException::single('file', 'Choose a CSV or XLSX file to upload.');
                }
                return $import->validate((string) $file['tmp_name'], (string) $file['name']);
            case 'imports_list':
                return EmployeeImport::where('1 = 1', [], null, 30);
            case 'import_rows':
                $id = $this->requireId('Upload');
                $result = (string) $this->request->query('result', '');
                if ($result !== '' && !isset(EmployeeImportRow::RESULTS[$result])) {
                    throw ValidationException::single('result', 'Unknown result filter.');
                }
                return [
                    'import' => EmployeeImport::findOrFail($id),
                    'rows'   => $result === ''
                        ? EmployeeImportRow::where('`import_id` = ?', [$id], null, 5000)
                        : EmployeeImportRow::where('`import_id` = ? AND `result` = ?', [$id, $result], null, 5000),
                ];
            case 'import_confirm':
                return $import->confirm($this->requireId('Upload'));
            case 'import_discard':
                return $import->discard($this->requireId('Upload'));
        }
        $this->unknown();
    }

    private function listing(): array
    {
        $companyId = (int) $this->request->query('company_id', 0);
        $q = (string) $this->request->query('q', '');
        $rows = Employee::search($q, $companyId > 0 ? $companyId : null, 3000);
        $status = (int) $this->request->query('status', 0);
        if ($status > 0) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['status'] === $status));
        }

        $companies = [];
        $used = Entitlement::utilisationMany(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $cid = (int) $row['company_id'];
            $companies[$cid] ??= Company::find($cid);
            $summary = Entitlement::summary($row, $companies[$cid], null, null, $used[(int) $row['id']] ?? [0.0, 0.0]);
            $row['eligibility'] = $summary['status'];
            $row['eligibility_pill'] = $summary['status_pill'];
            $row['limit'] = $summary['limit'];
            $row['available'] = $summary['available'];
        }
        unset($row);

        return $rows;
    }

    private function save(?int $id): array
    {
        $rules = [
            'company_id'         => ['required', 'integer', 'exists:cora_companies,id'],
            'employee_no'        => ['nullable', 'string', 'max:50'],
            'name'               => ['required', 'string', 'max:255'],
            'ic_no'              => ['required', 'string', 'max:30'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'address'            => ['nullable', 'string', 'max:1000'],
            'department'         => ['nullable', 'string', 'max:150'],
            'entitlement_limit'  => ['required', 'numeric', 'min:0'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['required', 'date'],
            'status'             => ['required', 'integer', 'in:' . implode(',', array_keys(Employee::STATUSES))],
            'info_remark'        => ['nullable', 'string', 'max:2000'],
            'restriction_type'   => ['required', 'integer', 'in:' . implode(',', array_keys(Employee::RESTRICTIONS))],
            'override_limit'     => ['nullable', 'numeric', 'min:0'],
            'override_until'     => ['nullable', 'date'],
            'restriction_remark' => ['nullable', 'string', 'max:2000'],
        ];
        $data = $this->validator->validate($this->request->only(self::FIELDS), $rules, $id);

        if (strtotime((string) $data['end_date']) < strtotime((string) $data['start_date'])) {
            throw ValidationException::single('end_date', 'The entitlement end date must be on or after the start date.');
        }
        $data['ic_norm'] = Employee::normalizeIc((string) $data['ic_no']);
        if ($data['ic_norm'] === '') {
            throw ValidationException::single('ic_no', 'The IC / passport must contain letters or digits.');
        }
        Employee::assertUniqueIc((int) $data['company_id'], $data['ic_norm'], $id);

        // Restriction / override: the operational instruction is structured, never free text alone.
        if ((int) $data['restriction_type'] === Employee::RESTRICTION_LIMIT && ($data['override_limit'] ?? null) === null) {
            throw ValidationException::single('override_limit', 'Enter the override entitlement limit.');
        }
        if ((int) $data['restriction_type'] !== Employee::RESTRICTION_NONE && trim((string) ($data['restriction_remark'] ?? '')) === '') {
            throw ValidationException::single('restriction_remark', 'Give the reason for the restriction / override (shown to outlets).');
        }
        if ((int) $data['restriction_type'] !== Employee::RESTRICTION_LIMIT) {
            $data['override_limit'] = null;
            $data['override_until'] = null;
        }
        if ((int) $data['restriction_type'] === Employee::RESTRICTION_NONE) {
            $data['restriction_remark'] = null;
        }

        if ($id === null) {
            $employee = Employee::create($data + $this->stamp());
            AuditTrail::record($this->auth, 'employee', (int) $employee['id'], 'created', null, $employee);
        } else {
            $before = Employee::findOrFail($id);
            $employee = Employee::update($id, $data);
            $entitlementChanged = AuditTrail::diff(
                array_intersect_key($before, array_flip(['entitlement_limit', 'start_date', 'end_date', 'restriction_type', 'override_limit', 'override_until'])),
                array_intersect_key($employee, array_flip(['entitlement_limit', 'start_date', 'end_date', 'restriction_type', 'override_limit', 'override_until']))
            ) !== [];
            AuditTrail::record($this->auth, 'employee', $id, $entitlementChanged ? 'entitlement_adjusted' : 'updated', $before, $employee);
        }

        return $employee + ['entitlement' => Entitlement::summary($employee)];
    }

    private function delete(int $id): ?array
    {
        $employee = Employee::findOrFail($id);
        $open = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM `cora_claims` WHERE `employee_id` = ? AND `deleted_at` IS NULL AND `status` IN (' . implode(',', [Claim::DRAFT, ...Claim::RESERVING]) . ')',
            [$id]
        );
        if ($open > 0) {
            throw new HttpException('This employee has ' . $open . ' open claim(s). Decide or cancel them first, or set the employee Inactive.', 422);
        }
        Employee::delete($id);
        AuditTrail::record($this->auth, 'employee', $id, 'deleted', $employee, null);

        return null;
    }
}
