<?php

declare(strict_types=1);

namespace Cora\Core;

use mysqli_sql_exception;

/**
 * Base controller for a module folder's api.php:
 *
 *   require __DIR__ . '/../app/bootstrap.php';
 *   \Cora\Controllers\ClaimController::run();
 *
 * run() resolves the logged-in staff, gates on CORA access, calls handle()
 * with the requested action and writes the JSON response:
 *   handle() returns data     -> { success: true, data }
 *   handle() returns null     -> { success: true }
 *   ValidationException       -> 422 { success: false, message, errors }
 *   HttpException             -> its status { success: false, message }
 * A handler with a different response shape (file download) writes and exits
 * itself.
 */
abstract class Controller
{
    protected Validator $validator;

    final public function __construct(
        protected Request $request,
        protected Auth $auth,
        protected Database $db,
    ) {
        $this->validator = new Validator($db);
    }

    /**
     * @return mixed data for the success response
     * @throws HttpException for an unknown action or a refused request
     */
    abstract protected function handle(string $action): mixed;

    public static function run(): never
    {
        $db = Database::get();
        $auth = Auth::fromSession($db);

        if (!$auth->isLoggedIn()) {
            Response::json(['success' => false, 'message' => 'Staff ID is required. Please ensure you are logged in.'], 401);
        }
        if (!$auth->hasAccess()) {
            Response::json(['success' => false, 'message' => 'You do not have access to the CORA module.'], 403);
        }

        try {
            $controller = new static(new Request(), $auth, $db);
            $data = $controller->handle((string) $controller->request->action);
            Response::json($data === null ? ['success' => true] : ['success' => true, 'data' => $data]);
        } catch (ValidationException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (HttpException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], $e->status());
        } catch (mysqli_sql_exception $e) {
            error_log('[CORA] ' . static::class . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Database error. Please try again.'], 500);
        }
    }

    protected function unknown(): never
    {
        throw new HttpException('Unknown action', 400);
    }

    protected function requireId(string $label = 'Record'): int
    {
        $id = $this->request->id();
        if ($id <= 0) {
            throw new HttpException($label . ' id is required', 422);
        }

        return $id;
    }

    protected function requireAdmin(): void
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException('Only Corporate Admins and Super Admins can do this.', 403);
        }
    }

    protected function requireSuper(): void
    {
        if (!$this->auth->isSuper()) {
            throw new HttpException('Only Super Admins can do this.', 403);
        }
    }

    /** created_by / created_by_name style stamp for new rows. */
    protected function stamp(string $prefix = 'created_by'): array
    {
        return [$prefix => $this->auth->staffId, $prefix . '_name' => $this->auth->staffName];
    }
}
