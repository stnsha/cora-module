<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Auth;
use Cora\Core\Database;
use Cora\Core\HttpException;
use Cora\Core\ValidationException;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Models\Outlet;

/**
 * Every claim status transition, with its controls:
 *   - duplicate Sales Order detection (an admin may authorise a duplicate)
 *   - entitlement-date validation (outside the period = flagged)
 *   - limit validation (company limit_mode: block, or warn + flag)
 *   - reservation: submission and approval lock the employee row
 *     (SELECT ... FOR UPDATE) so two outlets submitting at the same moment
 *     cannot both use the same balance
 *   - historical preservation: employee / company / limit snapshots are
 *     written on submit and never recalculated afterwards
 *   - audit trail for every transition
 */
final class ClaimWorkflow
{
    public function __construct(private Database $db, private Auth $auth)
    {
    }

    /**
     * Create or edit a claim, as a draft or submitted.
     *
     * @param array<string, mixed> $input validated: employee_id, outlet_id, visit_date, so_no,
     *                                    total_amount, claim_amount, payment_method, claim_type,
     *                                    remark, authorise_duplicate
     * @return array<string, mixed> the stored claim
     */
    public function save(?int $id, array $input, bool $submit): array
    {
        return $this->db->transaction(function () use ($id, $input, $submit): array {
            $existing = $id !== null ? $this->lockClaim($id) : null;
            if ($existing !== null) {
                $this->assertEditable($existing);
            }

            $employee = $this->lockEmployee((int) $input['employee_id']);
            $company = Company::findOrFail((int) $employee['company_id']);
            $outlet = $this->outlet((int) $input['outlet_id']);
            [$total, $corporate, $selfPay] = $this->amounts($input);

            $soNorm = Claim::normalizeSo((string) $input['so_no']);
            if ($soNorm === '') {
                throw ValidationException::single('so_no', 'The sales order number field is required.');
            }

            $data = [
                'employee_id'     => (int) $employee['id'],
                'company_id'      => (int) $company['id'],
                'outlet_id'       => $outlet['id'],
                'outlet_name'     => $outlet['name'],
                'employee_name'   => $employee['name'],
                'employee_ic'     => $employee['ic_no'],
                'employee_no'     => $employee['employee_no'],
                'company_name'    => $company['name'],
                'visit_date'      => $input['visit_date'],
                'so_no'           => trim((string) $input['so_no']),
                'so_norm'         => $soNorm,
                'total_amount'    => $total,
                'claim_amount'    => $corporate,
                'self_pay_amount' => $selfPay,
                'payment_method'  => (int) $input['payment_method'],
                'claim_type'      => $input['claim_type'] ?? null,
                'remark'          => $input['remark'] ?? null,
            ];

            if ($submit) {
                $data += $this->submissionChecks($existing, $employee, $company, $data, !empty($input['authorise_duplicate']));
            } else {
                $data['status'] = $existing !== null ? (int) $existing['status'] : Claim::DRAFT;
                if ($data['status'] !== Claim::DRAFT && $data['status'] !== Claim::AMENDMENT_REQUIRED) {
                    // A submitted claim being amended stays submitted only through submit.
                    throw new HttpException('A submitted claim can only be saved by submitting it again.', 422);
                }
            }

            if ($existing === null) {
                $claim = Claim::create($data + ['created_by' => $this->auth->staffId, 'created_by_name' => $this->auth->staffName]);
                $claim = Claim::update((int) $claim['id'], ['claim_no' => Claim::makeNumber((int) $claim['id'])]);
                AuditTrail::record($this->auth, 'claim', (int) $claim['id'], $submit ? 'submitted' : 'draft_created', null, $claim);

                return Claim::decorate($claim);
            }

            $claim = Claim::update((int) $existing['id'], $data);
            $action = match (true) {
                !$submit => 'draft_updated',
                (int) $existing['status'] === Claim::AMENDMENT_REQUIRED => 'resubmitted',
                (int) $existing['status'] === Claim::DRAFT => 'submitted',
                default => 'amended',
            };
            AuditTrail::record($this->auth, 'claim', (int) $claim['id'], $action, $existing, $claim);

            return Claim::decorate($claim);
        });
    }

    /** Admin opened a submitted claim: Submitted -> Pending Review. */
    public function startReview(int $id): array
    {
        $claim = Claim::findOrFail($id);
        if ((int) $claim['status'] !== Claim::SUBMITTED || !$this->auth->isAdmin()) {
            return $claim;
        }
        $after = Claim::update($id, ['status' => Claim::PENDING_REVIEW]);
        AuditTrail::record($this->auth, 'claim', $id, 'review_started', $claim, $after);

        return $after;
    }

