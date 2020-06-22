<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-06
 * Time: 16:02
 */

namespace Phper666\JwtAuth;
use Hyperf\Di\Annotation\Inject;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Helper\Utils;
use Psr\SimpleCache\CacheInterface;

/**
 * https://github.com/phper666/jwt-auth
 * author LI Yuzhao <562405704@qq.com>
 */
class Blacklist
{
    /**
     * @Inject()
     * @var CacheInterface
     */
    public $storage;

    /**
     * @param Token $token
     * @param array $config
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function add(Token $token, array $config)
    {
        $claims = Utils::claimsToArray($token->getClaims());
        $jti = $claims['jti'];
        if ($config['blacklist_enabled']) {
            $this->storage->set(
                $jti,
                ['valid_until' => $this->getGraceTimestamp($config)],
                $this->getSecondsUntilExpired($claims, $config)
            );
        }

        return $claims;
    }

    /**
     * @param       $claims
     * @param array $config
     * @return int
     */
    protected function getSecondsUntilExpired($claims, array $config)
    {
        $exp = Utils::timestamp($claims['exp']);
        $iat = Utils::timestamp($claims['iat']);

        // get the latter of the two expiration dates and find
        // the number of minutes until the expiration date,
        // plus 1 minute to avoid overlap
        return $exp->max($iat->addSeconds($config['blacklist_cache_ttl']))->addSecond()->diffInSeconds();
    }

    /**
     * Get the timestamp when the blacklist comes into effect
     * This defaults to immediate (0 seconds).
     *
     * @return int
     */
    protected function getGraceTimestamp(array $config)
    {
        if ($config['login_type'] == 'sso') $config['blacklist_grace_period'] = 0;
        return Utils::now()->addSeconds($config['blacklist_grace_period'])->getTimestamp();
    }

    /**
     * 判断token是否已经加入黑名单
     * @param $claims
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($claims, array $config)
    {
        if ($config['blacklist_enabled'] && $config['login_type'] == 'mpop') {
            $val = $this->storage->get($claims['jti']);
            // check whether the expiry + grace has past
            return !empty($val) && !Utils::isFuture($val['valid_until']);
        }

        if ($config['blacklist_enabled'] && $config['login_type'] == 'sso') {
            $val = $this->storage->get($claims['jti']);
            // 这里为什么要大于等于0，因为在刷新token时，缓存时间跟签发时间可能一致，详细请看刷新token方法
            $isFuture = ($claims['iat'] - $val['valid_until']) >= 0;
            // check whether the expiry + grace has past
            return !empty($val) && !$isFuture;
        }

        return false;
    }

    /**
     * 黑名单移除token
     * @param $jwtJti
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function remove($jwtJti)
    {
        return $this->storage->delete($jwtJti);
    }

    /**
     * 移除所有的缓存，注意：这样会把系统所有的缓存都清掉 todo
     * @return bool
     */
    public function clear()
    {
        $this->storage->clear();

        return true;
    }
}
