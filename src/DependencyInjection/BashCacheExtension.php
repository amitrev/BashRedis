<?php

namespace Bash\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class BashCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('bash_cache.main', $config['main']);
        $container->setParameter('bash_cache.counter', $config['counter']);

        $container->setParameter('bash_cache.expires.short', $config['expires']['short'] ?? 60);
        $container->setParameter('bash_cache.expires.medium', $config['expires']['medium'] ?? 3600);
        $container->setParameter('bash_cache.expires.long', $config['expires']['long'] ?? 86400);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

}
