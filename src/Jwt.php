<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-01
 * Time: 11:43
 */

namespace Phper666\JwtAuth;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Exception\TokenValidException;
use Phper666\JwtAuth\Exception\JWTException;
use Phper666\JwtAuth\Helper\Utils;
use Phper666\JwtAuth\Traits\CommonTrait;
use Psr\SimpleCache\CacheInterface;

/**
 * https://github.com/phper666/jwt-auth
 * @author LI Yuzhao <562405704@qq.com>
 */
class Jwt
{
    use CommonTrait;

    /**
     * @var array Supported algorithms
     */
    public $supportedAlgs = [
        'HS256' => 'Lcobucci\JWT\Signer\Hmac\Sha256',
        'HS384' => 'Lcobucci\JWT\Signer\Hmac\Sha384',
        'HS512' => 'Lcobucci\JWT\Signer\Hmac\Sha512',
        'ES256' => 'Lcobucci\JWT\Signer\Ecdsa\Sha256',
        'ES384' => 'Lcobucci\JWT\Signer\Ecdsa\Sha384',
        'ES512' => 'Lcobucci\JWT\Signer\Ecdsa\Sha512',
        'RS256' => 'Lcobucci\JWT\Signer\Rsa\Sha256',
        'RS384' => 'Lcobucci\JWT\Signer\Rsa\Sha384',
        'RS512' => 'Lcobucci\JWT\Signer\Rsa\Sha512',
    ];

    // 对称算法名称
    public $symmetryAlgs = [
        'HS256',
        'HS384',
        'HS512'
    ];

    // 非对称算法名称
    public $asymmetricAlgs = [
        'RS256',
        'RS384',
        'RS512',
        'ES256',
        'ES384',
        'ES512',
    ];

    public $prefix = 'Bearer';

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var CacheInterface
     */
    public $storage;

    /**
     * @var Blacklist
     */
    protected $blacklist;

    /**
     * @var ContainerInterface
     */
    private $container;

    // jwt配置
    private $jwtConfig;

    /**
     * jwt配置前缀
     * @var string
     */
    private $configPrefix = 'jwt';

    public function __construct(ContainerInterface $container, Blacklist $blacklist)
    {
        $this->container = $container;
        $this->storage = $this->container->get(CacheInterface::class);
        $this->request = $this->container->get(RequestInterface::class);
        $config = $this->container->get(ConfigInterface::class);

        $this->jwtConfig = $config->get($this->configPrefix);
        $this->blacklist = $blacklist;
    }

    /**
     * 生成token
     * @param array $claims
     * @param bool $isInsertSsoBlack 是否把单点登录生成的token加入黑名单
     * @param bool  $isConversionString
     * @return Token|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(array $claims, $isInsertSsoBlack = true, $isConversionString = true)
    {
        if ($this->jwtConfig['login_type'] == 'mpop') { // 多点登录
            $uniqid = uniqid();
        } else { // 单点登录
            if (empty($claims[$this->jwtConfig['sso_key']])) {
                throw new JWTException("There is no {$this->jwtConfig['sso_key']} key in the claims", 400);
            }
            $uniqid = $claims[$this->jwtConfig['sso_key']];
        }

        $signer = new $this->supportedAlgs[$this->jwtConfig['alg']];
        $time = time();

        $builder = $this->getBuilder()
            ->identifiedBy($uniqid) // 设置jwt的jti
            ->issuedAt($time)// (iat claim) 发布时间
            ->canOnlyBeUsedAfter($time)// (nbf claim) 在此之前不可用
            ->expiresAt($time + $this->jwtConfig['ttl']);// (exp claim) 到期时间

        foreach ($claims as $k => $v) {
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }

        $token = $builder->getToken($signer, $this->getKey()); // Retrieves the generated token

        if ($this->jwtConfig['login_type'] == 'sso' && $isInsertSsoBlack) { // 单点登录要把所有的以前生成的token都失效
            $this->blacklist->add($token, $this->jwtConfig);
        }

        return $isConversionString ? (string)$token : $token; // 返回的是token对象，使用强转换会自动转换成token字符串。Token对象采用了__toString魔术方法
    }

    /**
     * 刷新token
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshToken()
    {
        if (!$this->getHeaderToken()) {
            throw new JWTException('A token is required', 400);
        }
        $claims = $this->blacklist->add($this->getTokenObj(), $this->jwtConfig);
        unset($claims['iat']);
        unset($claims['nbf']);
        unset($claims['exp']);
        unset($claims['jti']);
        return $this->getToken($claims);
    }

    /**
     * 让token失效
     * @param string|null $token
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout(string $token = null)
    {
        if (!is_null($token) && $token !== '') {
            $token = Utils::handleHeaderToken($this->prefix, $token);
        } else {
            $token = $this->getHeaderToken();
        }
        $this->blacklist->add($this->getTokenObj($token), $this->jwtConfig);
        return true;
    }

    /**
     * 验证token
     * @param string|null $token
     * @param bool        $validate
     * @param bool        $verify
     * @return bool
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
        if ($this->jwtConfig['blacklist_enabled']) {
            $claims = Utils::claimsToArray($token->getClaims());
            // 验证token是否存在黑名单
            if ($this->blacklist->has($claims, $this->jwtConfig)) {
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
     * @param string|null $token
     * @return Token
     */
    public function getTokenObj(string $token = null)
    {
        if (!is_null($token) && $token !== '') {
            return $this->getParser()->parse($token);
        }
        return $this->getParser()->parse($this->getHeaderToken());
    }

    /**
     * 获取token的过期剩余时间，单位为s
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
     * 获取jwt token解析的dataç
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

    /**
     * 获取http头部token
     * @param $token
     * @param int $dynamicCacheTime
     * @return string|null
     */
    public function getHeaderToken()
    {
        $token = $this->request->getHeader('Authorization')[0] ?? '';
        $token = Utils::handleHeaderToken($this->prefix, $token);
        if ($token !== false) {
            return $token;
        }

        throw new JWTException('A token is required', 400);
    }

    /**
     * 获取对应算法需要的key
     * @param string $type 配置keys里面的键，获取私钥或者公钥。private-私钥，public-公钥
     * @return Key|null
     */
    public function getKey(string $type = 'private')
    {
        $key = NULL;

        // 对称算法
        if (in_array($this->jwtConfig['alg'], $this->symmetryAlgs)) {
            $key = new Key($this->jwtConfig['secret']);
        }

        // 非对称
        if (in_array($this->jwtConfig['alg'], $this->asymmetricAlgs)) {
            $key = $this->jwtConfig['keys'][$type];
            $key = new Key($key);
        }

        return $key;
    }

    public function getTTL()
    {
        return $this->jwtConfig['ttl'];
    }
}
