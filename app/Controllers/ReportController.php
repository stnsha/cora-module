<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Services\AuditTrail;
use Cora\Services\ClaimReport;

/**
 * Monthly Claim Report (reports/) - admin. Actions:
 *   report_list     ?month= &company_id= &outlet_id= &q= &status= &payment_method=
 *   report_export   same filters + &format=csv|xlsx -> file download
 */
final class ReportController extends Controller
{
    protected function handle(string $action): mixed
    {
        $this->requireAdmin();
        $filters = [];
        foreach (['month', 'date_from', 'date_to', 'company_id', 'outlet_id', 'payment_method', 'status', 'q'] as $key) {
            $filters[$key] = $this->request->query($key, '');
        }

        switch ($action) {
            case 'report_list':
                $rows = ClaimReport::rows($filters, null);
                return ['rows' => $rows, 'limited' => count($rows) >= ClaimReport::LIMIT, 'xlsx' => ClaimReport::canXlsx()];
            case 'report_export':
                $format = (string) $this->request->query('format', 'csv');
                $rows = ClaimReport::rows($filters, null, 100000);
                AuditTrail::record($this->auth, 'claim', null, 'report_exported', null, null, strtoupper($format) . ' ' . count($rows) . ' rows ' . json_encode(array_filter($filters)));
                ClaimReport::download($rows, $format, 'cora_claim_report' . ($filters['month'] !== '' ? '_' . $filters['month'] : ''));
        }
        $this->unknown();
    }
}
