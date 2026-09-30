<?php

declare(strict_types=1);

namespace Survos\MediaTopicsBundle\Tests;

use Survos\MediaTopicsBundle\SurvosMediaTopicsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SurvosMediaTopicsBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'test', 'test' => true]);
        });
    }

    public function getProjectDir(): string
    {
        // Per checkout: a container compiled against one vendor/ must not be reused by another.
        return sys_get_temp_dir().'/survos-media-topics-bundle-'.substr(hash('xxh64', __DIR__), 0, 8);
    }
}
