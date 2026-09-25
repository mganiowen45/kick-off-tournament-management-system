<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Totp;

final class MfaController
{
    public function setup(Request $request): never
    {
        $admin = Auth::requireAdmin(true);
        if ($admin['mfa_enabled']) {
            throw new HttpException('MFA is already enabled.', 400);
        }

        if ($request->method() === 'GET') {
            $secret = Totp::generateSecret();
            $_SESSION['mfa_setup_secret'] = $secret;
            $uri = Totp::getProvisioningUri($admin['email'], $secret);
            
            // Generate QR code using an external API for the frontend (in a real prod app we'd use a local library)
            // But we can just send the URI and let the frontend use a library or an API.
            Response::success([
                'secret' => $secret,
                'qr_uri' => $uri,
            ]);
        }

        $request->requireMethod('POST');
        $request->requireCsrf();
        
        $code = trim((string) $request->input('code', ''));
        $secret = $_SESSION['mfa_setup_secret'] ?? '';

        $limiter = new \App\Core\RateLimiter(Database::connection());
        $attemptKey = 'mfa_setup|' . $admin['id'] . '|' . $request->ip();
        $limiter->limit($attemptKey, 5, 15);

        if ($secret === '' || !Totp::verify($secret, $code)) {
            throw new HttpException('Invalid authentication code.', 422);
        }
        $limiter->clear($attemptKey);

        Database::connection()->prepare('UPDATE users SET mfa_secret = :secret, mfa_enabled = 1 WHERE id = :id')
            ->execute([':secret' => $secret, ':id' => $admin['id']]);

        unset($_SESSION['mfa_setup_secret']);
        $_SESSION['mfa_verified'] = true;

        Response::success(['redirect' => 'admin_dashboard.html'], 'MFA enabled successfully.');
    }

    public function verify(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $admin = Auth::requireAdmin(true);

        if (!$admin['mfa_enabled']) {
            throw new HttpException('MFA not enabled.', 400);
        }

        $code = trim((string) $request->input('code', ''));
        
        $limiter = new \App\Core\RateLimiter(Database::connection());
        $attemptKey = 'mfa|' . $admin['id'] . '|' . $request->ip();
        $limiter->limit($attemptKey, 5, 15);

        if (!Totp::verify((string) $admin['mfa_secret'], $code)) {
            throw new HttpException('Invalid authentication code.', 422);
        }
        
        $limiter->clear($attemptKey);

        $_SESSION['mfa_verified'] = true;
        Response::success(['redirect' => 'admin_dashboard.html'], 'MFA verified.');
    }
}

