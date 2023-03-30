<?php
declare(strict_types=1);

namespace Phper666\JWTAuth;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaimGiven;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Phper666\JWTAuth\Constant\JWTConstant;
use Phper666\JWTAuth\Exception\JWTException;
use Phper666\JWTAuth\Exception\TokenValidException;
use Phper666\JWTAuth\Util\JWTUtil;
use Phper666\JWTAuth\Util\TimeUtil;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;

/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2021/12/08
 * Time: 1:36 下午
 */
class JWT extends AbstractJWT
{
    private $supportAlgs = [
        // 非对称算法
        'RS256' => 'Lcobucci\JWT\Signer\Rsa\Sha256',
        'RS384' => 'Lcobucci\JWT\Signer\Rsa\Sha384',
        'RS512' => 'Lcobucci\JWT\Signer\Rsa\Sha512',
        'ES256' => 'Lcobucci\JWT\Signer\Ecdsa\Sha256',
        'ES384' => 'Lcobucci\JWT\Signer\Ecdsa\Sha384',
        'ES512' => 'Lcobucci\JWT\Signer\Ecdsa\Sha512',

        // 对称算法
        'HS256' => 'Lcobucci\JWT\Signer\Hmac\Sha256',
        'HS384' => 'Lcobucci\JWT\Signer\Hmac\Sha384',
        'HS512' => 'Lcobucci\JWT\Signer\Hmac\Sha512',
    ];

    /**
     * @var string
     */
    private $jwtClaimScene = 'jwt_scene';

    private $scene = 'default';

    /**
     * @var RequestInterface|mixed
     */
    public $request;

    /**
     * @var ConfigInterface|mixed
     */
    private $jwtConfig;

    /**
     * @var mixed|CacheInterface
     */
    private $cache;

    /**
     * @var Configuration
     */
    private $lcobucciJwtConfiguration;

    /**
     * @var PathMatch
     */
    private $pathMatch;

    public function __construct()
    {
        $config = make(ConfigInterface::class);
        $jwtConfig = $config->get(JWTConstant::CONFIG_NAME, []);
        $scenes = $jwtConfig['scene'];
        foreach ($scenes as $key => $scene) {
            $sceneConfig = array_merge($jwtConfig, $scene);
            $sceneConfigKey = JWTConstant::CONFIG_NAME . '.' .$key;
            $config->set($sceneConfigKey, $sceneConfig);
        }

        $this->jwtConfig = $config->get(JWTConstant::CONFIG_NAME, []);
        $this->cache = make(CacheInterface::class);
        $this->request = make(RequestInterface::class);
        $this->pathMatch = make(PathMatch::class);
    }

    /**
     * @param string $scene
     * @return $this
     */
    protected function initConfiguration(string $scene) {
        $this->setScene($scene);
        $jwtSceneConfig = $this->getJwtSceneConfig($scene);
        $useAlgsClass = $this->supportAlgs[$jwtSceneConfig['alg']];
        if (!$this->isAsymmetric()) {
            $this->lcobucciJwtConfiguration = Configuration::forSymmetricSigner(
                new $useAlgsClass(),
                InMemory::base64Encoded(base64_encode($jwtSceneConfig['secret']))
            );
        } else {
            $this->lcobucciJwtConfiguration = Configuration::forAsymmetricSigner(
                new $useAlgsClass(),
                InMemory::file($jwtSceneConfig['keys']['private'], $jwtSceneConfig['keys']['passphrase']),
                InMemory::file($jwtSceneConfig['keys']['public'])
            );
        }
        return $this;
    }

