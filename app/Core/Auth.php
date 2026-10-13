<?php

declare(strict_types=1);

namespace Cora\Core;

/**
 * The logged-in staff member and their CORA role tier (staff.cora, see Role):
 * 1 = Super Admin, 2 = Corporate Admin, 3 = Outlet PIC, 4 = Outlet User.
 *
 * Outlet roles act for the outlet(s) on their odb account (staff.outlet, a
 * comma-separated list of outlet ids). The dev role override
 * (dev-switch-role.php, localhost only) replaces the tier for UI testing but
 * never grants the real Super Admin flag.
 */
final class Auth
{
    /**
     * @param list<int> $outletIds
     */
    public function __construct(
        public readonly ?int $staffId,
        public readonly ?string $staffName,
        public readonly int $coraFlag,
        public readonly int $permission,
        public readonly bool $isSuperadmin,
        public readonly array $outletIds,
    ) {
    }

    public static function fromSession(Database $db): self
    {
        $staffId = null;
        $name = null;
        $flag = 0;
        $outlets = [];

        $username = $_SESSION['myusername'] ?? null;
        if (is_string($username) && $username !== '') {
            $row = $db->first(
                'SELECT `id`, `nama_staff`, `cora`, `outlet` FROM `staff` WHERE `username` = ? AND `recycle` != 1 LIMIT 1',
                [$username]
            );
            if ($row !== null) {
                $staffId = (int) $row['id'];
                $name = (string) $row['nama_staff'];
                $flag = (int) ($row['cora'] ?? 0);
                $outlets = self::parseOutlets((string) ($row['outlet'] ?? ''));
            }
        }

        $override = self::isLocal() && isset($_SESSION['cora_dev_role_override'])
            ? (int) $_SESSION['cora_dev_role_override']
            : null;

        return new self($staffId, $name, $flag, $override ?? $flag, $override === null && $flag === Role::SuperAdmin->value, $outlets);
    }

    /** @return list<int> */
    public static function parseOutlets(string $csv): array
    {
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv)), 'strlen'));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    public function isLoggedIn(): bool
    {
        return $this->staffId !== null;
    }

    public function role(): ?Role
    {
        return $this->isSuperadmin ? Role::SuperAdmin : Role::tryFrom($this->permission);
    }

    /** Any CORA tier (1-4) or a real Super Admin. */
    public function hasAccess(): bool
    {
        return $this->role() !== null;
    }

    /** Super Admin: company configuration, limits, rules, user access, audit. */
    public function isSuper(): bool
    {
        return $this->role() === Role::SuperAdmin;
    }

    /** Super Admin or Corporate Admin: employee upload, claim review, reports. */
    public function isAdmin(): bool
    {
        return in_array($this->role(), [Role::SuperAdmin, Role::CorporateAdmin], true);
    }

    /** Outlet PIC or Outlet User: eligibility search and claim submission. */
    public function isOutlet(): bool
    {
        return in_array($this->role(), [Role::OutletPic, Role::OutletUser], true);
    }

    /** Outlet PIC (or any admin): may amend / cancel outlet submissions. */
    public function canAmend(): bool
    {
        return $this->isAdmin() || $this->role() === Role::OutletPic;
    }

    public function canSeeOutlet(int $outletId): bool
    {
        return $this->isAdmin() || in_array($outletId, $this->outletIds, true);
    }

    public static function isLocal(): bool
    {
        $server = (string) ($_SERVER['SERVER_NAME'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        return in_array($server, ['localhost', '127.0.0.1'], true)
            || str_contains($server, 'localhost')
            || str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1');
    }
}
