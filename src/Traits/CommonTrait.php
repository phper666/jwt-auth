<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-07
 * Time: 14:14
 */
namespace Phper666\JwtAuth\Traits;

use Hyperf\Config\Annotation\Value;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Phper666\JwtAuth\Exception\JWTException;
use Psr\SimpleCache\CacheInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Parsing\Decoder;
use Lcobucci\JWT\Parsing\Encoder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Exception\TokenValidException;

trait CommonTrait
{
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
     * @Inject
     * @var RequestInterface
     */
    public $request;

    /**
     * @Inject
     * @var CacheInterface
     */
    public $storage;


    /**
     * @Value("jwt.secret")
     */
    public $secret;

    /**
     * @Value("jwt.keys")
     */
    public $keys;

    /**
     * @Value("jwt.ttl")
     */
    public $ttl;

    /**
     * @Value("jwt.alg")
     */
    public $alg;

    /**
     * @Value("jwt.login_type")
     */
    public $loginType = 'mpop';

    /**
     * @Value("jwt.sso_key")
     */
    public $ssoKey = 'uid';

    /**
     * @Value("jwt.blacklist_cache_ttl")
     */
    public $cacheTTL = 86400;

    /**
     * @Value("jwt.blacklist_grace_period")
     */
    public $gracePeriod = 0;

    /**
     * @Value("jwt.blacklist_enabled")
     */
    public $enalbed = true;

    /**
     * @see [[Lcobucci\JWT\Builder::__construct()]]
     * @return Builder
     */
    public function getBuilder(Encoder $encoder = null, ClaimFactory $claimFactory = null)
    {
        return new Builder($encoder, $claimFactory);
    }

    /**
     * @see [[Lcobucci\JWT\Parser::__construct()]]
     * @return Parser
     */
    public function getParser(Decoder $decoder = null, ClaimFactory $claimFactory = null)
    {
        return new Parser($decoder, $claimFactory);
    }

    /**
     * @see [[Lcobucci\JWT\ValidationData::__construct()]]
     * @return ValidationData
     */
    public function getValidationData($currentTime = null)
    {
        return new ValidationData($currentTime);
    }


    /**
     * 验证jwt token的data部分
     * @param Token $token token object
     * @return bool
     */
    public function validateToken(Token $token, $currentTime = null)
    {
        $data = $this->getValidationData($currentTime);
        return $token->validate($data);
    }

    /**
     * 验证 jwt token
     * @param Token $token token object
     * @return bool
     * @throws \Throwable
     */
    public function verifyToken(Token $token)
    {
        $alg = $token->getHeader('alg');
        if (empty($this->supportedAlgs[$alg])) {
            throw new TokenValidException('Algorithm not supported', 500);
        }
        /** @var Signer $signer */
        $signer = new $this->supportedAlgs[$alg];
        return $token->verify($signer, $this->getKey('public'));
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
        if (in_array($this->alg, $this->symmetryAlgs)) {
            $key = new Key($this->secret);
        }

        // 非对称
        if (in_array($this->alg, $this->asymmetricAlgs)) {
            $key = $this->keys[$type];
            $key = new Key($key);
        }

        return $key;
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
        $token = $this->handleHeaderToken($token);
        if ($token !== false) {
            return $token;
        }

        throw new JWTException('A token is required', 500);
    }

    /**
     * 处理头部token
     * @param string $token
     * @return bool|string
     */
    public function handleHeaderToken(string $token)
    {
        if (strlen($token) > 0) {
            $token = ucfirst($token);
            $arr = explode($this->prefix . ' ', $token);
            $token = $arr[1] ?? '';
            if (strlen($token) > 0) return $token;
        }
        return false;
    }

    /**
     * @param $claims
     * @return mixed
     */
    public function claimsToArray($claims)
    {
        foreach($claims as $k => $v) {
            $claims[$k] = $v->getValue();
        }

        return $claims;
    }

    /**
     * 获取缓存时间
     * @return mixed
     */
    public function getTTL()
    {
        return (int)$this->ttl;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}
