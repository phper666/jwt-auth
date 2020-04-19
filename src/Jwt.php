<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-01
 * Time: 11:43
 */

namespace Phper666\JwtAuth;

use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Exception\JWTException;
use Phper666\JwtAuth\Exception\TokenValidException;
use Phper666\JwtAuth\Traits\CommonTrait;

/**
 * https://github.com/phper666/jwt-auth
 * @author LI Yuzhao <562405704@qq.com>
 * @method $this setSupportedAlgs(array $supportedAlgs)
 * @methid $this setPrefix($prefix)
 * @methid $this setTokenName($tokenName)
 * @methid $this setTokenPosition($tokenPosition)
 * @methid $this setSecret($secret)
 * @methid $this setKeys($keys)
 * @methid $this setTtl($ttl)
 * @methid $this setRefreshTtl($refreshTtl)
 * @methid $this setAlg($alg)
 * @methid $this setLoginType($loginType)
 * @methid $this setSsoKey($ssoKey)
 * @methid $this setCacheTTL($cacheTTL)
 * @method $this setGracePeriod($gracePeriod)
 * @method $this setEnalbed($enalbed)
 *
 */
class Jwt
{
    use CommonTrait;

    /**
     * @var Blacklist
     */
    protected $blacklist;

    public function __construct(Blacklist $blacklist)
    {
        $this->blacklist = $blacklist;
    }

    /**
     * 生成token
     * @param array $claims
     * @param bool $isInsertSsoBlack
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function generateToken(array $claims, $isInsertSsoBlack = true)
    {
        return $this->getToken($claims, $isInsertSsoBlack);
    }

    /**
     * 生成token
     * @param array $claims
     * @param bool $isInsertSsoBlack 是否把单点登录生成的token加入黑名单
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(array $claims, $isInsertSsoBlack = true)
    {
        if ($this->loginType == 'mpop') { // 多点登录
            $uniqid = uniqid();
        } else { // 单点登录
            if (empty($claims[$this->ssoKey])) {
                throw new JWTException("There is no {$this->ssoKey} key in the claims", 400);
            }
            $uniqid = $claims[$this->ssoKey];
        }

        $signer = new $this->supportedAlgs[$this->alg];
        $time = time();

        $builder = $this->getBuilder()
            ->identifiedBy($uniqid) // 设置jwt的jti
            ->issuedAt($time)// (iat claim) 发布时间
            ->canOnlyBeUsedAfter($time)// (nbf claim) 在此之前不可用
            ->expiresAt($time + $this->ttl);// (exp claim) 到期时间

        foreach ($claims as $k => $v) {
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }

        $token = $builder->getToken($signer, $this->getKey()); // Retrieves the generated token

        if ($this->loginType == 'sso' && $isInsertSsoBlack) { // 单点登录要把所有的以前生成的token都失效
            $this->blacklist->add($token);
        }

        return $token; // 返回的是token对象，使用强转换会自动转换成token字符串。Token对象采用了__toString魔术方法
    }

    public function isExpired($token = null)
    {
        return $this->getTokenObj($token)->isExpired();
    }

    public function canRefresh($token = null)
    {
        $refreshExp = $this->getTokenObj($token)->getClaim('refresh_exp', 0);

        return $refreshExp > time();
    }

    /**
     * 刷新token
     *
     * @param bool $withPrefix
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshToken($withPrefix = false)
    {
        if (!$this->retrieveToken($withPrefix)) {
            throw new JWTException('A token is required', 400);
        }
        $claims = $this->blacklist->add($this->getTokenObj());

        if (isset($claims['refresh_exp']) && isset($claims['exp']) && Arr::get($claims, 'auto_renew', 1)) {
            // auto_renew 表示自动延长refresh_exp，在refresh_exp有效期内允许给客户端换发新token
            $claims['refresh_exp'] = time() + Arr::get($claims, 'refresh_ttl', $this->refreshTtl);
        }

        if (isset($claims['iat'])) {
            unset($claims['iat']);
        }
        if (isset($claims['nbf'])) {
            unset($claims['nbf']);
        }
        if (isset($claims['exp'])) {
            unset($claims['exp']);
        }
        if (isset($claims['jti'])) {
            unset($claims['jti']);
        }

        $newToken = $this->generateToken($claims);

        return $withPrefix ? $this->prefix . ' ' . $newToken : $newToken;
    }

    /**
     * 让token失效
     *
     * @param string|null $token
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout(string $token = null)
    {
        if (!is_null($token) && $token !== '') {
            $token = $this->handleToken($token);
        } else {
            $token = $this->retrieveToken();
        }
        $this->blacklist->add($this->getTokenObj($token));

        return true;
    }

    /**
     * 验证token
     *
     * @param string $token
     * @param bool $validate
     * @param bool $verify
     * @return true
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    public function checkToken(string $token = null, $validate = true, $verify = true)
    {
        try {
            $token = $this->getTokenObj($token);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        if ($this->enalbed) {
            $claims = $this->claimsToArray($token->getClaims());
            // 验证token是否存在黑名单
            if ($this->blacklist->has($claims)) {
                throw new TokenValidException('Token authentication does not pass', 401);
            }
        }

        if ($validate && !$this->validateToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }
        if ($verify && !$this->verifyToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }

        return true;
    }

    /**
     * 获取Token对象
     *
     * @param string|null $token
     * @return Token
     */
    public function getTokenObj(string $token = null)
    {
        if (!is_null($token) && $token !== '') {
            return $this->getParser()->parse($token);
        }

        return $this->getParser()->parse($this->retrieveToken());
    }

    /**
     * 获取token的过期剩余时间，单位为s
     *
     * @param string|null $token
     * @return int|mixed
     */
    public function getTokenDynamicCacheTime(string $token = null)
    {
        $nowTime = time();
        $exp = $this->getTokenObj($token)->getClaim('exp', $nowTime);
        $expTime = $exp - $nowTime;

        return $expTime;
    }

    /**
     * 获取jwt token解析的data
     *
     * @param string|null $token
     * @return array
     */
    public function getParserData(string $token = null)
    {
        $arr = [];
        $claims = $this->getTokenObj($token)->getClaims();

        foreach ($claims as $k => $v) {
            $arr[$k] = $v->getValue();
        }

        return $arr;
    }

    public function getBlacklist()
    {
        return $this->blacklist;
    }

    /**
     * 同步jwt对象和jwt->blacklist对象的配置相关属性
     *
     * @param $method
     * @param $arguments
     * @return $this
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this, '_' . $method) && Str::startsWith($method, 'set')) {
            $realMethod = "_{$method}";
            $this->$realMethod(...$arguments);
            $this->blacklist->$method(...$arguments);

            return $this;
        }

        return $this;
    }
}
