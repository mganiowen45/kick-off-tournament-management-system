<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService(Database::connection());
    }

    public function csrf(Request $request): never
    {
        $request->requireMethod('GET');
        Response::success(['csrf_token' => Csrf::token()]);
    }

    public function register(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = $this->service->register($request->data());
        Response::success([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => $user['avatar_url'],
            'redirect' => 'profile_setup.html' . ($this->safeReturnTo((string) $request->input('return_to', '')) !== ''
                ? '?return_to=' . rawurlencode($this->safeReturnTo((string) $request->input('return_to', '')))
                : ''),
            'csrf_token' => Csrf::token(),
        ], 'Account created successfully.', 201);
    }

    public function login(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = $this->service->login($request->data(), $request->ip());
        Response::success([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'avatar_url' => $user['avatar_url'],
            'redirect' => $this->loginRedirect($user, $this->safeReturnTo((string) $request->input('return_to', ''))),
            'csrf_token' => Csrf::token(),
        ], 'Login successful.');
    }

    public function logout(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        Auth::logout();
        Response::success(['redirect' => 'login.html'], 'Logged out successfully.');
    }

    public function checkUsername(Request $request): never
    {
        $request->requireMethod('GET');
        $username = trim((string) $request->query('username', ''));
        $available = $this->service->usernameAvailable($username);
        Response::success([
            'available' => $available,
            'message' => $available ? 'Username available' : 'Username taken',
        ]);
    }

    private function safeReturnTo(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 300) return '';
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || str_contains($value, '//') || str_contains($value, '\\')) return '';
        if (!preg_match('/^[A-Za-z0-9_.-]+\.html(?:\?[A-Za-z0-9%_.~=&+-]*)?$/', $value)) return '';
        if (str_starts_with($value, 'admin_')) return '';
        return $value;
    }

    private function loginRedirect(array $user, string $returnTo): string
    {
        if (($user['role'] ?? '') === 'admin') {
            if (empty($user['mfa_enabled'])) return 'admin_mfa_setup.html';
            if (empty($_SESSION['mfa_verified'])) return 'admin_mfa_verify.html';
            return 'admin_dashboard.html';
        }
        if ((int) ($user['profile_setup_completed'] ?? 0) !== 1) {
            return 'profile_setup.html' . ($returnTo !== '' ? '?return_to=' . rawurlencode($returnTo) : '');
        }
        return $returnTo !== '' ? $returnTo : 'dashboard.html';
    }
}
