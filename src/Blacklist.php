<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-06
 * Time: 16:02
 */

namespace Phper666\JwtAuth;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Helper\Utils;
use Phper666\JwtAuth\Traits\CommonTrait;

/**
 * https://github.com/phper666/jwt-auth
 * author LI Yuzhao <562405704@qq.com>
 */
class Blacklist extends AbstractAnnotation
{
    use CommonTrait;

    /**
     * 把token加入到黑名单中
     * @param Token $token
     * @return Claim[] $claims
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function add(Token $token)
    {
        $claims = $this->claimsToArray($token->getClaims());
        if ($this->enalbed && $claims['jti']) {
            $jti = $claims['jti'];
            $this->storage->set(
                $jti,
                ['valid_until' => $this->getGraceTimestamp()],
                $this->getSecondsUntilExpired($claims)
            );
        }

        return $claims;
    }

    /**
     * Get the number of seconds until the token expiry.
     *
     * @param $claims
     * @return int
     */
    protected function getSecondsUntilExpired($claims)
    {
        $exp = Utils::timestamp(Arr::get($claims, 'exp', 0));
        $iat = Utils::timestamp(Arr::get($claims, 'iat', 0));

        // get the latter of the two expiration dates and find
        // the number of minutes until the expiration date,
        // plus 1 minute to avoid overlap
        return $exp->max($iat->addSeconds($this->cacheTTL))->addSecond()->diffInSeconds();
    }

    /**
     * Get the timestamp when the blacklist comes into effect
     * This defaults to immediate (0 seconds).
     *
     * @return int
     */
    protected function getGraceTimestamp()
    {
        if ($this->loginType == 'sso') $this->gracePeriod = 0;

        return Utils::now()->addSeconds($this->gracePeriod)->getTimestamp();
    }

    /**
     * 判断token是否已经加入黑名单
     * @param $claims
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($claims)
    {
        if ($this->enalbed && isset($claims['jti']) && $this->loginType == 'mpop') {
            $val = $this->storage->get($claims['jti']);
            // check whether the expiry + grace has past
            return !empty($val) && !Utils::isFuture($val['valid_until']);
        }

        if ($this->enalbed && isset($claims['jti']) && $this->loginType == 'sso') {
            $val = $this->storage->get($claims['jti']);
            // 这里为什么要大于等于0，因为在刷新token时，缓存时间跟签发时间可能一致，详细请看刷新token方法
            $isFuture = (Arr::get($claims, 'iat', 0) - $val['valid_until']) >= 0;
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

    /**
     * Set the cache time limit.
     *
     * @param  int  $ttl
     *
     * @return $this
     */
    public function setCacheTTL($ttl)
    {
        $this->cacheTTL = (int) $ttl;

        return $this;
    }

    /**
     * Get the cache time limit.
     *
     * @return int
     */
    public function getCacheTTL()
    {
        return $this->cacheTTL;
    }

    public function __call($method, $arguments)
    {
        if (method_exists($this, '_' . $method) && Str::startsWith($method, 'set')) {
            $realMethod = "_{$method}";
            $this->$realMethod(...$arguments);

            return $this;
        }

        return $this;
    }
}
