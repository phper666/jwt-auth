### 基于hyperf(https://doc.hyperf.io/#/zh/README) 框架的jwt鉴权(json web token)组件。
### 采用基于https://github.com/lcobucci/jwt/tree/3.3 进行封装。
### 黑名单的设置参考了这篇文章https://learnku.com/articles/17883
### 注意：
由于hyperf可以升级1.1版本，如果你用1.1版本，请需要修改jwt-auth的composer.json文件，把依赖hyperf的组件版本全部改为~1.1.0或者使用jwt-auth的^2.0.1版本，这个版本是针对hyperf的1.1版本的
### 说明：
```shell
jwt-auth支持单点登录、多点登录、支持注销token(token会失效)、支持刷新token  
  
单点登录：只会有一个token生效，一旦刷新token，前面生成的token都会失效，一般以用户id来做区分  
  
多点登录：token不做限制，一旦刷新token，则当前token会失效  
  
注意：使用单点登录或者多点登录时，必须要开启黑名单，并且使用hyperf的缓存(建议使用redis缓存)。如果不开启黑名单，无法使token失效，生成的token会在有效时间内都可以使用(未更换证书或者secret)。  
  
单点登录原理：JWT有七个默认字段供选择。单点登录主要用到jti默认字段，jti字段的值默认为用户id。当生成token时，getToken方法有一个$isInsertSsoBlack参数来控制是否会把前面生成的token都失效，默认是失效的，如果想不失效，设置为false即可。但是如果是调用refreshToken来刷新token或者调用logout注销token，默认前面生成的token都会失效。  
jwt的生成的token加入黑名单时，会把用户id作为缓存的键，当前时间作为值，配置文件中的blacklist_cache_ttl作为缓存的失效时间。每次生成token或者刷新token时，会先从token中拿到签发时间和jti的值，根据jti值找到对应的缓存拿到时间，拿到时间后跟token的签发时间对比，如果签发时间小于等于拿到的时间值，则token判断为失效的。（jti在单点登录中，存的值是用户id）  
  
多点登录原理：多点登录跟单点登录差不多，唯一不同的是jti的值不是用户id，而是一个唯一字符串，每次调用refreshToken来刷新token或者调用logout注销token会默认把请求头中的token加入到黑名单，而不会影响到别的token  
  
token不做限制原理：token不做限制，在token有效的时间内都能使用，你只要把配置文件中的blacklist_enabled设置为false即可，即为关闭黑名单功能

```
### 使用：
##### 1、拉取依赖 
```shell
如果你使用hyperf1.0.x版本，则 composer require phper666/jwt-auth:~1.0.1
如果你使用hyperf1.1.x版本，则 composer require phper666/jwt-auth:~2.0.1
```
##### 2、发布配置
```shell
php bin/hyperf.php jwt:publish --config
```

##### 3、jwt配置
去配置config/autoload/jwt.php文件或者在配置文件.env里配置
```shell
# 务必改为你自己的字符串
JWT_SECRET=hyperf
#token过期时间，单位为秒
JWT_TTL=60
```
更多的配置请到config/autoload/jwt.php查看
##### 4、全局路由验证
在config/autoload/middlewaress.php配置文件中加入jwt验证中间件,所有的路由都会进行token的验证，例如：
```shell
<?php
return [
    'http' => [
        Phper666\JwtAuth\Middleware\JwtAuthMiddleware:class
    ],
];
```
##### 5、局部验证
在config/routes.php文件中，想要验证的路由加入jwt验证中间件即可，例如：
```shell
<?php

Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JwtAuth\Middleware\JwtAuthMiddleware::class]]);
```
##### 6、注解的路由验证
请看官方文档：https://doc.hyperf.io/#/zh/middleware/middleware
在你想要验证的地方加入jwt验证中间件即可。

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
注意：暂时不支持传入用户对象获取token，后期会支持
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
在需要鉴权的接口,请求该接口时在HTTP头部加入
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
use \Phper666\JwtAuth\Jwt;
use Psr\Container\ContainerInterface;
class IndexController extends Controller
{
    protected $jwt;
    public function __construct(ContainerInterface $container, Jwt $jwt)
    {
        parent::__construct($container);
        $this->jwt = $jwt;
    }

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
}
```
##### 11、获取解析后的token数据
提供了一个方法getParserData来获取解析后的token数据。
例如：$this->jwt->getParserData()

##### 12、建议
目前jwt抛出的异常目前有两种类型Phper666\JwtAuth\Exception\TokenValidException和Phper666\JwtAuth\Exception\JWTException,TokenValidException异常为token验证失败的异常，会抛出401,JWTException异常会抛出500，最好你们自己在项目异常重新返回错误信息
