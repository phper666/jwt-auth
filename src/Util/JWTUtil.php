<?php
declare(strict_types=1);
namespace Phper666\JWTAuth\Util;
use Lcobucci\JWT\ClaimsFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Decoder;
use Lcobucci\JWT\Encoder;
use Lcobucci\JWT\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;

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
     *
     * @param $claims
     * @return mixed
     */
    public static function claimsToArray(DataSet $claims): array
    {
        return $claims->all();
    }

    /**
     * 获取jwt token
     * @param ServerRequestInterface $request
     * @return array
     */
    public static function getToken(ServerRequestInterface $request)
    {
        $token = $request->getHeaderLine('Authorization') ?? '';
        return self::handleToken($token);
    }

    /**
     * 解析token
     * @param ServerRequestInterface $request
     * @return array
     */
    public static function getParserData(ServerRequestInterface $request): array
    {
        $token = $request->getHeaderLine('Authorization') ?? '';
        $token = self::handleToken($token);
        return self::getParser()->parse($token)->claims()->all();
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
            if (strlen($token) > 0) {
                return $token;
            }
        }
        return false;
    }

    /**
     * @return Parser
     */
    public static function getParser(Decoder $decoder = null): Parser
    {
        if ($decoder == null) {
            return new Parser(new JoseEncoder());
        }
        return new Parser($decoder);
    }

    public static function getValidator(): Validator
    {
        return new Validator();
    }
}
