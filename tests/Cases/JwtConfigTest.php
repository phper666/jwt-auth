<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Cases;

use Hyperf\Di\Container;
use Hyperf\Utils\ApplicationContext;
use Mockery;
use Phper666\JwtAuth\Blacklist;
use Phper666\JwtAuth\Jwt;

/**
 * @internal
 * @coversNothing
 */
class JwtConfigTest extends AbstractTestCase
{
    public function testExample()
    {
        $jwt = $this->getContainer()->get(Jwt::class);
        $jwt->setPrefix('prefix');

        $this->assertEquals('prefix', $jwt->getPrefix());
        $this->assertEquals('prefix', $jwt->getBlacklist()->getPrefix());
    }

    protected function getContainer()
    {
        $container = Mockery::mock(Container::class);
        ApplicationContext::setContainer($container);

        $blacklist = new Blacklist();
        $container->shouldReceive('get')->with(Jwt::class)->andReturn(new Jwt($blacklist));

        return $container;
    }
}
