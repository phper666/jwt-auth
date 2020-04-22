<?php
declare(strict_types=1);

namespace Phper666\JWTAuth;
use Hyperf\HttpServer\Contract\RequestInterface;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Phper666\JWTAuth\Exception\JWTException;
use Phper666\JWTAuth\Exception\TokenValidException;
use Phper666\JWTAuth\Util\JWTUtil;
use Psr\Container\ContainerInterface;

/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2020/4/21
 * Time: 1:36 下午
 */
class JWT extends AbstractJWT
{
    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var BlackList
     */
    public $blackList;

    public function __construct(ContainerInterface $container, BlackList $blackList)
    {
        parent::__construct($container);
        $this->request = $this->getContainer()->get(RequestInterface::class);
        $this->blackList = $blackList;
    }

    /**
     * 生成token
     * @param array $claims
     * @param bool $isInsertSsoBlack 是否把单点登录生成的token加入黑名单
     * @param bool  $isConversionString 是否把token强制转换成string类型
     * @return Token|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(array $claims, $isInsertSsoBlack = true, $isConversionString = true)
    {
        $config = $this->getSceneConfig($this->getScene());
        $loginType = $config['login_type'];
        $ssoKey = $config['sso_key'];
        if ($loginType == 'mpop') { // 多点登录,场景值加上一个唯一id
            $uniqid = uniqid($this->getScene() . '_', true);
        } else { // 单点登录
            if (empty($claims[$ssoKey])) {
                throw new JWTException("There is no {$ssoKey} key in the claims", 400);
            }
            $uniqid = $this->getScene() . "_" . $claims[$ssoKey];
        }

        $signer = new $config['supported_algs'][$config['alg']];
        $time = time();
        $builder = JWTUtil::getBuilder()
            ->identifiedBy($uniqid) // 设置jwt的jti
            ->issuedAt($time)// (iat claim) 发布时间
            ->canOnlyBeUsedAfter($time)// (nbf claim) 在此之前不可用
            ->expiresAt($time + $config['ttl']);// (exp claim) 到期时间

        $claims[$this->tokenScenePrefix] = $this->getScene(); // 加入场景值
        foreach ($claims as $k => $v) {
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }

        $token = $builder->getToken($signer, $this->getKey($config)); // Retrieves the generated token

        // 单点登录要把所有的以前生成的token都失效
        if ($loginType == 'sso' && $isInsertSsoBlack) $this->blackList->addTokenBlack($token, $config);

        return $isConversionString ? (string)$token : $token;
    }

    /**
     * 验证token
     * @param string|null $token
     * @param bool        $validate
     * @param bool        $verify
     * @param bool        $independentTokenVerify true时会验证当前场景配置是否是生成当前的token的配置，需要配合自定义中间件实现，false会根据当前token拿到原来的场景配置，并且验证当前token
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    public function checkToken(string $token = null, $validate = true, $verify = true, $independentTokenVerify = false)
    {
        try {
            if (empty($token)) $token = $this->getHeaderToken();
            $config = $this->getSceneConfigByToken($token);
            $token = $this->getTokenObj($token);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        $claims = JWTUtil::claimsToArray($token->getClaims());
        // 验证token是否存在黑名单
        if ($config['blacklist_enabled'] && $this->blackList->hasTokenBlack($claims, $config)) throw new TokenValidException('Token authentication does not pass', 401);

        if ($validate && !$this->validateToken($token)) throw new TokenValidException('Token authentication does not pass', 401);

        // 获取当前环境的场景配置并且验证该token是否是该配置生成的
        if ($independentTokenVerify) $config = $this->getSceneConfig($this->getScene());
        if ($verify && !$this->verifyToken($token, $config)) throw new TokenValidException('Token authentication does not pass', 401);

        return true;
    }

    /**
     * 刷新token
     * @return Token
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshToken(string $token = null)
    {
        if (empty($token)) $token = $this->getHeaderToken();
        $config = $this->getSceneConfigByToken($token);
        $claims = $this->blackList->addTokenBlack($this->getTokenObj($token), $config);
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
        if (empty($token)) $token = $this->getHeaderToken();
        $config = $this->getSceneConfigByToken($token);
        // 如果是sso，并且使当前token失效
        $ssoSelfExp = false;
        if ($config['login_type'] == 'sso') $ssoSelfExp = true;
        $this->blackList->addTokenBlack($this->getTokenObj($token), $config, $ssoSelfExp);
        return true;
    }

    /**
     * 获取token动态有效时间
     * @param string|null $token
     * @return int|mixed
     */
    public function getTokenDynamicCacheTime(string $token = null)
    {
        $nowTime = time();
        if (empty($token)) $token = $this->getHeaderToken();
        $exp = $this->getTokenObj($token)->getClaim('exp', $nowTime);
        $expTime = $exp - $nowTime;
        return $expTime;
    }

    /**
     * 获取jwt token解析的data
     * @param string|null $token
     * @return array
     */
    public function getParserData(string $token = null)
    {
        $arr = [];
        if (empty($token)) $token = $this->getHeaderToken();
        $claims = $this->getTokenObj($token)->getClaims();
        foreach ($claims as $k => $v) {
            $arr[$k] = $v->getValue();
        }
        return $arr;
    }

    /**
     * 获取缓存时间
     * @return mixed
     */
    public function getTTL(string $token = null)
    {
        if (!empty($token)) $config = $this->getSceneConfigByToken($token);
        if (empty($token)) $config = $this->getSceneConfig($this->getScene());
        return (int)$config['ttl'];
    }

    /**
     * 获取对应算法需要的key
     * @param string $type 配置keys里面的键，获取私钥或者公钥。private-私钥，public-公钥
     * @return Key|null
     */
    private function getKey(array $config, string $type = 'private')
    {
        $key = NULL;
        // 对称算法
        if (in_array($config['alg'], $config['symmetry_algs'])) {
            $key = new Key($config['secret']);
        }

        // 非对称
        if (in_array($config['alg'], $config['asymmetric_algs'])) {
            $key = $config['keys'][$type];
            $key = new Key($key);
        }
        return $key;
    }

    /**
     * 获取Token对象
     * @param string|null $token
     * @return Token
     */
    private function getTokenObj(string $token = null)
    {
        return JWTUtil::getParser()->parse($token);
//        if (!empty(str_replace(' ', '', $token))) return JWTUtil::getParser()->parse($token);
//        return JWTUtil::getParser()->parse($this->getHeaderToken());
    }

    /**
     * 获取http头部token
     * @return bool|mixed|string
     */
    private function getHeaderToken()
    {
        $token = $this->request->getHeaderLine('Authorization') ?? '';
        $token = JWTUtil::handleToken($token, $this->tokenPrefix);
        if ($token === false) throw new JWTException('A token is required', 400);
        return $token;
    }

    /**
     * 验证jwt token的data部分
     * @param Token $token token object
     * @return bool
     */
    private function validateToken(Token $token, $currentTime = null)
    {
        $data = JWTUtil::getValidationData($currentTime);
        return $token->validate($data);
    }

    /**
     * 验证 jwt token
     * @param Token $token token object
     * @return bool
     * @throws \Throwable
     */
    private function verifyToken(Token $token, array $config)
    {
        $alg = $token->getHeader('alg');
        if (empty($config['supported_algs'][$alg])) {
            throw new TokenValidException('Algorithm not supported', 401);
        }
        /** @var Signer $signer */
        $signer = new $config['supported_algs'][$alg];
        return $token->verify($signer, $this->getKey($config, 'public'));
    }
}
