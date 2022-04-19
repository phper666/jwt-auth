### 基于Hyperf(https://doc.hyperf.io/#/zh/README) 框架的 jwt 鉴权(json web token)组件。
### 采用基于https://github.com/lcobucci/jwt/tree/3.3 进行封装。
### 黑名单的设置参考了这篇文章https://learnku.com/articles/17883
### 注意：
1、只支持php >= 7.4版本，如果php < 7.4，请使用3.x版本  
2、支持多应用单点登录、多应用多点登录   
3、如有建议欢迎给我邮件，562405704@qq.com  
4、hyperf请使用2.x版本以上

你可以：
```
composer require phper666/jwt-auth:~4.0.0
composer require hyperf/utils:~2.0.0
composer require hyperf/cache:~2.0.0
composer require hyperf/command:~2.0.0
composer require hyperf/config:~2.0.0
composer require hyperf/di:~2.0.0
```
### 说明：

> `jwt-auth` 支持多应用单点登录、多应用多点登录、多应用支持注销 token(token会失效)、支持多应用刷新 token  
  
> 多应用单点登录：在该应用配置下只会有一个 token 生效，一旦刷新 token ，前面生成的 token 都会失效，一般以用户 id 来做区分  
  
> 多应用多点登录：在该配置应用下token 不做限制，一旦刷新 token ，则当前配置应用的 token 会失效  
  
> 注意：使用多应用单点登录或者多应用多点登录时，必须要开启黑名单，并且使用 `Hyperf` 的缓存(建议使用 `redis` 缓存)。如果不开启黑名单，无法使 token 失效，生成的 token 会在有效时间内都可以使用(未更换证书或者 secret )。  
  
> 多应用单点登录原理：`JWT` 有七个默认字段供选择。单点登录主要用到 jti 默认字段，`jti` 字段的值默认为缓存到redis中的key(该key的生成为场景值+存储的用户id(`sso_key`))，这个key的值会存一个签发时间，token检测会根据这个时间来跟token原有的签发时间对比，如果token原有时间小于等于redis存的时间，则认为无效
  
> 多应用多点登录原理：多点登录跟单点登录差不多，唯一不同的是jti的值不是场景值+用户id(`sso_key`)，而是一个唯一字符串，每次调用 `refreshToken` 来刷新 `token` 或者调用 `logout` 注销 token 会默认把请求头中的 token 加入到黑名单，而不会影响到别的 token  
  
> token 不做限制原理：token 不做限制，在 token 有效的时间内都能使用，你只要把配置文件中的 `blacklist_enabled` 设置为 `false` 即可，即为关闭黑名单功能


### 使用：
##### 1、拉取依赖 
> 使用 `Hyperf 2.x` 版本,则
```shell
composer require phper666/jwt-auth:~4.0.0
``` 

