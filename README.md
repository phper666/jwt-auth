### 建议使用3.x版本
### 基于Hyperf(https://doc.hyperf.io/#/zh/README) 框架的 jwt 鉴权(json web token)组件。
### 采用基于https://github.com/lcobucci/jwt/tree/3.3 进行封装。
### 黑名单的设置参考了这篇文章https://learnku.com/articles/17883
### 注意：
1、由于 `Hyperf` 可以升级 `1.1` 版本，如果你用 `1.1` 版本，请需要修改 `jwt-auth` 的 `composer.json` 文件，把依赖 `Hyperf` 的组件版本全部改为 `~1.1.0` 或者使用 `jwt-auth` 的 `^2.0.1` 版本，这个版本是针对 `Hyperf` 的 `1.1` 版本的   
2、composer.json不在依赖安装hyperf的包，需要自行依赖安装，具体依赖的包如下：
```
"hyperf/utils": "required hyperf/utils ~2.0.0 OR required hyperf/utils ~1.1.0",
"hyperf/cache": "required hyperf/cache ~2.0.0 OR required hyperf/cache ~1.1.0",
"hyperf/command": "required hyperf/command ~2.0.0 OR required hyperf/command ~1.1.0",
"hyperf/config": "required hyperf/config ~2.0.0 OR required hyperf/config ~1.1.0",
"hyperf/di": "required hyperf/di ~2.0.0 OR required hyperf/di ~1.1.0"
```
为什么要这样做？因为发现1.1.x和2.0.x的升级不影响该包的代码   
如果你使用hyperf 1.1.x,你可以：
```
composer require phper666/jwt-auth:~2.0.1
composer require hyperf/utils:~1.0.1
composer require hyperf/cache:~1.0.1
composer require hyperf/command:~1.0.1
composer require hyperf/config:~1.0.1
composer require hyperf/di:~1.0.1
```
如果你使用hyperf 2.0.x,你可以：
```
composer require phper666/jwt-auth:~2.0.1
composer require hyperf/utils:~2.0.0
composer require hyperf/cache:~2.0.0
composer require hyperf/command:~2.0.0
composer require hyperf/config:~2.0.0
composer require hyperf/di:~2.0.0
```
### 说明：

> `jwt-auth` 支持单点登录、多点登录、支持注销 token(token会失效)、支持刷新 token  
  
> 单点登录：只会有一个 token 生效，一旦刷新 token ，前面生成的 token 都会失效，一般以用户 id 来做区分  
  
> 多点登录：token 不做限制，一旦刷新 token ，则当前 token 会失效  
  
> 注意：使用单点登录或者多点登录时，必须要开启黑名单，并且使用 `Hyperf` 的缓存(建议使用 `redis` 缓存)。如果不开启黑名单，无法使 token 失效，生成的 token 会在有效时间内都可以使用(未更换证书或者 secret )。  
  
> 单点登录原理：`JWT` 有七个默认字段供选择。单点登录主要用到 jti 默认字段，`jti` 字段的值默认为用户 id。当生成 token 时，`getToken` 方法有一个 `$isInsertSsoBlack` 参数来控制是否会把前面生成的 token 都失效，默认是失效的，如果想不失效，设置为     `false` 即可。但是如果是调用 `refreshToken` 来刷新 token 或者调用 `logout` 注销token，默认前面生成的 token 都会失效。  
jwt 的生成的 token 加入黑名单时，会把用户 id 作为缓存的键，当前时间作为值，配置文件中的 `blacklist_cache_ttl` 作为缓存的失效时间。每次生成 token 或者刷新 token 时，会先从 token 中拿到签发时间和 `jti` 值找到对应的缓存拿到时间，拿到时间后跟 token 的签发时间对比，如果签发时间小于等于拿到的时间值，则 token 判断为失效的。（`jti` 在单点登录中，存的值是用户 id）  
  
> 多点登录原理：多点登录跟单点登录差不多，唯一不同的是jti的值不是用户 id，而是一个唯一字符串，每次调用 `refreshToken` 来刷新 `token` 或者调用 `logout` 注销 token 会默认把请求头中的 token 加入到黑名单，而不会影响到别的 token  
  
> token 不做限制原理：token 不做限制，在 token 有效的时间内都能使用，你只要把配置文件中的 `blacklist_enabled` 设置为 `false` 即可，即为关闭黑名单功能


