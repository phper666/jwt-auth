<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-01
 * Time: 22:32
 */
namespace Phper666\JwtAuth\Middleware;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;
use Library\Utils\Exception\JwtTokenException;
use Phper666\JwtAuth\Exception\JWTException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Phper666\JwtAuth\Jwt;
use Phper666\JwtAuth\Exception\TokenValidException;

class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var HttpResponse
     */
    protected $response;

    protected $jwt;

    public function __construct(HttpResponse $response, Jwt $jwt)
    {
        $this->response = $response;
        $this->jwt = $jwt;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $token = $this->jwt->retrieveToken();
            if ($this->jwt->checkToken($token, false)) {
                // validate会对exp进行校验，过期抛异常，此处忽略校验（也可以通过重写getParser()修改行为）
                if ($this->jwt->isExpired()) {
                    if (!$this->jwt->canRefresh()) {
                        throw new JWTException('Token is expired!', 403);
                    }

                    $token = $this->jwt->refreshToken(true);
                } else {
                    $token = $this->jwt->refreshToken(true);
                }
                Context::set('jwtToken', $token);
            }
        } catch (TokenValidException | JWTException  $e) {
            // 无效token的处理，example
            return $this->response->json([
                'status' => 0,
                'msg' => 'token is invalid!',
            ]);
        }

        return $handler->handle($request);
    }
}
