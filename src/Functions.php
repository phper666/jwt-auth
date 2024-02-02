<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2024/2/2
 * Time: 20:01
 */

namespace Phper666\JWTAuth;

use Hyperf\Context\ApplicationContext;

function hyperf_make(string $name, array $parameters = [])
{
    if (ApplicationContext::hasContainer()) {
        /** @var \Hyperf\Di\Container $container */
        $container = ApplicationContext::getContainer();
        if (method_exists($container, 'make')) {
            return $container->make($name, $parameters);
        }
    }
    $parameters = array_values($parameters);
    return new $name(...$parameters);
}