    /**
     * 生成token
     *
     * @param array $claims
     * @return Token|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(string $scene, array $claims): Plain
    {
        // 初始化lcobucci jwt config
        $this->initConfiguration($scene);
        $claims[$this->jwtClaimScene] = $scene; // 加入场景值

        $jwtSceneConfig = $this->getJwtSceneConfig();
        $loginType = $jwtSceneConfig['login_type'];
        $ssoKey = $jwtSceneConfig['sso_key'];
        $issuedBy = $jwtSceneConfig[RegisteredClaims::ISSUER] ?? 'phper666/jwt';
        if ($loginType == JWTConstant::MPOP) { // 多点登录,场景值加上一个唯一id
            $uniqid = uniqid($this->getScene() . '_', true);
        } else { // 单点登录
            if (empty($claims[$ssoKey])) {
                throw new JWTException("There is no {$ssoKey} key in the claims", 400);
            }
            $uniqid = $this->getScene() . "_" . $claims[$ssoKey];
        }

        $clock = SystemClock::fromUTC();
        $now = $clock->now();
        $expiresAt = $clock->now()->modify('+' . $jwtSceneConfig['ttl'] . ' second');
        $builder = $this->lcobucciJwtConfiguration->builder(ChainedFormatter::withUnixTimestampDates())->issuedBy($issuedBy);
        foreach ($claims as $k => $v) {
            if ($k == RegisteredClaims::SUBJECT) {
                $builder = $builder->relatedTo($v);
                continue;
            }
            if ($k == RegisteredClaims::AUDIENCE) {
                if (!is_array($v)) {
                    throw new JWTException("Aud only supports array types", 400);
                }
                $builder = $builder->PermittedFor(...$v);
                continue;
            }
            if ($k == RegisteredClaims::ISSUER) {
                $builder = $builder->issuedBy($v);
                continue;
            }
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }
        $builder = $builder
            // Configures the id (jti claim) 设置jwt的jti
            ->identifiedBy($uniqid)
            // Configures the time that the token was issue (iat claim) 发布时间
            ->issuedAt($now)
            // Configures the time that the token can be used (nbf claim) 在此之前不可用
            ->canOnlyBeUsedAfter($now)
            // Configures the expiration time of the token (exp claim) 到期时间
            ->expiresAt($expiresAt);


        $token = $builder->getToken($this->lcobucciJwtConfiguration->signer(), $this->lcobucciJwtConfiguration->signingKey());
        if ($loginType == JWTConstant::SSO) {
            $this->addTokenBlack($token, true);
        }
        return $token;
    }

    /**
     * 获取当前场景的配置
     *
     * @return mixed
     */
    public function getJwtSceneConfig(string $scene = null) {
        if ($scene == null) {
            return $this->jwtConfig[$this->getScene()];
        }
        return $this->jwtConfig[$scene];
    }

    /**
     * @param string $token
     * @return bool
     */
    public function verifyToken(string $token): bool
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        $token = $this->tokenToPlain($token);
        $this->initConfiguration($this->getSceneByTokenPlain($token));

        $constraints = $this->validationConstraints($token->claims(), $this->lcobucciJwtConfiguration);
        if (!$this->lcobucciJwtConfiguration->validator()->validate($token, ...$constraints)) {
            throw new TokenValidException('Token authentication does not pass', 400);
        }

        // 验证token是否存在黑名单
        if ($this->hasTokenBlack($token)) {
            throw new TokenValidException('Token authentication does not pass', 400);
        }

