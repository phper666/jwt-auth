<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2021/12/29
 * Time: 10:07 下午
 */

namespace Phper666\JWTAuth;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Plain;

abstract class AbstractJWT
{
    /**
     * 获取jwt token
     *
     * @param array $claims
     * @return Plain
     */
    abstract function getToken(string $scene, array $claims): Plain;

    /**
     * 对jwt token进行验证
     *
     * @param string $token
     * @return bool
     */
    abstract function verifyToken(string $token): bool;

    /**
     * 对jwt token进行验证
     *
     * @param string $token
     * @return bool
     */
    abstract function verifyTokenAndScene(string $scene, string $token): bool;

    /**
     * 检查当前路由是否需要做token校验
     *
     * @param string $scene
     * @param string $requestMethod
     * @param string $requestPath
     * @return bool
     */
    abstract function matchRoute(string $scene, string $requestMethod, string $requestPath): bool;

    /**
     * 获取jwt中的场景值
     *
     * @param string $token
     * @return bool
     */
    abstract function getSceneByToken(string $token): bool;

    /**
     * 刷新jwt token
     *
     * @param string $token
     * @return Plain
     */
    abstract function refreshToken(string $token): Plain;

    /**
     * 获取JWT token的claims部分
     *
     * @param string $token
     * @return array
     */
    abstract function getClaimsByToken(string $token): array;

    abstract function tokenToPlain(string $token): Plain;

    /**
     * 获取jwt的有效时间
     *
     * @param string $token
     * @return int
     */
    abstract function getTTL(string $token): int;

    /**
     * 获取jwt的剩余的有效时间
     *
     * @param string $token
     * @return int
     */
    abstract function getTokenDynamicCacheTime(string $token): int;

    /**
     * 使当前jwt失效
     *
     * @param string $token
     * @return bool
     */
    abstract function logout(string $token): bool;

    /**
     * token加入黑名单
     *
     * @param Token $token
     * @return bool
     */
    abstract function addTokenBlack(Plain $token): bool;

    /**
     * 黑名单是否存在当前token
     *
     * @param array $claims
     * @return bool
     */
    abstract function hasTokenBlack(Plain $token): bool;

    /**
     * @param array $sceneConfig
     * @param string $claimJti
     * @return string
     */
    abstract function getCacheKey(array $sceneConfig, string $claimJti): string;

    /**
     * Get the cache time limit.
     *
     * @return int
     */
    abstract function getCacheTTL(string $token = null): int;
}
