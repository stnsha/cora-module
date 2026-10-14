<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\HttpException;
use Cora\Models\AuditLog;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Services\ClaimReport;
use Cora\Services\ClaimWorkflow;
use Cora\Services\Entitlement;
use Cora\Services\Masking;

/**
 * Claim Submission, Claim History and Claim Review (claims/, review/).
 * Outlet roles only ever see their own outlets' claims. Actions:
 *   claims_list     filters (Services\ClaimReport::where) + today=1, resubmitted=1
 *   claims_counts   per-status counts for the same filters (tabs)
 *   claims_get      ?id= &review=1   claim + employee entitlement now + company
 *                   rules + employee history + timeline; review=1 by an admin
 *                   moves Submitted -> Pending Review
 *   claims_save     { id?, ...fields }   save as draft
 *   claims_submit   { id?, ...fields }   submit (reserves the entitlement)
 *   claims_cancel   { id, reason }
 *   claims_approve  { id, remark? }        admin
 *   claims_reject   { id, remark }         admin, reason compulsory
 *   claims_return   { id, remark }         admin, return for amendment
 */
final class ClaimController extends Controller
{
    /** Timeline entries that show what was edited (from / to). */
    private const EDIT_ACTIONS = ['draft_updated', 'amended', 'resubmitted'];

    /**
     * Claim fields the outlet can edit, in form order: field => [label, format].
     * Snapshots, timestamps and status are left out of the timeline.
     */
    private const EDITED_FIELDS = [
        'outlet_name'     => ['Outlet', 'text'],
        'visit_date'      => ['Visit date', 'text'],
        'so_no'           => ['Sales Order No.', 'text'],
        'total_amount'    => ['Purchase amount', 'money'],
        'payment_method'  => ['Payment method', 'payment'],
        'claim_amount'    => ['Corporate claim', 'money'],
        'self_pay_amount' => ['Self-pay', 'money'],
        'claim_type'      => ['Claim type', 'text'],
        'remark'          => ['Remark', 'text'],
    ];

    private const FIELDS = ['employee_id', 'outlet_id', 'visit_date', 'so_no', 'total_amount', 'claim_amount', 'payment_method', 'claim_type', 'remark', 'authorise_duplicate'];

    protected function handle(string $action): mixed
    {
        $workflow = new ClaimWorkflow($this->db, $this->auth);

        switch ($action) {
            case 'claims_list':
                return $this->listing();
            case 'claims_counts':
                return ClaimReport::counts($this->filters(), $this->outletScope());
            case 'claims_get':
                return $this->details($workflow);
            case 'claims_save':
            case 'claims_submit':
                $id = $this->request->id();
                return $workflow->save($id > 0 ? $id : null, $this->claimInput(), $action === 'claims_submit');
            case 'claims_cancel':
                return $workflow->cancel($this->requireId('Claim'), $this->remark('Give the reason for cancelling.'));
            case 'claims_approve':
                $remark = trim((string) $this->request->get('remark', ''));
                return $workflow->approve($this->requireId('Claim'), $remark !== '' ? $remark : null);
            case 'claims_reject':
                return $workflow->reject($this->requireId('Claim'), $this->remark('A rejection reason is compulsory.'));
            case 'claims_return':
                return $workflow->returnForAmendment($this->requireId('Claim'), $this->remark('Tell the outlet what to correct.'));
        }
        $this->unknown();
    }

    /**
     * Audit diff { field: [before, after] } -> [{ label, from, to }] for the
     * editable claim fields, formatted for display ('-' for empty).
     *
     * @param array<string, array{0: mixed, 1: mixed}>|null $changes
     * @return list<array{label: string, from: string, to: string}>
     */
    private static function editedFields(?array $changes): array
    {
        $format = static fn (mixed $v, string $type): string => match (true) {
            $v === null || $v === ''               => '-',
            $type === 'money'                      => 'RM' . number_format((float) $v, 2),
            $type === 'payment'                    => Claim::PAYMENT_METHODS[(int) $v] ?? (string) $v,
            default                                => (string) $v,
        };

        $out = [];
        foreach (self::EDITED_FIELDS as $field => [$label, $type]) {
            if (!isset($changes[$field]) || !is_array($changes[$field])) {
                continue;
            }
            [$from, $to] = $changes[$field] + [null, null];
            $out[] = ['label' => $label, 'from' => $format($from, $type), 'to' => $format($to, $type)];
        }

        return $out;
    }

    /** @return list<int>|null */
    private function outletScope(): ?array
    {
        return $this->auth->isAdmin() ? null : $this->auth->outletIds;
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        $f = [];
        foreach (['month', 'date_from', 'date_to', 'company_id', 'outlet_id', 'employee_id', 'payment_method', 'status', 'q'] as $key) {
            $f[$key] = $this->request->query($key, '');
        }

        return $f;
    }

