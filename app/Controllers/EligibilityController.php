<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\ValidationException;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Models\Outlet;
use Cora\Models\Setting;
use Cora\Services\Entitlement;
use Cora\Services\Masking;

/**
 * Eligibility Checker (eligibility/) - the outlet's first screen. Actions:
 *   eligibility_search  ?q= &company_id=   employees with their status (min 3 characters)
 *   eligibility_card    ?id=   Employee Eligibility Card: entitlement, company
 *                       rules, individual remark / restriction, claim history
 *   claim_context       the signed-in user's outlets + claim types + payment methods
 * Outlet roles receive masked employee data (Services\Masking).
 */
final class EligibilityController extends Controller
{
    protected function handle(string $action): mixed
    {
        return match ($action) {
            'eligibility_search' => $this->search(),
            'eligibility_card' => $this->card($this->requireId('Employee')),
            'claim_context' => $this->context(),
            default => $this->unknown(),
        };
    }

    private function search(): array
    {
        $q = trim((string) $this->request->query('q', ''));
        if (mb_strlen($q) < 3) {
            throw ValidationException::single('q', 'Type at least 3 characters of the IC / passport, name, phone or employee ID.');
        }
        $companyId = (int) $this->request->query('company_id', 0);

        $rows = Employee::search($q, $companyId > 0 ? $companyId : null, 25);
        $used = Entitlement::utilisationMany(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $companies = [];
        return array_map(function (array $row) use (&$companies, $used): array {
            $cid = (int) $row['company_id'];
            $companies[$cid] ??= Company::find($cid);
            $summary = Entitlement::summary($row, $companies[$cid], null, null, $used[(int) $row['id']] ?? [0.0, 0.0]);
            $out = [
                'id'               => (int) $row['id'],
                'name'             => $row['name'],
                'ic_no'            => $row['ic_no'],
                'employee_no'      => $row['employee_no'],
                'phone'            => $row['phone'],
                'company_name'     => $row['company_name'],
                'department'       => $row['department'],
                'status'           => $summary['status'],
                'status_pill'      => $summary['status_pill'],
                'available'        => $summary['available'],
                'restricted'       => (int) $row['restriction_type'] !== Employee::RESTRICTION_NONE,
            ];
            return $this->auth->isAdmin() ? $out : Masking::employee($out);
        }, $rows);
    }

    private function card(int $id): array
    {
        $employee = Employee::findOrFail($id);
        $company = Company::find((int) $employee['company_id']);
        $summary = Entitlement::summary($employee, $company);
        $rules = $company !== null ? Company::rules($company) : null;
        unset($rules['client']);

        // Claim history inside the current period (newest first) - every
        // outlet's claims, so the outlet sees what is already reserved.
        $start = Entitlement::ymd($employee['start_date']);
        $end = Entitlement::ymd($employee['end_date']);
        $history = Claim::where(
            '`employee_id` = ? AND `status` != ? AND `visit_date` BETWEEN ? AND ?',
            [$id, Claim::DRAFT, $start ?? '1900-01-01', $end ?? '2999-12-31'],
            '`visit_date` DESC, `id` DESC',
            50
        );
        $history = array_map(function (array $c): array {
            $c = Claim::decorate($c);
            $mine = $this->auth->canSeeOutlet((int) $c['outlet_id']);
            return [
                'id'                   => $mine ? (int) $c['id'] : null,
                'claim_no'             => $c['claim_no'],
                'visit_date'           => $c['visit_date'],
                'outlet_name'          => $c['outlet_name'],
                'so_no'                => $mine ? $c['so_no'] : null,
                'claim_amount'         => $c['claim_amount'],
                'payment_method_label' => $c['payment_method_label'],
                'status'               => $c['status'],
                'status_label'         => $c['status_label'],
            ];
        }, $history);

        $employee['company_name'] = $company['name'] ?? null;
        $employee['status_label'] = Employee::STATUSES[(int) $employee['status']] ?? '';
        $employee['restriction_label'] = Employee::RESTRICTIONS[(int) $employee['restriction_type']] ?? '';

        return [
            'employee'    => $this->auth->isAdmin() ? $employee : Masking::employee($employee),
            'entitlement' => $summary,
            'rules'       => $rules,
            'history'     => $history,
        ];
    }

    private function context(): array
    {
        $outlets = $this->auth->isOutlet() || $this->auth->outletIds !== []
            ? Outlet::byIds($this->auth->outletIds)
            : [];
        if ($outlets === [] && $this->auth->isAdmin()) {
            // Admins may record a claim on behalf of any outlet.
            $outlets = Outlet::all();
        }

        return [
            'outlets'         => $outlets,
            'claim_types'     => Setting::claimTypes(),
            'payment_methods' => Claim::PAYMENT_METHODS,
            'can_authorise_duplicate' => $this->auth->isAdmin(),
        ];
    }
}
