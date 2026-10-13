<?php

declare(strict_types=1);

namespace Cora\Services\Import;

use Cora\Core\Auth;
use Cora\Core\Database;
use Cora\Core\HttpException;
use Cora\Core\ValidationException;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Models\EmployeeImport;
use Cora\Models\EmployeeImportRow;
use Cora\Services\AuditTrail;
use DateTimeImmutable;

/**
 * Employee listing upload in two steps:
 *   1. validate() - reads the file, checks every row, stages it with its
 *      result and returns the summary (valid / duplicate IC / invalid dates /
 *      missing limits / ...). Nothing touches the Employee Master yet.
 *   2. confirm()  - only after validation has completed: writes the valid
 *      rows (new employees inserted, existing company + IC updated) in one
 *      transaction. Entitlement changes on existing employees are audited
 *      per employee; approved claims keep their own snapshots.
 */
final class EmployeeImportService
{
    /**
     * Template columns: key => [heading, compulsory]. The first row of the
     * file is the heading row; headings are matched ignoring case, spaces and
     * punctuation, plus the aliases below.
     */
    public const COLUMNS = [
        'company'           => ['Company', true],
        'name'              => ['Employee Name', true],
        'ic_no'             => ['IC / Passport', true],
        'entitlement_limit' => ['Entitlement Limit', true],
        'start_date'        => ['Entitlement Start Date', true],
        'end_date'          => ['Entitlement End Date', true],
        'employee_no'       => ['Employee ID', false],
        'phone'             => ['Phone Number', false],
        'address'           => ['Address', false],
        'department'        => ['Department', false],
        'info_remark'       => ['Individual Remark', false],
    ];

    private const ALIASES = [
        'companyname' => 'company', 'company' => 'company',
        'employeename' => 'name', 'name' => 'name', 'fullname' => 'name',
        'icpassport' => 'ic_no', 'ic' => 'ic_no', 'icno' => 'ic_no', 'passport' => 'ic_no', 'nric' => 'ic_no', 'icnumber' => 'ic_no',
        'entitlementlimit' => 'entitlement_limit', 'limit' => 'entitlement_limit', 'annuallimit' => 'entitlement_limit',
        'entitlementstartdate' => 'start_date', 'startdate' => 'start_date',
        'entitlementenddate' => 'end_date', 'enddate' => 'end_date',
        'employeeid' => 'employee_no', 'employeeno' => 'employee_no', 'staffid' => 'employee_no',
        'phonenumber' => 'phone', 'phone' => 'phone', 'mobile' => 'phone', 'phoneno' => 'phone',
        'address' => 'address',
        'department' => 'department', 'dept' => 'department',
        'individualremark' => 'info_remark', 'remark' => 'info_remark', 'remarks' => 'info_remark',
    ];

    public function __construct(private Database $db, private Auth $auth)
    {
    }

    /**
     * @return array<string, mixed> the import with its summary
     */
    public function validate(string $path, string $fileName): array
    {
        $rows = ImportFileReader::read($path, $fileName);
        if (count($rows) < 2) {
            throw ValidationException::single('file', 'The file has no employee rows under the heading row.');
        }
        $map = $this->mapHeader(array_shift($rows));

        // Pass 1: parse + per-row checks.
        $parsed = [];
        $icCount = [];
        $companies = [];
        foreach ($rows as $i => $cols) {
            $data = [];
            foreach ($map as $index => $key) {
                $data[$key] = trim((string) ($cols[$index] ?? ''));
            }
            [$result, $message, $clean, $companyId] = $this->checkRow($data, $companies);
            if ($companyId !== null && $clean['ic_norm'] !== '') {
                $icCount[$companyId . '|' . $clean['ic_norm']] = ($icCount[$companyId . '|' . $clean['ic_norm']] ?? 0) + 1;
            }
            $parsed[] = ['row_no' => $i + 2, 'data' => $clean, 'result' => $result, 'message' => $message, 'company_id' => $companyId];
        }

        // Pass 2: duplicate IC within the file (every occurrence - the admin
        // decides which one is right), then existing employee = update.
        foreach ($parsed as &$p) {
            $key = $p['company_id'] . '|' . $p['data']['ic_norm'];
            if ($p['result'] === 'valid_new' && ($icCount[$key] ?? 0) > 1) {
                $p['result'] = 'duplicate_ic';
                $p['message'] = 'IC / passport appears ' . $icCount[$key] . ' times for this company in the file.';
            }
            if ($p['result'] === 'valid_new') {
                $existing = Employee::findByIc((int) $p['company_id'], (string) $p['data']['ic_norm']);
                if ($existing !== null) {
                    $p['result'] = 'valid_update';
                    $p['employee_id'] = (int) $existing['id'];
                    $p['message'] = 'Updates ' . $existing['name'] . '.';
                }
            }
        }
        unset($p);

        $summary = array_fill_keys(array_keys(EmployeeImportRow::RESULTS), 0);
        foreach ($parsed as $p) {
            $summary[$p['result']]++;
        }

        return $this->db->transaction(function () use ($fileName, $parsed, $summary): array {
            $import = EmployeeImport::create([
                'file_name'       => mb_substr($fileName, 0, 255),
                'total_rows'      => count($parsed),
                'summary'         => $summary,
                'status'          => EmployeeImport::VALIDATED,
                'created_by'      => $this->auth->staffId,
                'created_by_name' => $this->auth->staffName,
            ]);
            $now = date('Y-m-d H:i:s');
            foreach (array_chunk($parsed, 200) as $chunk) {
                $values = [];
                $params = [];
                foreach ($chunk as $p) {
                    $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    array_push(
                        $params,
                        (int) $import['id'],
                        $p['row_no'],
                        json_encode($p['data'], JSON_UNESCAPED_UNICODE),
                        $p['result'],
                        $p['message'] !== null ? mb_substr($p['message'], 0, 255) : null,
                        $p['company_id'],
                        $p['employee_id'] ?? null,
                        $now,
                        $now
                    );
                }
                $this->db->execute(
                    'INSERT INTO `cora_employee_import_rows` (`import_id`, `row_no`, `data`, `result`, `message`, `company_id`, `employee_id`, `created_at`, `updated_at`) VALUES '
                    . implode(', ', $values),
                    $params
                );
            }
            AuditTrail::record($this->auth, 'import', (int) $import['id'], 'validated', null, null, $fileName . ': ' . json_encode($summary));

            return $import;
        });
    }

