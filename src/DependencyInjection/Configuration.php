<?php

namespace Bash\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('bash_redis');

        $builder
            ->getRootNode()
                ->children()
                    ->arrayNode('clients')
                        ->addDefaultChildrenIfNoneSet('default')
                        ->useAttributeAsKey('default')
                        ->prototype('array')
                        ->children()
                            ->arrayNode('$parameters')->prototype('variable')->end()->end()
                            ->arrayNode('$options')->prototype('variable')->end()->end()
//                            ->scalarNode('type')->end()
//                            ->scalarNode('alias')->end()
//                            ->scalarNode('dsn')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

//        >arrayNode('auth_mappings')
//        ->prototype('array')
//            ->children()
//                ->scalarNode('is_bundle')->end()
//                ->scalarNode('type')->end()
//                ->scalarNode('dir')->end()
//                ->scalarNode('prefix')->end()
//                ->scalarNode('alias')->end()
//            ->end()
//        ->end()
//        ->defaultValue([])
//        ->end()

        return $builder;
    }
}
