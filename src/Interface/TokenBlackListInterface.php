<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2020/4/21
 * Time: 9:17 下午
 */
namespace Phper666\JWTAuth\Interface;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Plain;

/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2021/12/08
 * Time: 1:36 下午
 */
interface TokenBlackListInterface
{
    /**
     * token加入黑名单
     *
     * @param Token $token
     * @return bool
     */
    public function addTokenBlack(Plain $token): bool;

    /**
     * 黑名单是否存在当前token
     *
     * @param array $claims
     * @return bool
     */
    public function hasTokenBlack(Plain $token): bool;

    /**
     * @param array $sceneConfig
     * @param string $claimJti
     * @return string
     */
    public function getCacheKey(array $sceneConfig, string $claimJti): string;

    /**
     * Get the cache time limit.
     *
     * @return int
     */
    public function getCacheTTL(string $token = null): int;
}