##### 2、发布配置
```shell
php bin/hyperf.php jwt:publish --config
```
或者
```shell
php bin/hyperf.php vendor:publish phper666/jwt-auth
```
##### 3、jwt配置
去配置 `config/autoload/jwt.php` 文件或者在配置文件 `.env` 里配置
```php
<?php
return [
    /**
     * 不需要检查的路由，如果使用jwt提供的默认中间件，可以对某些不用做检验的路由进行配置，例如登录等
     * 具体的逻辑可以效仿JWT提供的默认中间件
     * [
     *      ["GET", "/index/test"],
     *      ["**", "/test"]
     * ]
     *
     * 第一个填写请求方法('**'代表支持所有的请求方法)，第二个填写路由路径('/**'代表支持所有的路径)
     * 如果数组中存在["**", "/**"]，则默认所有的请求路由都不做jwt token的校验，直接放行，如果no_check_route为一个空数组，则
     * 所有的请求路由都需要做jwt token校验
     * 正则写法：["**", "/api/{name:.+}"]  支持模块化不做jwt token的校验，例如：/api/login/login
     */
    'no_check_route' => [
        ["**", "/**"],
    ],

    'login_type' => env('JWT_LOGIN_TYPE', 'mpop'), //  登录方式，sso为单点登录，mpop为多点登录

    /**
     * 单点登录自定义数据中必须存在uid的键值，这个key你可以自行定义，只要自定义数据中存在该键即可
     */
    'sso_key' => 'uid',

    /**
     * 只能用于Hmac包下的加密非对称算法，其它的都会使用公私钥
     */
    'secret' => env('JWT_SECRET', 'phper666'),

    /**
     * JWT 权限keys
     * 对称算法: HS256, HS384 & HS512 使用 `JWT_SECRET`.
     * 非对称算法: RS256, RS384 & RS512 / ES256, ES384 & ES512 使用下面的公钥私钥，需要自己去生成.
     */
    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'), // 公钥，例如：'file:///path/to/public/key'
        'private' => env('JWT_PRIVATE_KEY'), // 私钥，例如：'file:///path/to/private/key'

        /**
         * 你的私钥的密码。不需要密码可以不用设置
         */
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    'ttl' => env('JWT_TTL', 7200), // token过期时间，单位为秒

    /**
     * 支持的对称算法：HS256、HS384、HS512
     * 支持的非对称算法：RS256、RS384、RS512、ES256、ES384、ES512
     */
    'alg' => env('JWT_ALG', 'HS256'), // jwt的hearder加密算法

    /**
     * jwt使用到的缓存前缀
     * 建议使用独立的redis做缓存，这样比较好做分布式
     */
    'cache_prefix' => 'phper666:jwt',

    /**
     * 是否开启黑名单，单点登录和多点登录的注销、刷新使原token失效，必须要开启黑名单，目前黑名单缓存只支持hyperf缓存驱动
     */
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

    /**
     * 黑名单的宽限时间 单位为：秒，注意：如果使用单点登录，该宽限时间无效
     */
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    /**
     * 签发者
     */
    'issued_by' => 'phper666/jwt',

    /**
     * 区分不同场景的token，比如你一个项目可能会有多种类型的应用接口鉴权,下面自行定义，我只是举例子
     * 下面的配置会自动覆盖根配置，比如application1会里面的数据会覆盖掉根数据
     * 下面的scene会和根数据合并
     * scene必须存在一个default
     * 什么叫根数据，这个配置的一维数组，除了scene都叫根配置
     */
    'scene' => [
        'default' => [],
        'application' => [
            'secret' => 'application', // 非对称加密使用字符串,请使用自己加密的字符串
            'login_type' => 'sso', //  登录方式，sso为单点登录，mpop为多点登录
            'sso_key' => 'uid',
            'ttl' => 7200, // token过期时间，单位为秒
        ],
        'application1' => [
            'secret' => 'application1', // 非对称加密使用字符串,请使用自己加密的字符串
            'login_type' => 'sso', //  登录方式，sso为单点登录，mpop为多点登录
            'sso_key' => 'uid',
            'ttl' => 7200, // token过期时间，单位为秒
        ],
        'application2' => [
            'secret' => 'application2', // 非对称加密使用字符串,请使用自己加密的字符串
            'login_type' => 'mppo', //  登录方式，sso为单点登录，mpop为多点登录
            'ttl' => 7200, // token过期时间，单位为秒
        ]
    ]
];
```
更多的配置请到 `config/autoload/jwt.php` 查看
##### 4、全局路由验证
在 `config/autoload/middlewaress.php` 配置文件中加入 `jwt` 验证中间件,所有的路由都会进行 `token` 的验证，例如：
```shell
<?php
return [
    'http' => [
        Phper666\JWTAuth\Middleware\JWTAuthDefaultSceneMiddleware:class
    ],
];
```
##### 5、局部验证
在 `config/routes.php` 文件中，想要验证的路由加入 `jwt` 验证中间件即可，例如：
```shell
<?php

Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JWTAuth\Middleware\JWTAuthDefaultSceneMiddleware::class]]);
```
##### 6、注解的路由验证
请看官方文档：https://doc.hyperf.io/#/zh/middleware/middleware
在你想要验证的地方加入 `jwt 验证中间` 件即可。

