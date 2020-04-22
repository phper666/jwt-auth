<?php
declare(strict_types=1);
namespace Phper666\JWTAuth\Util;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Parsing\Decoder;
use Lcobucci\JWT\Parsing\Encoder;
use Lcobucci\JWT\ValidationData;

/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2020/4/21
 * Time: 1:51 下午
 */
class JWTUtil
{
    /**
     * claims对象转换成数组
     * @param $claims
     * @return mixed
     */
    public static function claimsToArray( $claims)
    {
        /**
         *  @var $claim \Lcobucci\JWT\Claim
         */
        foreach($claims as $k => $claim) {
            $claims[$k] = $claim->getValue();
        }
        return $claims;
    }

    /**
     * 解析token
     * @param string $token
     * @return array
     */
    public static function getParserData(string $token)
    {
        $arr = [];
        $claims = self::getParser()->parse($token)->getClaims();
        foreach ($claims as $k => $v) {
            $arr[$k] = $v->getValue();
        }
        return $arr;
    }

    /**
     * 处理token
     * @param string $token
     * @param string $prefix
     * @return bool|mixed|string
     */
    public static function handleToken(string $token, string $prefix = 'Bearer')
    {
        if (strlen($token) > 0) {
            $token = ucfirst($token);
            $arr = explode("{$prefix} ", $token);
            $token = $arr[1] ?? '';
            if (strlen($token) > 0) return $token;
        }
        return false;
    }

    /**
     * @see [[Lcobucci\JWT\Builder::__construct()]]
     * @return Builder
     */
    public static function getBuilder(Encoder $encoder = null, ClaimFactory $claimFactory = null)
    {
        return new Builder($encoder, $claimFactory);
    }

    /**
     * @see [[Lcobucci\JWT\Parser::__construct()]]
     * @return Parser
     */
    public static function getParser(Decoder $decoder = null, ClaimFactory $claimFactory = null)
    {
        return new Parser($decoder, $claimFactory);
    }

    /**
     * @see [[Lcobucci\JWT\ValidationData::__construct()]]
     * @return ValidationData
     */
    public static function getValidationData($currentTime = null)
    {
        return new ValidationData($currentTime);
    }
}
