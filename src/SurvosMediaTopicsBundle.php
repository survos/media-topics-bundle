<?php

declare(strict_types=1);

namespace Survos\MediaTopicsBundle;

use Survos\Kit\AbstractSurvosBundle;
use Survos\MediaTopics\Genres;
use Survos\MediaTopics\MediaTopics;
use Survos\MediaTopicsBundle\Command\MediaTopicsCommands;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
class SurvosMediaTopicsBundle extends AbstractSurvosBundle
{
    protected function twigNamespace(): ?string
    {
        return null;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('file')->defaultNull()->info('IPTC JSON export to read; default: the release pinned in survos/media-topics')->end()
                ->scalarNode('genre_file')->defaultNull()->info('IPTC Genre JSON export to read; default: the release pinned in survos/media-topics')->end()
                ->scalarNode('locale')->defaultValue(MediaTopics::DEFAULT_LOCALE)->info('Locale the commands show when none is given')->end()
            ->end();
    }

    /** @param array<mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $services = $container->services();
        $services->set(MediaTopics::class)
            ->factory([MediaTopics::class, 'load'])
            ->args([$config['file'] ?? MediaTopics::PINNED_FILE]);

        $services->set(Genres::class)
            ->factory([Genres::class, 'load'])
            ->args([$config['genre_file'] ?? Genres::PINNED_FILE]);

        $services->get(MediaTopicsCommands::class)
            ->arg('$defaultLocale', $config['locale'])
            ->arg('$http', new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }
}