##### 7、模拟登录获取token,具体情况下面的例子文件
```shell
<?php

namespace App\Controller;
use \Phper666\JWTAuth\JWT;
class IndexController extends Controller
{
    # 模拟登录,获取token
    public function login(Jwt $jwt)
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用默认场景登录
            $token = $this->jwt->getToken('default', $userData);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'token' => $token->toString(),
                    'exp' => $this->jwt->getTTL($token->toString()),
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    # http头部必须携带token才能访问的路由
    public function getData()
    {
        return $this->response->json(['code' => 0, 'msg' => 'success', 'data' => ['a' => 1]]);
    }
}
```
注意：暂时不支持传入用户对象获取 token，后期会支持
##### 7、路由
```shell
<?php
# 登录
Router::post('/login', 'App\Controller\IndexController@login');

# 获取数据
Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JWTAuth\Middleware\JWTAuthDefaultSceneMiddleware::class]]);
```
##### 8、鉴权
在需要鉴权的接口,请求该接口时在 `HTTP` 头部加入
```shell
Authorization  Bearer token
```
##### 9、结果
###### 请求：http://{your ip}:9501/login，下面是返回的结果
```shell
{
    "code": 0,
    "msg": "获取token成功",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1NjQ3MzgyNTgsIm5iZiI6MTU2NDczODI1OCwiZXhwIjoxNTY0NzM4MzE4LCJ1aWQiOjEsInVzZXJuYW1lIjoieHgifQ.CJL1rOqRmrKjFpYalY6Wu7JBH6vkbysfvOf-TMQgonQ"
    }
}
```
###### 请求：http://{your ip}:9501/v1/data
```shell
{
    "code": 0,
    "msg": "success",
    "data": {
        "a": 1
    }
}
```
##### 10、例子文件
```php
<?php

declare(strict_types=1);
namespace App\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Phper666\JWTAuth\JWT;
use Hyperf\HttpServer\Annotation\Middleware;
use Phper666\JWTAuth\Middleware\JWTAuthDefaultSceneMiddleware;
use Phper666\JWTAuth\Middleware\JWTAuthApplicationSceneMiddleware;
use Phper666\JWTAuth\Middleware\JWTAuthApplication1SceneMiddleware;
use Phper666\JWTAuth\Middleware\JWTAuthApplication2SceneMiddleware;
use Hyperf\Di\Annotation\Inject;
use Phper666\JWTAuth\Util\JWTUtil;
use Psr\Container\ContainerInterface;

/**
 * @\Hyperf\HttpServer\Annotation\Controller(prefix="api")
 * Class IndexController
 * @package App\Controller
 */
class IndexController
{
    /**
     *
     * @Inject
     * @var JWT
     */
    protected $jwt;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->request = $container->get(RequestInterface::class);
        $this->response = $container->get(ResponseInterface::class);
    }

    /**
     * 模拟登录
     * @PostMapping(path="login_default")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function loginDefault()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用默认场景登录
            $token = $this->jwt->getToken('default', $userData);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'token' => $token->toString(),
                    'exp' => $this->jwt->getTTL($token->toString()),
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    /**
     * 模拟登录
     * @PostMapping(path="login_application")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function loginApplication()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData1 = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用application2场景登录
            $token1 = $this->jwt->getToken('application1', $userData1);
            $userData2 = [
                'uid' => 2, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用application2场景登录
            $token2 = $this->jwt->getToken('application1', $userData2);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    [
                        'token' => $token1->toString(),
                        'exp' => $this->jwt->getTTL($token1->toString()),
                        'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime($token1->toString())
                    ],
                    [
                        'token' => $token2->toString(),
                        'exp' => $this->jwt->getTTL($token2->toString()),
                        'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime($token2->toString())
                    ]
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    /**
     * 模拟登录
     * @PostMapping(path="login_application1")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function loginApplication1()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData1 = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用application2场景登录
            $token1 = $this->jwt->getToken('application1', $userData1);
            $userData2 = [
                'uid' => 2, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用application2场景登录
            $token2 = $this->jwt->getToken('application1', $userData2);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    [
                        'token' => $token1->toString(),
                        'exp' => $this->jwt->getTTL($token1->toString()),
                        'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime($token1->toString())
                    ],
                    [
                        'token' => $token2->toString(),
                        'exp' => $this->jwt->getTTL($token2->toString()),
                        'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime($token2->toString())
                    ]
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    /**
     * 模拟登录
     * @PostMapping(path="login_application2")
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function loginApplication2()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            // 使用默认场景登录
            $token = $this->jwt->getToken('default', $userData);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'token' => $token->toString(),
                    'exp' => $this->jwt->getTTL($token->toString()),
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    /**
     * default 场景的刷新token
     *
     * @PutMapping(path="refresh_default")
     * @Middleware(JWTAuthDefaultSceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshDefaultToken()
    {
        $token = $this->jwt->refreshToken();
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'token' => $token->toString(),
                'exp' => $this->jwt->getTTL($token->toString()),
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * application 场景的刷新token
     *
     * @PutMapping(path="refresh_application")
     * @Middleware(JWTAuthApplicationSceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshApplicationToken()
    {
        $token = $this->jwt->refreshToken();
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'token' => $token->toString(),
                'exp' => $this->jwt->getTTL($token->toString()),
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * application1 场景的刷新token
     *
     * @PutMapping(path="refresh_application1")
     * @Middleware(JWTAuthApplication1SceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshApplication1Token()
    {
        $token = $this->jwt->refreshToken();
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'token' => $token->toString(),
                'exp' => $this->jwt->getTTL($token->toString()),
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * application2 场景的刷新token
     *
     * @PutMapping(path="refresh_application2")
     * @Middleware(JWTAuthApplication2SceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function refreshApplication2Token()
    {
        $token = $this->jwt->refreshToken();
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'token' => $token->toString(),
                'exp' => $this->jwt->getTTL($token->toString()),
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * default 场景的删除token
     *
     * @DeleteMapping(path="logout_default")
     * @Middleware(JWTAuthDefaultSceneMiddleware::class)
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout_default()
    {
        return $this->jwt->logout();
    }

    /**
     * application 场景的删除token
     *
     * @DeleteMapping(path="logout_application")
     * @Middleware(JWTAuthApplicationSceneMiddleware::class)
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout_application()
    {
        return $this->jwt->logout();
    }

    /**
     * application1 场景的删除token
     *
     * @DeleteMapping(path="logout_application1")
     * @Middleware(JWTAuthApplication1SceneMiddleware::class)
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout_application1()
    {
        return $this->jwt->logout();
    }

    /**
     * application2 场景的删除token
     *
     * @DeleteMapping(path="logout_application2")
     * @Middleware(JWTAuthApplication2SceneMiddleware::class)
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout_application2()
    {
        return $this->jwt->logout();
    }

    /**
     * 只能使用default场景值生成的token访问
     * @GetMapping(path="list_default")
     * @Middleware(JWTAuthDefaultSceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getDefaultData()
    {
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime(JWTUtil::getToken($this->request)),
                'jwt_claims' => JWTUtil::getParserData($this->request)
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * 只能使用application场景值生成的token访问
     * @GetMapping(path="list_application")
     * @Middleware(JWTAuthApplicationSceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getApplicationData()
    {
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime(JWTUtil::getToken($this->request)),
                'jwt_claims' => JWTUtil::getParserData($this->request)
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * 只能使用application1场景值生成的token访问
     * @GetMapping(path="list_application1")
     * @Middleware(JWTAuthApplication1SceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getApplication1Data()
    {
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime(JWTUtil::getToken($this->request)),
                'jwt_claims' => JWTUtil::getParserData($this->request)
            ]
        ];
        return $this->response->json($data);
    }

    /**
     * 只能使用application2场景值生成的token访问
     * @GetMapping(path="list_application2")
     * @Middleware(JWTAuthApplication2SceneMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getApplication2Data()
    {
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'dynamic_exp' => $this->jwt->getTokenDynamicCacheTime(JWTUtil::getToken($this->request)),
                'jwt_claims' => JWTUtil::getParserData($this->request)
            ]
        ];
        return $this->response->json($data);
    }
}

```
##### 11、获取解析后的 token 数据
提供了一个 `getParserData` 来获取解析后的 token 数据。
例如：`JWTUtil::getParserData($this->request)`

