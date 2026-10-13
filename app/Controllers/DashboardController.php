<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Services\CompanyDashboard;
use Cora\Services\Notifications;

/**
 * Dashboard (dashboard/) and the navbar notification centre. Actions:
 *   notifications        counters for the signed-in role (any role)
 *   company_dashboard    ?month=YYYY-MM &company_id= utilisation per company (admin)
 */
final class DashboardController extends Controller
{
    protected function handle(string $action): mixed
    {
        return match ($action) {
            'notifications' => Notifications::forUser($this->auth),
            'company_dashboard' => $this->companyDashboard(),
            default => $this->unknown(),
        };
    }

    private function companyDashboard(): array
    {
        $this->requireAdmin();
        $month = (string) $this->request->query('month', date('Y-m'));
        $companyId = (int) $this->request->query('company_id', 0);

        return CompanyDashboard::month($month, $companyId > 0 ? $companyId : null);
    }
}
