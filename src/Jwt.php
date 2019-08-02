<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-01
 * Time: 11:43
 */

namespace Phper666\JwtAuth;

use Hyperf\Config\Annotation\Value;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Parsing\Decoder;
use Lcobucci\JWT\Parsing\Encoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Phper666\JwtAuth\Exception\TokenValidException;

/**
 * https://github.com/phper666/jwt-auth
 * @author LI Yuzhao <562405704@qq.com>
 */
class Jwt
{
    /**
     * @var array Supported algorithms
     */
    protected $supportedAlgs = [
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
    protected $symmetryAlgs = [
        'HS256',
        'HS384',
        'HS512'
    ];

    // 非对称算法名称
    protected $asymmetricAlgs = [
        'RS256',
        'RS384',
        'RS512',
        'ES256',
        'ES384',
        'ES512',
    ];

    /**
     * @Value("jwt.secret")
     */
    protected $secret;

    /**
     * @Value("jwt.keys")
     */
    protected $keys;

    /**
     * @Value("jwt.ttl")
     */
    protected $ttl;

    /**
     * @Value("jwt.alg")
     */
    protected $alg;

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
     * 生成token
     * @param array $claim
     * @return Token
     */
    public function getToken(array $claim)
    {
        $signer = new $this->supportedAlgs[$this->alg];

        $time = time();
        $builder = $this->getBuilder()
            ->issuedAt($time)// (iat claim) 发布时间
            ->canOnlyBeUsedAfter($time)// (nbf claim) 在此之前不可用
            ->expiresAt($time + $this->ttl);// (exp claim) 到期时间
        foreach ($claim as $k => $v) {
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }
        $token = $builder->getToken($signer, $this->getKey()); // Retrieves the generated token

        return $token; // 返回的是token对象，使用强转换会自动转换成token字符串。Token对象采用了__toString魔术方法
    }

    /**
     * 验证token
     * @param string $token JWT
     * @return true
     * @throws \Throwable
     */
    public function checkToken($token, $validate = true, $verify = true)
    {
        try {
            $token = $this->getParser()->parse((string)$token);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        // todo 抛出指定异常
        if ($validate && !$this->validateToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }
        if ($verify && !$this->verifyToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }
        return true;
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
}
