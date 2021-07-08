<?php

namespace Bash\Bundle\CacheBundle\DependencyInjection;

use Bash\Bundle\CacheBundle\BashRedis\Client;
use Bash\Bundle\CacheBundle\BashRedis\Factory;
use Bash\Bundle\CacheBundle\BashRedis\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class BashCacheExtension extends ConfigurableExtension
{
    protected function loadInternal(array $configs, ContainerBuilder $container): void
    {
        $factoryReference = new Reference(FactoryInterface::class);
        $container->setDefinition($factoryReference, new Definition(Factory::class));

        foreach ($configs['clients'] as $name => $arguments) {
            $definition = new Definition(Client::class);
            $definition->setFactory([$factoryReference, 'create']);
            $definition->setArguments([$arguments['$parameters'], $arguments['$options']]);
            $definition->setPublic(true);

            $container->setDefinition(sprintf('%s.%s', $this->getAlias(), $name), $definition);
        }
    }

    public function getAlias(): string
    {
        return 'bash_cache';
    }
}
