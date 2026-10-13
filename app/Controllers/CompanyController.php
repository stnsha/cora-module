<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\HttpException;
use Cora\Core\ValidationException;
use Cora\Models\ClientList;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Services\AuditTrail;

/**
 * Company Master (companies/). Actions:
 *   companies_options                 id + name of active companies (any role - search filter)
 *   companies_list                    every company with employee count (admin)
 *   companies_get     ?id=            company + resolved rules (admin)
 *   companies_create / companies_update / companies_delete   (Super Admin)
 *   clients_search    ?q=             Corporate Client List picker (Super Admin)
 *   clients_get       ?id=            one client list entry, as inherited (Super Admin)
 */
final class CompanyController extends Controller
{
    private const FIELDS = [
        'client_id', 'code', 'name', 'category', 'default_limit', 'limit_mode', 'period_start', 'period_end',
        'benefit_criteria', 'general_remark', 'hr_contact_name', 'hr_contact_email', 'status',
    ];

    protected function handle(string $action): mixed
    {
        switch ($action) {
            case 'companies_options':
                return array_map(
                    static fn (array $c): array => ['id' => $c['id'], 'name' => $c['name']],
                    Company::where('`status` = ?', [Company::STATUS_ACTIVE])
                );
            case 'companies_list':
                $this->requireAdmin();
                return Company::listing();
            case 'companies_get':
                $this->requireAdmin();
                $company = Company::findOrFail($this->requireId('Company'));
                return $company + ['rules' => Company::rules($company)];
            case 'companies_create':
            case 'companies_update':
                $this->requireSuper();
                return $this->save($action === 'companies_update' ? $this->requireId('Company') : null);
            case 'companies_delete':
                $this->requireSuper();
                return $this->delete($this->requireId('Company'));
            case 'clients_search':
                $this->requireSuper();
                $q = trim((string) $this->request->query('q', ''));
                return mb_strlen($q) < 2 ? [] : ClientList::search($q);
            case 'clients_get':
                $this->requireSuper();
                return ClientList::find($this->requireId('Client')) ?? throw new HttpException('Client not found in the Corporate Client List.', 404);
        }
        $this->unknown();
    }

    private function save(?int $id): array
    {
        $rules = [
            'client_id'        => ['nullable', 'integer'],
            'code'             => ['nullable', 'string', 'max:30'],
            'name'             => ['required', 'string', 'max:255'],
            'category'         => ['nullable', 'string', 'max:100'],
            'default_limit'    => ['nullable', 'numeric', 'min:0'],
            'limit_mode'       => ['required', 'integer', 'in:' . implode(',', array_keys(Company::LIMIT_MODES))],
            'period_start'     => ['nullable', 'date'],
            'period_end'       => ['nullable', 'date'],
            'benefit_criteria' => ['nullable', 'string', 'max:5000'],
            'general_remark'   => ['nullable', 'string', 'max:5000'],
            'hr_contact_name'  => ['nullable', 'string', 'max:150'],
            'hr_contact_email' => ['nullable', 'email', 'max:255'],
            'status'           => ['required', 'integer', 'in:' . implode(',', array_keys(Company::STATUSES))],
        ];
        $data = $this->validator->validate($this->request->only(self::FIELDS) + ['client_id' => null], $rules, $id);

        if ($data['client_id'] !== null && ClientList::find((int) $data['client_id']) === null) {
            throw ValidationException::single('client_id', 'The selected Corporate Client List entry was not found.');
        }
        if (!empty($data['period_start']) && !empty($data['period_end']) && strtotime($data['period_end']) < strtotime($data['period_start'])) {
            throw ValidationException::single('period_end', 'The period end must be on or after the period start.');
        }
        $this->assertUnique('name', (string) $data['name'], $id, 'A company with this name already exists.');
        if (!empty($data['code'])) {
            $this->assertUnique('code', (string) $data['code'], $id, 'A company with this code already exists.');
        }

        if ($id === null) {
            $company = Company::create($data + $this->stamp());
            AuditTrail::record($this->auth, 'company', (int) $company['id'], 'created', null, $company);
            return $company;
        }
        $before = Company::findOrFail($id);
        $company = Company::update($id, $data);
        AuditTrail::record($this->auth, 'company', $id, 'updated', $before, $company);

        return $company;
    }

    private function delete(int $id): ?array
    {
        $company = Company::findOrFail($id);
        $employees = (int) $this->db->scalar('SELECT COUNT(*) FROM `cora_employees` WHERE `company_id` = ? AND `deleted_at` IS NULL', [$id]);
        $claims = (int) $this->db->scalar('SELECT COUNT(*) FROM `cora_claims` WHERE `company_id` = ? AND `deleted_at` IS NULL AND `status` != ?', [$id, Claim::CANCELLED]);
        if ($employees > 0 || $claims > 0) {
            throw new HttpException('This company has ' . $employees . ' employee(s) and ' . $claims . ' claim(s). Set it Inactive instead of deleting it.', 422);
        }
        Company::delete($id);
        AuditTrail::record($this->auth, 'company', $id, 'deleted', $company, null);

        return null;
    }

    private function assertUnique(string $column, string $value, ?int $id, string $message): void
    {
        $sql = 'LOWER(TRIM(`' . $column . '`)) = ?';
        $params = [mb_strtolower(trim($value))];
        if ($id !== null) {
            $sql .= ' AND `id` != ?';
            $params[] = $id;
        }
        if (Company::where($sql, $params, null, 1) !== []) {
            throw ValidationException::single($column, $message);
        }
    }
}
