<?php
declare(strict_types=1);
namespace Phper666\JWTAuth;

use Lcobucci\JWT\Token;
use Phper666\JWTAuth\Util\JWTUtil;
use Phper666\JWTAuth\Util\TimeUtil;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * https://github.com/phper666/jwt-auth
 * author LI Yuzhao <562405704@qq.com>
 */
class BlackList extends AbstractJWT
{
    /**
     * @var CacheInterface
     */
    public $cache;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->cache = $this->getContainer()->get(CacheInterface::class);
    }

    /**
     * 把token加入到黑名单中
     * @param Token $token
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function addTokenBlack(Token $token, array $config = [], $ssoSelfExp = false)
    {
        $claims = JWTUtil::claimsToArray($token->getClaims());
        if ($ssoSelfExp) $claims['iat'] += 1; // 如果是当点登录，并且调用了logout方法
        if ($config['blacklist_enabled']) {
            $cacheKey = $this->getCacheKey($claims['jti']);
            $this->cache->set(
                $cacheKey,
                ['valid_until' => $this->getGraceTimestamp($claims, $config)],
                $this->getSecondsUntilExpired($claims, $config)
            );
        }
        return $claims;
    }

    /**
     * Get the number of seconds until the token expiry.
     *
     * @return int
     */
    protected function getSecondsUntilExpired($claims, array $config)
    {
        $exp = TimeUtil::timestamp($claims['exp']);
        $iat = TimeUtil::timestamp($claims['iat']);

        // get the latter of the two expiration dates and find
        // the number of minutes until the expiration date,
        // plus 1 minute to avoid overlap
        return $exp->max($iat->addSeconds($config['blacklist_cache_ttl']))->diffInSeconds();
    }

    /**
     * Get the timestamp when the blacklist comes into effect
     * This defaults to immediate (0 seconds).
     *
     * @return int
     */
    protected function getGraceTimestamp($claims, array $config)
    {
        $loginType = $config['login_type'];
        $gracePeriod = $config['blacklist_grace_period'];
        if ($loginType == 'sso') $gracePeriod = 0;
        return TimeUtil::timestamp($claims['iat'])->addSeconds($gracePeriod)->getTimestamp();
    }

    /**
     * 判断token是否已经加入黑名单
     * @param $claims
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function hasTokenBlack($claims, array $config = [])
    {
        $cacheKey = $this->getCacheKey($claims['jti']);
        if ($config['blacklist_enabled'] && $config['login_type'] == 'mpop') {
            $val = $this->cache->get($cacheKey);
            // check whether the expiry + grace has past
            return !empty($val) && !TimeUtil::isFuture($val['valid_until']);
        }

        if ($config['blacklist_enabled'] && $config['login_type'] == 'sso') {
            $val = $this->cache->get($cacheKey);
            // 这里为什么要大于等于0，因为在刷新token时，缓存时间跟签发时间可能一致，详细请看刷新token方法
            $isFuture = ($claims['iat'] - $val['valid_until']) >= 0;
            // check whether the expiry + grace has past
            return !empty($val) && !$isFuture;
        }

        return false;
    }

    /**
     * 黑名单移除token
     * @param $key  token 中的jit
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function remove($key)
    {
        return $this->cache->delete($key);
    }

    /**
     * 移除所有的token缓存
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clear()
    {
        $cachePrefix = $this->getSceneConfig($this->getScene())['blacklist_prefix'];
        return $this->cache->delete("{$cachePrefix}.*");
    }

    /**
     * @param string $jti
     * @return string
     */
    private function getCacheKey(string $jti)
    {
        $config = $this->getSceneConfig($this->getScene());
        return "{$config['blacklist_prefix']}_" . $jti;
    }

    /**
     * Get the cache time limit.
     *
     * @return int
     */
    public function getCacheTTL()
    {
        return $this->getSceneConfig($this->getScene())['ttl'];
    }
}
