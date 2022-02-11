<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2021/12/1
 * Time: 1:34 下午
 */
namespace Phper666\JWTAuth;

use Lcobucci\JWT\Token\Plain;

/**
 * Interface JWTTokenInterface
 * @package Phper666\JWTAuth
 */
interface JWTTokenInterface
{
    /**
     * 获取jwt token
     *
     * @param array $claims
     * @return Plain
     */
    public function getToken(string $scene, array $claims): Plain;

    /**
     * 对jwt token进行验证
     *
     * @param string $token
     * @return bool
     */
    public function verifyToken(string $token): bool;

    /**
     * 刷新jwt token
     *
     * @param string $token
     * @return Plain
     */
    public function refreshToken(string $token): Plain;

    /**
     * 获取JWT token的claims部分
     *
     * @param string $token
     * @return array
     */
    public function getClaimsByToken(string $token): array;

    public function tokenToPlain(string $token): Plain;

    /**
     * 获取jwt的有效时间
     *
     * @param string $token
     * @return int
     */
    public function getTTL(string $token): int;

    /**
     * 获取jwt的剩余的有效时间
     *
     * @param string $token
     * @return int
     */
    public function getTokenDynamicCacheTime(string $token): int;

    /**
     * 使当前jwt失效
     *
     * @param string $token
     * @return bool
     */
    public function logout(string $token): bool;
}