##### 12、如何支持每个场景生成的token不能互相访问各个应用
具体你可以查看Phper666\JWTAuth\Middleware\JWTAuthSceneDefaultMiddleware和Phper666\JWTAuth\Middleware\JWTAuthSceneApplication1Middleware这两个中间件，根据这两个中间件你可以编写自己的中间件来支持每个场景生成的token不能互相访问各个应用   

##### 13、建议
> 目前 `jwt` 抛出的异常目前有两种类型 
>`Phper666\JWTAuth\Exception\TokenValidException`、    
>`Phper666\JWTAuth\Exception\JWTException,TokenValidException`  
>异常为 `TokenValidException` 验证失败的异常，会抛出 `401` ,   
>`JWTException` 异常会抛出 `400`，   
>最好你们自己在项目异常重新返回错误信息

##### 14、更新
- 新增no_check_route来忽略对某些路由的jwt校验
- 新增issued_by签发者
- 去掉了model、blacklist_cache_ttl、supported_algs、symmetry_algs、asymmetric_algs配置

##### 15、兼容迁移
- 3.x版本升级到4.x，直接依赖4.x，并去掉model、blacklist_cache_ttl、supported_algs、symmetry_algs、asymmetric_algs配置
- 在配置里面新增no_check_route、issued_by配置,具体情况publish/jwt.php
- 升级后旧的token可能会失效，旧的token，黑名单的token可能会生效，因为黑名单的缓存key有些改变
- 请先备份好配置再升级
