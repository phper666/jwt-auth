<?php

declare(strict_types=1);

return [
    # 非对称加密使用字符串
    'secret' => env('JWT_SECRET'),

    /*
     * JWT 权限keys
     * 对称算法: HS256, HS384 & HS512 使用 `JWT_SECRET`.
     * 非对称算法: RS256, RS384 & RS512 / ES256, ES384 & ES512 使用下面的公钥私钥.
     */
    'keys' => [
        # 公钥，例如：'file://path/to/public/key'
        'public' => env('JWT_PUBLIC_KEY'),

        # 私钥，例如：'file://path/to/private/key'
        'private' => env('JWT_PRIVATE_KEY'),
    ],

    # token过期事件，单位为秒
    'ttl' => env('JWT_TTL', 60),

    # jwt的hearder加密算法
    'alg' => env('JWT_ALG', 'HS256'),
];
