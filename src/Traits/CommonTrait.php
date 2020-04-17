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

    /**
     * @Value("token.prefix")
     */
    public $prefix;

    /**
     * @Value("token.name")
     */
    public $tokenName;

    /**
     * @Value("token.position")
     */
    public $tokenPosition;

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
     * @Value("jwt.refresh_ttl")
     */
    public $refreshTtl;

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
     * @param Encoder|null $encoder
     * @param ClaimFactory|null $claimFactory
     * @return Builder
     * @see [[Lcobucci\JWT\Builder::__construct()]]
     */
    public function getBuilder(Encoder $encoder = null, ClaimFactory $claimFactory = null)
    {
        return new Builder($encoder, $claimFactory);
    }

    /**
     * @param Decoder|null $decoder
     * @param ClaimFactory|null $claimFactory
     * @return Parser
     * @see [[Lcobucci\JWT\Parser::__construct()]]
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
            throw new TokenValidException('Algorithm not supported', 401);
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
     * @deprecated 调整为根据位置动态获取Token
     * @return string|null
     */
    public function getHeaderToken()
    {
        $token = $this->request->getHeader('Authorization')[0] ?? '';
        $token = $this->handleHeaderToken($token);
        if ($token !== false) {
            return $token;
        }

        throw new JWTException('A token is required', 400);
    }

    /**
     * 取得token值
     *
     * @param bool $withPrefix
     * @return string|null
     */
    public function retrieveToken($withPrefix = false)
    {
        if (strtolower($this->tokenPosition) === 'header') {
            $token = $this->request->getHeader($this->tokenName)[0] ?? '';
        } else {
            $token = $this->request->query($this->tokenName);
        }

        $tokenWithoutPrefix = $this->handleToken($token);

        if ($tokenWithoutPrefix === false) {
            throw new JWTException('A token is required', 400);
        }

        return $withPrefix ? $token : $tokenWithoutPrefix;
    }

    /**
     * 获取tokenName
     * @return mixed
     */
    public function getTokenName()
    {
        return $this->tokenName;
    }

    /**
     * 获取tokenPosition
     * @return mixed
     */
    public function getTokenPosition()
    {
        return $this->tokenPosition;
    }

    /**
     * 获取tokenPrefix
     * @return mixed
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * 处理token, alias of handleHeaderToken
     * @param string $token
     * @return bool|string
     */
    public function handleToken(string $token)
    {
        return $this->handleHeaderToken($token);
    }

    /**
     * 处理头部token
     * @param string $token
     * @return bool|string
     * @deprecated
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

    /**
     * @param array $supportedAlgs
     * @return self
     */
    private function _setSupportedAlgs(array $supportedAlgs): self
    {
        $this->supportedAlgs = $supportedAlgs;

        return $this;
    }

    /**
     * @param array $symmetryAlgs
     * @return self
     */
    private function _setSymmetryAlgs(array $symmetryAlgs): self
    {
        $this->symmetryAlgs = $symmetryAlgs;

        return $this;
    }

    /**
     * @param array $asymmetricAlgs
     * @return self
     */
    private function _setAsymmetricAlgs(array $asymmetricAlgs): self
    {
        $this->asymmetricAlgs = $asymmetricAlgs;

        return $this;
    }

    /**
     * @param mixed $prefix
     * @return self
     */
    private function _setPrefix($prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param mixed $tokenName
     * @return self
     */
    private function _setTokenName($tokenName): self
    {
        $this->tokenName = $tokenName;

        return $this;
    }

    /**
     * @param mixed $tokenPosition
     * @return self
     */
    private function _setTokenPosition($tokenPosition): self
    {
        $this->tokenPosition = $tokenPosition;

        return $this;
    }

    /**
     * @param mixed $secret
     * @return self
     */
    private function _setSecret($secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @param mixed $keys
     * @return self
     */
    private function _setKeys($keys): self
    {
        $this->keys = $keys;

        return $this;
    }

    /**
     * @param mixed $ttl
     * @return self
     */
    private function _setTtl($ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param $refreshTtl
     * @return self
     */
    private function _setRefreshTtl($refreshTtl): self
    {
        $this->refreshTtl = $refreshTtl;

        return $this;
    }

    /**
     * @param mixed $alg
     * @return self
     */
    private function _setAlg($alg): self
    {
        $this->alg = $alg;

        return $this;
    }

    /**
     * @param mixed $loginType
     * @return self
     */
    private function _setLoginType($loginType): self
    {
        $this->loginType = $loginType;

        return $this;
    }

    /**
     * @param mixed $ssoKey
     * @return self
     */
    private function _setSsoKey($ssoKey): self
    {
        $this->ssoKey = $ssoKey;

        return $this;
    }

    /**
     * @param mixed $cacheTTL
     * @return self
     */
    private function _setCacheTTL($cacheTTL): self
    {
        $this->cacheTTL = $cacheTTL;

        return $this;
    }

    /**
     * @param mixed $gracePeriod
     * @return self
     */
    private function _setGracePeriod($gracePeriod): self
    {
        $this->gracePeriod = $gracePeriod;

        return $this;
    }

    /**
     * @param mixed $enalbed
     * @return self
     */
    private function _setEnalbed($enalbed): self
    {
        $this->enalbed = $enalbed;

        return $this;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}