    private function listing(): array
    {
        $rows = ClaimReport::rows($this->filters(), $this->outletScope(), 3000);
        if ((string) $this->request->query('today', '') === '1') {
            $today = date('d-m-Y');
            $rows = array_values(array_filter($rows, static fn (array $r): bool => str_starts_with((string) $r['submitted_at'], $today)));
        }
        if ((string) $this->request->query('resubmitted', '') === '1') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['resubmit_count'] > 0));
        }
        if (!$this->auth->isAdmin()) {
            $rows = array_map([Masking::class, 'claim'], $rows);
        }

        // Remaining balance per employee (review queue column).
        $used = Entitlement::utilisationMany(array_values(array_unique(array_map(static fn (array $r): int => (int) $r['employee_id'], $rows))));
        $employees = [];
        foreach ($rows as &$row) {
            $eid = (int) $row['employee_id'];
            if (!array_key_exists($eid, $employees)) {
                $employee = Employee::find($eid);
                $employees[$eid] = $employee !== null ? Entitlement::summary($employee, null, null, null, $used[$eid] ?? [0.0, 0.0])['available'] : null;
            }
            $row['remaining'] = $employees[$eid];
        }
        unset($row);

        return $rows;
    }

    private function details(ClaimWorkflow $workflow): array
    {
        $id = $this->requireId('Claim');
        $claim = Claim::findVisible($id, $this->outletScope());
        if ((string) $this->request->query('review', '') === '1' && $this->auth->isAdmin()) {
            $claim = $workflow->startReview($id);
        }
        $claim = Claim::decorate($claim);

        $employee = Employee::find((int) $claim['employee_id']);
        $company = Company::find((int) $claim['company_id']);
        $rules = $company !== null ? Company::rules($company) : null;
        unset($rules['client']);

        $history = [];
        if ($employee !== null) {
            $history = array_map(static fn (array $c): array => Claim::decorate($c), Claim::where(
                '`employee_id` = ? AND `id` != ? AND `status` != ?',
                [(int) $employee['id'], $id, Claim::DRAFT],
                '`visit_date` DESC, `id` DESC',
                30
            ));
        }

        $timeline = array_map(static fn (array $a): array => [
            'action'     => $a['action'],
            'remark'     => $a['remark'],
            'staff_name' => $a['staff_name'],
            'created_at' => $a['created_at'],
            'changes'    => in_array($a['action'], self::EDIT_ACTIONS, true) ? self::editedFields($a['changes'] ?? null) : [],
        ], AuditLog::forRecord('claim', $id));

        $status = (int) $claim['status'];
        $mine = (int) ($claim['created_by'] ?? 0) === $this->auth->staffId;
        $permissions = [
            'edit'    => match ($status) {
                Claim::DRAFT => $this->auth->canAmend() || $mine,
                Claim::SUBMITTED, Claim::AMENDMENT_REQUIRED => $this->auth->canAmend(),
                default => false,
            },
            'cancel'  => match (true) {
                $this->auth->isAdmin() => in_array($status, [Claim::DRAFT, Claim::SUBMITTED, Claim::PENDING_REVIEW, Claim::AMENDMENT_REQUIRED, Claim::APPROVED], true),
                $this->auth->canAmend() => in_array($status, [Claim::DRAFT, Claim::SUBMITTED, Claim::PENDING_REVIEW, Claim::AMENDMENT_REQUIRED], true),
                default => $status === Claim::DRAFT && $mine,
            },
            'review'  => $this->auth->isAdmin() && in_array($status, Claim::IN_QUEUE, true),
        ];

        $out = [
            'claim'       => $this->auth->isAdmin() ? $claim : Masking::claim($claim),
            'employee'    => $employee === null ? null : ($this->auth->isAdmin() ? $employee : Masking::employee($employee)),
            'entitlement' => $employee !== null ? Entitlement::summary($employee, $company, null) : null,
            'rules'       => $rules,
            'history'     => array_map(fn (array $c): array => $this->auth->isAdmin() ? $c : Masking::claim($c), $history),
            'timeline'    => $timeline,
            'can'         => $permissions,
        ];
        if ($employee !== null) {
            $out['employee']['restriction_label'] = Employee::RESTRICTIONS[(int) $employee['restriction_type']] ?? '';
            $out['employee']['status_label'] = Employee::STATUSES[(int) $employee['status']] ?? '';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function claimInput(): array
    {
        $rules = [
            'employee_id'         => ['required', 'integer', 'exists:cora_employees,id'],
            'outlet_id'           => ['required', 'integer'],
            'visit_date'          => ['required', 'date'],
            'so_no'               => ['required', 'string', 'max:50'],
            'total_amount'        => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'claim_amount'        => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'payment_method'      => ['required', 'integer', 'in:' . implode(',', array_keys(Claim::PAYMENT_METHODS))],
            'claim_type'          => ['nullable', 'string', 'max:50'],
            'remark'              => ['nullable', 'string', 'max:2000'],
            'authorise_duplicate' => ['nullable', 'boolean'],
        ];
        $data = $this->validator->validate($this->request->only(self::FIELDS), $rules);
        $data['visit_date'] = Entitlement::ymd($data['visit_date']) ?? date('Y-m-d', (int) strtotime((string) $data['visit_date']));

        return $data;
    }

    private function remark(string $requiredMessage): string
    {
        $remark = trim((string) $this->request->get('remark', $this->request->get('reason', '')));
        if ($remark === '') {
            throw new HttpException($requiredMessage, 422);
        }

        return mb_substr($remark, 0, 2000);
    }
}