    /** Approve: the reserved amount becomes approved utilisation. */
    public function approve(int $id, ?string $remark): array
    {
        return $this->db->transaction(function () use ($id, $remark): array {
            $claim = $this->lockClaim($id);
            $this->assertInQueue($claim);
            $employee = $this->lockEmployee((int) $claim['employee_id']);
            $company = Company::find((int) $claim['company_id']);

            // Re-check the balance without this claim's own reservation: an
            // admin entitlement change since submission must not be overrun.
            $summary = Entitlement::summary($employee, $company, $id);
            $room = round($summary['limit'] - $summary['approved'] - $summary['pending'], 2);
            if ((float) $claim['claim_amount'] > $room + 0.001 && $summary['limit_mode'] === Company::LIMIT_BLOCK) {
                throw new HttpException(sprintf(
                    'Approving would exceed the entitlement: only RM%s remains after other approved and pending claims. Return the claim for amendment or reject it.',
                    number_format(max(0.0, $room), 2)
                ), 422);
            }

            $after = Claim::update($id, $this->decision(Claim::APPROVED, $remark));
            AuditTrail::record($this->auth, 'claim', $id, 'approved', $claim, $after, $remark);

            return Claim::decorate($after);
        });
    }

    /** Reject (reason compulsory): the reservation is released. */
    public function reject(int $id, string $reason): array
    {
        return $this->decide($id, Claim::REJECTED, $reason, 'rejected');
    }

    /** Return for amendment (remark compulsory): stays reserved until corrected or cancelled. */
    public function returnForAmendment(int $id, string $remark): array
    {
        return $this->decide($id, Claim::AMENDMENT_REQUIRED, $remark, 'returned_for_amendment');
    }

    /**
     * Cancel / void. Outlet User: own drafts. Outlet PIC: any of the outlet's
     * claims not yet decided. Admin: any claim, approved included (void).
     */
    public function cancel(int $id, string $reason): array
    {
        return $this->db->transaction(function () use ($id, $reason): array {
            $claim = $this->lockClaim($id);
            $status = (int) $claim['status'];
            $open = [Claim::DRAFT, Claim::SUBMITTED, Claim::PENDING_REVIEW, Claim::AMENDMENT_REQUIRED];

            $allowed = match (true) {
                $this->auth->isAdmin() => in_array($status, [...$open, Claim::APPROVED], true),
                $this->auth->canAmend() => in_array($status, $open, true),
                default => $status === Claim::DRAFT && (int) $claim['created_by'] === $this->auth->staffId,
            };
            if (!$allowed) {
                throw new HttpException('This claim cannot be cancelled by you in its current status (' . (Claim::STATUSES[$status] ?? $status) . ').', 403);
            }

            $after = Claim::update($id, [
                'status'            => Claim::CANCELLED,
                'cancelled_by'      => $this->auth->staffId,
                'cancelled_by_name' => $this->auth->staffName,
                'cancelled_at'      => date('Y-m-d H:i:s'),
                'cancel_reason'     => $reason,
            ]);
            AuditTrail::record($this->auth, 'claim', $id, $status === Claim::APPROVED ? 'voided' : 'cancelled', $claim, $after, $reason);

            return Claim::decorate($after);
        });
    }

    // ---- internals ----

    private function decide(int $id, int $status, string $remark, string $action): array
    {
        return $this->db->transaction(function () use ($id, $status, $remark, $action): array {
            $claim = $this->lockClaim($id);
            $this->assertInQueue($claim);
            $after = Claim::update($id, $this->decision($status, $remark));
            AuditTrail::record($this->auth, 'claim', $id, $action, $claim, $after, $remark);

            return Claim::decorate($after);
        });
    }

    /** @return array<string, mixed> */
    private function decision(int $status, ?string $remark): array
    {
        return [
            'status'           => $status,
            'reviewed_by'      => $this->auth->staffId,
            'reviewed_by_name' => $this->auth->staffName,
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'review_remark'    => $remark,
        ];
    }

