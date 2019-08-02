###基于hyperf(https://doc.hyperf.io/#/zh/README)框架的jwt鉴权(json web token)组件。
###采用基于https://github.com/lcobucci/jwt/tree/3.3进行封装。

###使用：
#####1、拉取依赖 
```shell
composer require phper666/jwt-auth
```
#####2、发布配置
```shell
jwt php bin/hyperf.php jwt:publish --config
```

#####3、jwt配置
去配置config/autoload/jwt.php文件或者在配置文件.env里配置
```shell
# 务必改为你自己的字符串
JWT_SECRET=hyperf
#token过期时间，单位为秒
JWT_TTL=60
```
#####4、全局路由验证
在config/autoload/middlewaress.php配置文件中加入jwt验证中间件,所有的路由都会进行token的验证，例如：
```shell
<?php
return [
    'http' => [
        Phper666\JwtAuth\Middleware\JwtAuthMiddleware:class
    ],
];
```
#####5、局部验证
在config/routes.php文件中，想要验证的路由加入jwt验证中间件即可，例如：
```shell
<?php

Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JwtAuth\Middleware\JwtAuthMiddleware::class]]);
```
#####6、注解的路由验证
请看官方文档：https://doc.hyperf.io/#/zh/middleware/middleware
在你想要验证的地方加入jwt验证中间件即可。

#####7、模拟登录获取token
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
#####7、路由
```shell
<?php
# 登录
Router::post('/login', 'App\Controller\IndexController@login');

# 获取数据
Router::addGroup('/v1', function () {
    Router::get('/data', 'App\Controller\IndexController@getData');
}, ['middleware' => [Phper666\JwtAuth\Middleware\JwtAuthMiddleware::class]]);
```
#####8、鉴权
在需要鉴权的接口,请求该接口时在HTTP头部加入
```shell
Authorization  Bearer token
```
#####9、结果
######请求：http://{your ip}:9501/login，下面是返回的结果
```shell
{
    "code": 0,
    "msg": "获取token成功",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1NjQ3MzgyNTgsIm5iZiI6MTU2NDczODI1OCwiZXhwIjoxNTY0NzM4MzE4LCJ1aWQiOjEsInVzZXJuYW1lIjoieHgifQ.CJL1rOqRmrKjFpYalY6Wu7JBH6vkbysfvOf-TMQgonQ"
    }
}
```
######请求：http://{your ip}:9501/v1/data
```shell
{
    "code": 0,
    "msg": "success",
    "data": {
        "a": 1
    }
}
```
