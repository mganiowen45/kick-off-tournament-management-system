<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Api
{
    public static function run(callable $handler): never
    {
        try {
            $result = $handler(new Request());
            if (is_array($result)) {
                Response::success($result);
            }
            Response::success();
        } catch (HttpException $exception) {
            Response::error($exception->getMessage(), $exception->status(), $exception->data());
        } catch (Throwable $exception) {
            error_log('[KICKOFF API] ' . $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            Response::error('The server could not complete this request.', 500);
        }
    }

    private function __construct()
    {
    }
}
