<?php
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2019-08-07
 * Time: 14:14
 */
namespace Phper666\JwtAuth\Traits;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Parsing\Decoder;
use Lcobucci\JWT\Parsing\Encoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Token;
use Phper666\JwtAuth\Exception\TokenValidException;

trait CommonTrait
{
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
            throw new TokenValidException('Algorithm not supported', 401);
        }
        /** @var Signer $signer */
        $signer = new $this->supportedAlgs[$alg];
        return $token->verify($signer, $this->getKey('public'));
    }

    public function __get($name)
    {
        return $this->$name;
    }
}