        return true;
    }

    /**
     * @param string $scene
     * @param string $token
     * @return bool
     */
    public function verifyTokenAndScene(string $scene, string $token): bool
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }
        $plainToken = $this->tokenToPlain($token);
        $tokenScene = $this->getSceneByTokenPlain($plainToken);
        if ($scene != $tokenScene) {
            throw new JWTException('The token does not support the current scene', 400);
        }

        return $this->verifyToken($token);
    }

    /**
     * 检查当前路由是否需要对jwt进行校验
     *
     * @param string $requestMethod
     * @param string $requestPath
     * @return bool
     */
    public function matchRoute(?string $scene, string $requestMethod, string $requestPath): bool
    {
        $noCheckRoute = $this->jwtConfig['no_check_route'] ?? [];
        if ($scene != null) {
            $noCheckRoute = $this->jwtConfig[$scene]['no_check_route'] ?? [];
        }

        return $this->pathMatch->matchRoute($noCheckRoute, $requestMethod, $requestPath);
    }


    /**
     * 判断token是否已经加入黑名单
     *
     * @param $claims
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function hasTokenBlack(Plain $token): bool
    {
        $sceneConfig = $this->getSceneConfigByToken($token);
        if ($sceneConfig['blacklist_enabled']) {
            $claims = $token->claims();
            $cacheKey = $this->getCacheKey($sceneConfig, $claims->get(RegisteredClaims::ID));
            $cacheValue = $this->cache->get($cacheKey);
            if ($sceneConfig['login_type'] == JWTConstant::MPOP) {
                return !empty($cacheValue['valid_until']) && !TimeUtil::isFuture($cacheValue['valid_until']);
            }

            if ($sceneConfig['login_type'] == JWTConstant::SSO) {
                // 签发时间
                $iatTime = TimeUtil::getCarbonTimeByTokenTime($claims->get(RegisteredClaims::ISSUED_AT))->getTimestamp();
                if (!empty($cacheValue['valid_until']) && !empty($iatTime)) {
                    // 当前token的签发时间小于等于缓存的签发时间，则证明当前token无效
                    return $iatTime <= $cacheValue['valid_until'];
                }
            }
        }

        return false;
    }

    /**
     * 把token加入到黑名单中
     *
     * @param Token $token
     * @param bool $addByCreateTokenMethod
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function addTokenBlack(Plain $token, bool $addByCreateTokenMethod = false): bool
    {
        $sceneConfig = $this->getSceneConfigByToken($token);
        $claims = $token->claims();
        if ($sceneConfig['blacklist_enabled']) {
            $cacheKey = $this->getCacheKey($sceneConfig, $claims->get(RegisteredClaims::ID));
            if ($sceneConfig['login_type'] == JWTConstant::MPOP) {
                $blacklistGracePeriod = $sceneConfig['blacklist_grace_period'];
                $iatTime = TimeUtil::getCarbonTimeByTokenTime($claims->get(RegisteredClaims::ISSUED_AT));
                $validUntil = $iatTime->addSeconds($blacklistGracePeriod)->getTimestamp();
            } else {
                /**
                 * 为什么要取当前的时间戳？
                 * 是为了在单点登录下，让这个时间前当前用户生成的token都失效，可以把这个用户在多个端都踢下线
                 */
                $validUntil = TimeUtil::now()->subSeconds(1)->getTimestamp();
            }

            /**
             * 缓存时间取当前时间跟jwt过期时间的差值，单位秒
             */
            $tokenCacheTime = $this->getTokenCacheTime($claims);
            if ($tokenCacheTime > 0) {
                return $this->cache->set(
                    $cacheKey,
                    ['valid_until' => $validUntil],
                    $tokenCacheTime
                );
            }
        }
        return false;
    }

    /**
     * 获取token缓存时间，根据token的过期时间跟当前时间的差值来做缓存时间
     *
     * @param DataSet $claims
     * @return int
     */
    private function getTokenCacheTime(DataSet $claims): int
    {
        $expTime = TimeUtil::getCarbonTimeByTokenTime($claims->get(RegisteredClaims::EXPIRATION_TIME));
        $nowTime = TimeUtil::now();
        // 优化，如果当前时间大于过期时间，则证明这个jwt token已经失效了，没有必要缓存了
        // 如果当前时间小于等于过期时间，则缓存时间为两个的差值
        if ($nowTime->lte($expTime)) {
            // 加1秒防止临界时间缓存问题
            return $expTime->diffInSeconds($nowTime) + 1;
        }

        return 0;
    }

    /**
     * 刷新token
     *
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshToken(string $token = null): Plain
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        $token = $this->tokenToPlain($token);

        // TODO emm....这里是否要做失败处理?
        $this->addTokenBlack($token);

        $claims = $token->claims();
        $data = JWTUtil::claimsToArray($claims);
        $scene = $this->getSceneByClaims($claims);
        unset($data[RegisteredClaims::ISSUER]);
        unset($data[RegisteredClaims::EXPIRATION_TIME]);
        unset($data[RegisteredClaims::NOT_BEFORE]);
        unset($data[RegisteredClaims::ISSUED_AT]);
        unset($data[RegisteredClaims::ID]);
        return $this->getToken($scene, $data);
    }

    /**
     * 让token失效
     *
     * @param string|null $token
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout(string $token = null): bool
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        $token = $this->tokenToPlain($token);
        return $this->addTokenBlack($token);
    }

    /**
     * 获取token动态有效时间
     *
     * @param string|null $token
     * @return int|mixed
     */
    public function getTokenDynamicCacheTime(string $token = null): int
    {
        if($token == null) {
            throw new JWTException("Missing token");
        }

        $nowTime = TimeUtil::now();
        $expTime = $this->tokenToPlain($token)->claims()->get(RegisteredClaims::EXPIRATION_TIME, $nowTime);

        $expTime = TimeUtil::getCarbonTimeByTokenTime($expTime);
        return $nowTime->max($expTime)->diffInSeconds();
    }

    /**
     * 获取jwt的claims数据
     *
     * @param string $token
     * @return array
     */
    public function getClaimsByToken(string $token = null): array
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        return $this->tokenToPlain($token)->claims()->all();
    }

    public function tokenToPlain(string $token): Plain
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }
        try{
            return JWTUtil::getParser()->parse($token);
        }catch (\Exception $e) {
            throw new JWTException('Jwt token interpretation error. Please provide the correct jwt token and parse the error information: ' . $e->getMessage(), 400);
        }
    }

    public function setScene(string $scene = 'default'): JWT
    {
        $this->scene = $scene;
        return $this;
    }

    public function getScene(): string
    {
        return $this->scene;
    }

    public function getCacheKey(array $sceneConfig, string $claimJti): string
    {
        return $sceneConfig["cache_prefix"] . ':' . $claimJti;
    }

    /**
     * 获取缓存时间
     *
     * @return mixed
     */
    public function getCacheTTL(string $token = null): int
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        $token = $this->tokenToPlain($token);
        $claimJti = $token->claims()->get(RegisteredClaims::ID);
        $sceneConfig = $this->getSceneConfigByToken($token);
        $cacheKey = $this->getCacheKey($sceneConfig, $claimJti);
        return $this->cache->get($cacheKey);
    }

    public function getTTL(string $token): int
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }

        $token = JWTUtil::getParser()->parse($token);
        $sceneConfig = $this->getSceneConfigByToken($token);
        return (int)$sceneConfig['ttl'];
    }

    public function getSceneByToken(string $token): bool
    {
        if($token == null) {
            $token = JWTUtil::getToken($this->request);
        }
        $token = $this->tokenToPlain($token);
        $scene = $this->getSceneByTokenPlain($token);
        return $this->jwtConfig[$scene];
    }

    /**
     * 获取Signer
     *
     * @return Signer
     */
    protected function getSigner(): Signer
    {
        $jwtSceneConfig = $this->getJwtSceneConfig();
        $alg = $jwtSceneConfig['alg'];
        if (! array_key_exists($alg, $this->supportAlgs)) {
            throw new JWTException('The given supportAlgs could not be found', 400);
        }

        return new $this->supportAlgs[$alg];
    }

    /**
     * 判断是否为非对称算法
     */
    protected function isAsymmetric(): bool
    {
        $reflect = new ReflectionClass($this->getSigner());

        return $reflect->isSubclassOf(Signer\Rsa::class) || $reflect->isSubclassOf(Signer\Ecdsa::class);
    }

    /**
     * https://lcobucci-jwt.readthedocs.io/en/latest/validating-tokens/
     * JWT 验证时，支持的校验
     * 'Lcobucci\JWT\Validation\Constraint\IdentifiedBy',
     * 'Lcobucci\JWT\Validation\Constraint\IssuedBy',
     * 'Lcobucci\JWT\Validation\Constraint\PermittedFor',
     * 'Lcobucci\JWT\Validation\Constraint\RelatedTo',
     * 'Lcobucci\JWT\Validation\Constraint\SignedWith',
     * 'Lcobucci\JWT\Validation\Constraint\StrictValidAt',
     * 'Lcobucci\JWT\Validation\Constraint\LooseValidAt'
     * @return array
     */
    protected function validationConstraints(DataSet $claims, Configuration $configuration)
    {
        $clock = SystemClock::fromUTC();
        $validationConstraints = [
            new IdentifiedBy($claims->get(RegisteredClaims::ID)),
            new IssuedBy($claims->get(RegisteredClaims::ISSUER)),
            new LooseValidAt($clock),
            new StrictValidAt($clock),
            new SignedWith($configuration->signer(), $configuration->verificationKey())
        ];
        if ($claims->get(RegisteredClaims::AUDIENCE) != null) {
            $validationConstraints[] = new PermittedFor(...$claims->get(RegisteredClaims::AUDIENCE));
        }
        if ($claims->get(RegisteredClaims::SUBJECT) != null) {
            $validationConstraints[] = new RelatedTo($claims->get(RegisteredClaims::SUBJECT));
        }
        return $validationConstraints;
    }

    /**
     * 通过token获取当前场景的配置
     *
     * @param Plain $token
     * @return string
     */
    protected function getSceneConfigByToken(Plain $token): array
    {
        $scene = $this->getSceneByTokenPlain($token);
        return $this->jwtConfig[$scene];
    }

    protected function getSceneByClaims(DataSet $claims) {
        return $claims->get($this->jwtClaimScene, $this->getScene());
    }

    /**
     * @param Plain $token
     * @return string
     */
    protected function getSceneByTokenPlain(Plain $token): string
    {
        $claims = $token->claims()->all();
        return $claims[$this->jwtClaimScene];
    }
}
