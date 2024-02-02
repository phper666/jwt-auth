<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: liyuzhao
 * Date: 2024/2/2
 * Time: 20:01
 */

namespace Phper666\JWTAuth;

if (!function_exists('make')) {
    function make(string $name, array $parameters = [])
    {
        if (class_exists('\Hyperf\Context\ApplicationContext')) {
            if (\Hyperf\Context\ApplicationContext::hasContainer()) {
                /** @var \Hyperf\Di\Container $container */
                $container = \Hyperf\Context\ApplicationContext::getContainer();
                if (method_exists($container, 'make')) {
                    return $container->make($name, $parameters);
                }
            }
        } elseif (class_exists('\Hyperf\Utils\ApplicationContext')) {
            if (\Hyperf\Utils\ApplicationContext::hasContainer()) {
                /** @var \Hyperf\Di\Container $container */
                $container = \Hyperf\Utils\ApplicationContext::getContainer();
                if (method_exists($container, 'make')) {
                    return $container->make($name, $parameters);
                }
            }
        }

        $parameters = array_values($parameters);
        return new $name(...$parameters);
    }
}