### 使用：
##### 1、拉取依赖 
> 如果你使用 `Hyperf 1.0.x` 版本,则
```shell
composer require phper666/jwt-auth:~1.0.1
``` 

> 如果你使用 `Hyperf 1.1.x` 版本，则 
```shell
composer require phper666/jwt-auth:~2.0.1
```

##### 2、发布配置
```shell
php bin/hyperf.php jwt:publish --config
```

##### 3、jwt配置
去配置 `config/autoload/jwt.php` 文件或者在配置文件 `.env` 里配置
```shell
# 务必改为你自己的字符串
JWT_SECRET=hyperf
#token过期时间，单位为秒
JWT_TTL=60
```
更多的配置请到 `config/autoload/jwt.php` 查看
##### 4、全局路由验证
在 `config/autoload/middlewaress.php` 配置文件中加入 `jwt` 验证中间件,所有的路由都会进行 `token` 的验证，例如：
```shell
<?php
return [
    'http' => [
        Phper666\JwtAuth\Middleware\JwtAuthMiddleware:class
    ],
];
```
##### 5、局部验证
在 `config/routes.php` 文件中，想要验证的路由加入 `jwt` 验证中间件即可，例如：
```shell
<?php

Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JwtAuth\Middleware\JwtAuthMiddleware::class]]);
```
##### 6、注解的路由验证
请看官方文档：https://doc.hyperf.io/#/zh/middleware/middleware
在你想要验证的地方加入 `jwt 验证中间` 件即可。

##### 7、模拟登录获取token
```shell
<?php

namespace App\Controller;
use \Phper666\JwtAuth\Jwt;
class IndexController extends Controller
{
    # 模拟登录,获取token
    public function login(Jwt $jwt)
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData = [
                'uid' => 1,
                'username' => 'xx',
            ];
            $token = (string)$jwt->getToken($userData);
            return $this->response->json(['code' => 0, 'msg' => '获取token成功', 'data' => ['token' => $token]]);
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
}, ['middleware' => [Phper666\JwtAuth\Middleware\JwtAuthMiddleware::class]]);
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
```shell
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\AutoController;
use \Phper666\JwtAuth\Jwt;

/**
 * @AutoController()
 * Class IndexController
 * @package App\Controller
 */
class IndexController extends AbstractController
{
    /**
     * @Inject()
     * @var Jwt
     */
    protected $jwt;

    # 模拟登录
    public function login()
    {
        $username = $this->request->input('username');
        $password = $this->request->input('password');
        if ($username && $password) {
            $userData = [
                'uid' => 1, // 如果使用单点登录，必须存在配置文件中的sso_key的值，一般设置为用户的id
                'username' => 'xx',
            ];
            $token = $this->jwt->getToken($userData);
            $data = [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'token' => (string)$token,
                    'exp' => $this->jwt->getTTL(),
                ]
            ];
            return $this->response->json($data);
        }
        return $this->response->json(['code' => 0, 'msg' => '登录失败', 'data' => []]);
    }

    # 刷新token，http头部必须携带token才能访问的路由
    public function refreshToken()
    {
        $token = $this->jwt->refreshToken();
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'token' => (string)$token,
                'exp' => $this->jwt->getTTL(),
            ]
        ];
        return $this->response->json($data);
    }

    # 注销token，http头部必须携带token才能访问的路由
    public function logout()
    {
        $this->jwt->logout();
        return true;
    }

    # http头部必须携带token才能访问的路由
    public function getData()
    {
        $data = [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'cache_time' => $this->jwt->getTokenDynamicCacheTime() // 获取token的有效时间，动态的
            ]
        ];
        return $this->response->json($data);
    }

    public function index()
    {
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
        ];
    }
}

```
##### 11、获取解析后的 token 数据
提供了一个方法     `getParserData` 来获取解析后的 token 数据。
例如：`$this->jwt->getParserData()`

##### 12、建议
> 目前 `jwt` 抛出的异常目前有两种类型 `Phper666\JwtAuth\Exception\TokenValidException` 和 `Phper666\JwtAuth\Exception\JWTException,TokenValidException` 异常为 token 验证失败的异常，会抛出 `401` ,`JWTException` 异常会抛出 `400`，最好你们自己在项目异常重新返回错误信息
