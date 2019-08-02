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
 * JSON Web Token implementation, based on this library:
 * https://github.com/phper666/jwt-auth
 *
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

    /**
     * @Value("jwt.secret")
     */
    protected $secret;

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
        $time = time();
        $signer = new $this->supportedAlgs[$this->alg];
        $key = new Key($this->secret);

        $builder = $this->getBuilder()
            ->issuedAt($time)// (iat claim) 发布时间
            ->canOnlyBeUsedAfter($time)// (nbf claim) 在此之前不可用
            ->expiresAt($time + $this->ttl);// (exp claim) 到期时间
        foreach ($claim as $k => $v) {
            $builder = $builder->withClaim($k, $v); // 自定义数据
        }
        $token = $builder->getToken($signer, $key); // Retrieves the generated token

        return $token; // The string representation of the object is a JWT string
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
     * Parses the JWT and returns a token class
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
        if ($validate && !$this->validateToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }
        if ($verify && !$this->verifyToken($token)) {
            throw new TokenValidException('Token authentication does not pass', 401);
        }
        return true;
    }

    /**
     * Validate token
     * @param Token $token token object
     * @return bool
     */
    public function validateToken(Token $token, $currentTime = null)
    {
        $data = $this->getValidationData($currentTime);
        // @todo Add claims for validation
        return $token->validate($data);
    }

    /**
     * Validate token
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
        return $token->verify($signer, $this->secret);
    }
}
