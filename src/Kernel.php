<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // RU: Ошибки типов при десериализации остаются HTTP 400, нарушения валидации — HTTP 422.
                // EN: Deserialization type errors remain HTTP 400, validation violations remain HTTP 422.
                $container->getDefinition('api_platform.state_provider.deserialize')->replaceArgument(3, null);
            }
        });
    }

    public function boot(): void
    {
        parent::boot();

        $timezone = $this->getContainer()->getParameter('timezone');
        if (empty($timezone)) {
            $timezone = date_default_timezone_get();
        }
        date_default_timezone_set($timezone);
    }
}