    /**
     * Writes the valid rows of a validated upload.
     *
     * @return array<string, mixed> the confirmed import with created / updated counts
     */
    public function confirm(int $importId): array
    {
        return $this->db->transaction(function () use ($importId): array {
            $this->db->first('SELECT `id` FROM `cora_employee_imports` WHERE `id` = ? FOR UPDATE', [$importId]);
            $import = EmployeeImport::findOrFail($importId);
            if ((int) $import['status'] !== EmployeeImport::VALIDATED) {
                throw new HttpException('This upload is already ' . strtolower(EmployeeImport::STATUSES[(int) $import['status']] ?? 'closed') . '.', 422);
            }

            $created = 0;
            $updated = 0;
            $rows = EmployeeImportRow::where('`import_id` = ? AND `result` IN (\'valid_new\', \'valid_update\')', [$importId]);
            foreach ($rows as $row) {
                $d = $row['data'];
                $fields = [
                    'company_id'        => (int) $row['company_id'],
                    'name'              => $d['name'],
                    'ic_no'             => $d['ic_no'],
                    'ic_norm'           => $d['ic_norm'],
                    'entitlement_limit' => $d['entitlement_limit'],
                    'start_date'        => $d['start_date'],
                    'end_date'          => $d['end_date'],
                    'import_id'         => $importId,
                ];
                foreach (['employee_no', 'phone', 'address', 'department', 'info_remark'] as $optional) {
                    if (($d[$optional] ?? '') !== '') {
                        $fields[$optional] = $d[$optional];
                    }
                }

                // Re-check at confirm time: someone may have added the IC since validation.
                $existing = Employee::findByIc((int) $row['company_id'], (string) $d['ic_norm']);
                if ($existing === null) {
                    $employee = Employee::create($fields + [
                        'status'          => Employee::STATUS_ACTIVE,
                        'created_by'      => $this->auth->staffId,
                        'created_by_name' => $this->auth->staffName,
                    ]);
                    $created++;
                } else {
                    $employee = Employee::update((int) $existing['id'], $fields);
                    AuditTrail::record($this->auth, 'employee', (int) $existing['id'], 'updated_by_upload', $existing, $employee, 'Upload #' . $importId);
                    $updated++;
                }
                $this->db->execute('UPDATE `cora_employee_import_rows` SET `employee_id` = ? WHERE `id` = ?', [(int) $employee['id'], (int) $row['id']]);
            }

            $after = EmployeeImport::update($importId, ['status' => EmployeeImport::CONFIRMED, 'confirmed_at' => date('Y-m-d H:i:s')]);
            AuditTrail::record($this->auth, 'import', $importId, 'confirmed', null, null, "{$created} created, {$updated} updated");

            return $after + ['created' => $created, 'updated' => $updated];
        });
    }

    public function discard(int $importId): array
    {
        $import = EmployeeImport::findOrFail($importId);
        if ((int) $import['status'] !== EmployeeImport::VALIDATED) {
            throw new HttpException('Only an upload awaiting confirmation can be discarded.', 422);
        }
        $after = EmployeeImport::update($importId, ['status' => EmployeeImport::DISCARDED]);
        AuditTrail::record($this->auth, 'import', $importId, 'discarded');

        return $after;
    }