    /**
     * Submission controls; returns the status / snapshot / flag columns.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $company
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function submissionChecks(?array $existing, array $employee, array $company, array $data, bool $authoriseDuplicate): array
    {
        $summary = Entitlement::summary($employee, $company, $existing !== null ? (int) $existing['id'] : null);
        if (in_array($summary['status'], [Entitlement::SUSPENDED, Entitlement::INACTIVE], true)) {
            throw new HttpException('Employee is ' . $summary['status'] . ' - a corporate claim cannot be submitted.', 422);
        }

        $visit = Entitlement::ymd($data['visit_date']) ?? (string) $data['visit_date'];
        if ($visit > date('Y-m-d')) {
            throw ValidationException::single('visit_date', 'The visitation date cannot be in the future.');
        }

        $flags = [];
        if (!Entitlement::inPeriod($employee, $visit)) {
            $flags['outside_period'] = true;
        }
        if ($summary['status'] !== Entitlement::ACTIVE && $summary['status'] !== Entitlement::LIMIT_EXHAUSTED) {
            $flags['not_active'] = true;
        }

        $corporate = (float) $data['claim_amount'];
        if ($corporate > $summary['available'] + 0.001) {
            if ($summary['limit_mode'] === Company::LIMIT_BLOCK) {
                throw ValidationException::single('claim_amount', sprintf(
                    'The corporate claim of RM%s exceeds the available balance of RM%s. Use Mixed Payment: corporate RM%s and self-pay for the rest.',
                    number_format($corporate, 2),
                    number_format($summary['available'], 2),
                    number_format($summary['available'], 2)
                ));
            }
            $flags['exceeds_limit'] = true;
        }

        $duplicate = Claim::findDuplicateSo((string) $data['so_norm'], $existing !== null ? (int) $existing['id'] : null);
        $authorised = false;
        if ($duplicate !== null) {
            if (!($authoriseDuplicate && $this->auth->isAdmin())) {
                throw ValidationException::single('so_no', 'Sales Order ' . $data['so_no'] . ' was already submitted on claim '
                    . $duplicate['claim_no'] . ' (' . ($duplicate['outlet_name'] ?? '') . '). A duplicate needs admin authorisation.');
            }
            $flags['duplicate_so'] = true;
            $authorised = true;
        }

        $resubmits = (int) ($existing['resubmit_count'] ?? 0);

        return [
            'status'               => Claim::SUBMITTED,
            'flags'                => $flags === [] ? null : $flags,
            'limit_snapshot'       => $summary['limit'],
            'available_snapshot'   => $summary['available'],
            'period_start'         => $employee['start_date'],
            'period_end'           => $employee['end_date'],
            'duplicate_authorised' => $authorised,
            'resubmit_count'       => $existing !== null && (int) $existing['status'] === Claim::AMENDMENT_REQUIRED ? $resubmits + 1 : $resubmits,
            'submitted_by'         => $this->auth->staffId,
            'submitted_by_name'    => $this->auth->staffName,
            'submitted_at'         => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Payment method rules -> [total, corporate, self-pay].
     *   Corporate Claim: corporate = total
     *   Mixed Payment:   0 < corporate < total, self-pay = total - corporate
     *   Self-Pay / TPA:  nothing charged to the corporate entitlement
     *
     * @param array<string, mixed> $input
     * @return array{0: float, 1: float, 2: float}
     */
    private function amounts(array $input): array
    {
        $total = round((float) $input['total_amount'], 2);
        if ($total <= 0) {
            throw ValidationException::single('total_amount', 'The purchase amount must be more than 0.');
        }

        return match ((int) $input['payment_method']) {
            Claim::PAY_CORPORATE => [$total, $total, 0.0],
            Claim::PAY_MIXED => $this->mixed($total, round((float) ($input['claim_amount'] ?? 0), 2)),
            Claim::PAY_SELF, Claim::PAY_TPA => [$total, 0.0, (int) $input['payment_method'] === Claim::PAY_SELF ? $total : 0.0],
            default => throw ValidationException::single('payment_method', 'The selected payment method is invalid.'),
        };
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function mixed(float $total, float $corporate): array
    {
        if ($corporate <= 0 || $corporate >= $total) {
            throw ValidationException::single('claim_amount', 'For Mixed Payment the corporate claim must be more than 0 and less than the purchase amount.');
        }

        return [$total, $corporate, round($total - $corporate, 2)];
    }

    /** @param array<string, mixed> $claim */
    private function assertEditable(array $claim): void
    {
        $status = (int) $claim['status'];
        $ok = match ($status) {
            Claim::DRAFT => $this->auth->canAmend() || (int) $claim['created_by'] === $this->auth->staffId,
            Claim::SUBMITTED, Claim::AMENDMENT_REQUIRED => $this->auth->canAmend(),
            default => false,
        };
        if (!$ok) {
            throw new HttpException('This claim cannot be edited by you in its current status (' . (Claim::STATUSES[$status] ?? $status) . ').', 403);
        }
    }

    /** @param array<string, mixed> $claim */
    private function assertInQueue(array $claim): void
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException('Only Corporate Admins and Super Admins review claims.', 403);
        }
        if (!in_array((int) $claim['status'], Claim::IN_QUEUE, true)) {
            throw new HttpException('This claim is ' . (Claim::STATUSES[(int) $claim['status']] ?? '') . ' and no longer waiting for review.', 422);
        }
    }

    /** @return array{id: int, code: string, name: string} */
    private function outlet(int $outletId): array
    {
        if (!$this->auth->canSeeOutlet($outletId)) {
            throw ValidationException::single('outlet_id', 'You can only submit claims for your own outlet.');
        }
        $outlet = Outlet::find($outletId);
        if ($outlet === null) {
            throw ValidationException::single('outlet_id', 'The selected outlet is invalid.');
        }

        return $outlet;
    }

    /** @return array<string, mixed> the claim, row-locked, visible to the caller */
    private function lockClaim(int $id): array
    {
        $this->db->first('SELECT `id` FROM `cora_claims` WHERE `id` = ? AND `deleted_at` IS NULL FOR UPDATE', [$id]);

        return Claim::findVisible($id, $this->auth->isAdmin() ? null : $this->auth->outletIds);
    }

    /** @return array<string, mixed> the employee, row-locked */
    private function lockEmployee(int $id): array
    {
        $this->db->first('SELECT `id` FROM `cora_employees` WHERE `id` = ? AND `deleted_at` IS NULL FOR UPDATE', [$id]);

        return Employee::findOrFail($id);
    }
}
