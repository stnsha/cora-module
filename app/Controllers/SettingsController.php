<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\HttpException;
use Cora\Core\Role;
use Cora\Core\ValidationException;
use Cora\Models\Company;
use Cora\Models\Setting;
use Cora\Services\AuditTrail;

/**
 * Settings (settings/) - Super Admin. Actions:
 *   settings_get     Default Alpro rules
 *   settings_save    { default_limit, default_limit_mode, default_benefit_criteria, claim_types: [..] }
 *   users_list       staff with a CORA role (cora 1-4)
 *   users_search     ?q= active staff by name (20), for "Add access"
 *   users_save       { id, cora 1-4 } grant or change a role
 *   users_delete     { id } remove access (cora = 0)
 * Nobody can change or remove their own role. users_* need the real Super
 * Admin flag (never a dev-simulated role).
 */
final class SettingsController extends Controller
{
    protected function handle(string $action): mixed
    {
        if (str_starts_with($action, 'users_')) {
            if (!$this->auth->isSuperadmin) {
                throw new HttpException('Only a Super Admin can manage user access.', 403);
            }
            return $this->users($action);
        }

        $this->requireSuper();
        switch ($action) {
            case 'settings_get':
                return Setting::asArray();
            case 'settings_save':
                return $this->saveSettings();
        }
        $this->unknown();
    }

    private function saveSettings(): array
    {
        $data = $this->validator->validate($this->request->only(['default_limit', 'default_limit_mode', 'default_benefit_criteria', 'claim_types']), [
            'default_limit'            => ['required', 'numeric', 'min:0'],
            'default_limit_mode'       => ['required', 'integer', 'in:' . implode(',', array_keys(Company::LIMIT_MODES))],
            'default_benefit_criteria' => ['nullable', 'string', 'max:5000'],
            'claim_types'              => ['required', 'array'],
        ]);
        $types = array_values(array_unique(array_filter(array_map(
            static fn ($t): string => mb_substr(trim((string) $t), 0, 50),
            $data['claim_types']
        ), 'strlen')));
        if ($types === []) {
            throw ValidationException::single('claim_types', 'Keep at least one claim type.');
        }

        $before = Setting::asArray();
        $values = [
            'default_limit'            => number_format((float) $data['default_limit'], 2, '.', ''),
            'default_limit_mode'       => (string) $data['default_limit_mode'],
            'default_benefit_criteria' => $data['default_benefit_criteria'],
            'claim_types'              => json_encode($types, JSON_UNESCAPED_UNICODE),
        ];
        $this->db->transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                Setting::put($key, $value, $this->auth->staffId, $this->auth->staffName);
            }
        });
        $after = Setting::asArray();
        AuditTrail::record($this->auth, 'setting', null, 'updated', $before, $after);

        return $after;
    }

    private function users(string $action): mixed
    {
        switch ($action) {
            case 'users_list':
                return $this->staffRows('s.`cora` > 0', [], 's.`cora` ASC, s.`nama_staff` ASC', null);
            case 'users_search':
                $q = trim((string) $this->request->query('q', ''));
                if (mb_strlen($q) < 2) {
                    return [];
                }
                $like = '%' . addcslashes($q, '\\%_') . '%';
                return $this->staffRows('(s.`nama_staff` LIKE ? OR s.`username` LIKE ?)', [$like, $like], 's.`nama_staff` ASC', 20);
            case 'users_save':
                $id = $this->requireId('Staff');
                $role = Role::tryFrom((int) $this->request->get('cora', 0));
                if ($role === null) {
                    throw ValidationException::single('cora', 'Choose a role.');
                }
                $before = $this->guardStaff($id);
                $this->db->execute('UPDATE `staff` SET `cora` = ? WHERE `id` = ? AND `recycle` != 1', [$role->value, $id]);
                $after = $this->staffRows('s.`id` = ?', [$id], 's.`id`', 1)[0];
                AuditTrail::record($this->auth, 'user', $id, 'role_changed', ['cora' => $before['cora']], ['cora' => $after['cora']], $after['nama_staff']);
                return $after;
            case 'users_delete':
                $id = $this->requireId('Staff');
                $before = $this->guardStaff($id);
                $this->db->execute('UPDATE `staff` SET `cora` = 0 WHERE `id` = ?', [$id]);
                AuditTrail::record($this->auth, 'user', $id, 'access_removed', ['cora' => $before['cora']], ['cora' => 0], $before['nama_staff']);
                return ['id' => $id, 'cora' => 0];
        }
        $this->unknown();
    }

    /** @return array<string, mixed> the staff row; refuses self and inactive staff */
    private function guardStaff(int $id): array
    {
        if ($id === $this->auth->staffId) {
            throw new HttpException('You cannot change your own access - ask another Super Admin.', 422);
        }
        $row = $this->staffRows('s.`id` = ?', [$id], 's.`id`', 1)[0] ?? null;
        if ($row === null) {
            throw new HttpException('Staff member not found or no longer active.', 404);
        }

        return $row;
    }

    /**
     * Active staff (recycle != 1) with department, outlet(s) and CORA role.
     *
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function staffRows(string $where, array $params, string $order, ?int $limit): array
    {
        $rows = $this->db->select(
            'SELECT s.`id`, s.`nama_staff`, s.`username`, s.`cora`, s.`status_semasa`, s.`outlet`, d.`depart_name`
               FROM `staff` s
               LEFT JOIN `staff_department` d ON d.`id` = s.`department`
              WHERE s.`recycle` != 1 AND ' . $where . '
              ORDER BY ' . $order . ($limit !== null ? ' LIMIT ' . $limit : ''),
            $params
        );

        return array_map(static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'nama_staff' => (string) $r['nama_staff'],
            'username'   => (string) $r['username'],
            'cora'       => (int) $r['cora'],
            'status'     => $r['status_semasa'],
            'department' => $r['depart_name'],
            'outlets'    => count(array_filter(explode(',', (string) $r['outlet']), static fn (string $v): bool => (int) $v > 0)),
        ], $rows);
    }
}