    /** CSV template: heading row + one sample row. */
    public static function templateCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_map(static fn (array $c): string => $c[0], self::COLUMNS), ',', '"', '\\');
        fputcsv($out, ['ABC Manufacturing Sdn Bhd', 'John Tan', '900101-14-5678', '500.00', '2026-01-01', '2026-12-31', 'E1001', '012-3456789', '', 'Production', ''], ',', '"', '\\');
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF" . $csv;
    }

    // ---- internals ----

    /**
     * @param list<string> $header
     * @return array<int, string> column index => field key
     */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $heading) {
            $norm = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $heading) ?? '');
            $key = self::ALIASES[$norm] ?? null;
            if ($key !== null && !in_array($key, $map, true)) {
                $map[$i] = $key;
            }
        }
        $missing = [];
        foreach (self::COLUMNS as $key => [$heading, $required]) {
            if ($required && !in_array($key, $map, true)) {
                $missing[] = $heading;
            }
        }
        if ($missing !== []) {
            throw ValidationException::single('file', 'Missing compulsory column(s): ' . implode(', ', $missing) . '. Download the template for the expected headings.');
        }

        return $map;
    }

    /**
     * @param array<string, string> $data
     * @param array<string, int|null> $companies cache: lower-case name => id
     * @return array{0: string, 1: string|null, 2: array<string, mixed>, 3: int|null}
     */
    private function checkRow(array $data, array &$companies): array
    {
        $clean = [
            'company'           => $data['company'] ?? '',
            'name'              => mb_substr($data['name'] ?? '', 0, 255),
            'ic_no'             => mb_substr($data['ic_no'] ?? '', 0, 30),
            'ic_norm'           => Employee::normalizeIc($data['ic_no'] ?? ''),
            'entitlement_limit' => null,
            'start_date'        => null,
            'end_date'          => null,
            'employee_no'       => mb_substr($data['employee_no'] ?? '', 0, 50),
            'phone'             => mb_substr($data['phone'] ?? '', 0, 30),
            'address'           => $data['address'] ?? '',
            'department'        => mb_substr($data['department'] ?? '', 0, 150),
            'info_remark'       => $data['info_remark'] ?? '',
            'raw_limit'         => $data['entitlement_limit'] ?? '',
            'raw_start'         => $data['start_date'] ?? '',
            'raw_end'           => $data['end_date'] ?? '',
        ];

        $missing = [];
        foreach (['company' => 'Company', 'name' => 'Employee Name', 'ic_no' => 'IC / Passport'] as $k => $label) {
            if ($clean[$k] === '' || ($k === 'ic_no' && $clean['ic_norm'] === '')) {
                $missing[] = $label;
            }
        }
        if ($missing !== []) {
            return ['missing_field', 'Missing ' . implode(', ', $missing) . '.', $clean, null];
        }

        $companyKey = mb_strtolower(trim($clean['company']));
        if (!array_key_exists($companyKey, $companies)) {
            $company = Company::findByNameOrCode($clean['company']);
            $companies[$companyKey] = $company !== null ? (int) $company['id'] : null;
        }
        $companyId = $companies[$companyKey];
        if ($companyId === null) {
            return ['unknown_company', 'Company "' . $clean['company'] . '" is not in the Company Master.', $clean, null];
        }

        if ($clean['raw_limit'] === '') {
            return ['missing_limit', 'Entitlement limit is empty.', $clean, $companyId];
        }
        $limit = str_replace([',', ' '], '', preg_replace('/^RM/i', '', trim($clean['raw_limit'])) ?? '');
        if (!is_numeric($limit) || (float) $limit <= 0) {
            return ['invalid_limit', 'Entitlement limit "' . $clean['raw_limit'] . '" is not a positive amount.', $clean, $companyId];
        }
        $clean['entitlement_limit'] = number_format((float) $limit, 2, '.', '');

        $start = self::parseDate($clean['raw_start']);
        $end = self::parseDate($clean['raw_end']);
        if ($start === null || $end === null) {
            return ['invalid_date', 'Dates must be YYYY-MM-DD or DD/MM/YYYY.', $clean, $companyId];
        }
        if ($end < $start) {
            return ['invalid_date', 'End date is before the start date.', $clean, $companyId];
        }
        $clean['start_date'] = $start;
        $clean['end_date'] = $end;

        return ['valid_new', null, $clean, $companyId];
    }

    /** Date cell -> Y-m-d; accepts ISO, DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY and Excel serials. */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $value)) {
            // Excel serial day (1900 date system).
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $value . ' days')->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'Y/m/d'] as $format) {
            $d = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($d !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
