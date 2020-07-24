<?php

declare(strict_types=1);

namespace Phper666\JwtAuth\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Utils\Str;

/**
 * @Command
 */
class JWTGenerateSecretCommand extends HyperfCommand
{
    /**
     * 执行的命令行.
     *
     * @var string
     */
    protected $name = 'jwt:secret';

    public function configure()
    {
        parent::configure();
        $this->setDescription('设置 JWTAuth 秘钥');
    }

    public function handle(): void
    {
        $key = Str::random(64);

        if (file_exists($path = BASE_PATH . '/.env') === false) {
            $this->error('请先创建.env文件');

            return;
        }

        if (Str::contains(file_get_contents($path), 'JWT_SECRET') === false) {
            file_put_contents($path, PHP_EOL . "JWT_SECRET={$key}" . PHP_EOL, FILE_APPEND);

            $this->comment($key);

            return;
        }

        $this->error(sprintf('已经设置 JWTAuth 秘钥了, 可以使用"%s"替换', $key));
    }
}
