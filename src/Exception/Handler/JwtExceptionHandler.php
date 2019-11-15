<?php
declare(strict_types=1);

/**
 * https://github.com/phper666/jwt-auth
 * @author Qyq <18339795172@163.com>
 */

namespace Phper666\JwtAuth\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Phper666\JwtAuth\Exception\JWTException;
use Phper666\JwtAuth\Exception\TokenValidException;
use Psr\Http\Message\ResponseInterface;
use Throwable;


class JwtExceptionHandler extends ExceptionHandler
{

    /**
     * Handle the exception, and return the specified result.
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();
        /** @var \Hyperf\Validation\ValidationException $throwable */
        $error = $throwable->getMessage();

        // 重写返回数据信息
        $body  = json_encode([
            'err_code' => 40001,
            'err_msg'  => $error
        ]);

        return $response->withStatus($throwable->status)->withBody(new SwooleStream($body));
    }

    /**
     * Determine if the current exception handler should handle the exception,.
     *
     * @return bool
     *              If return true, then this exception handler will handle the exception,
     *              If return false, then delegate to next handler
     */
    public function isValid(Throwable $throwable): bool
    {
        if ($throwable instanceof JWTException
        || $throwable instanceof  TokenValidException)
        {
            return true;
        }

        return false;
    }
}