<?php

namespace Bash\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('bash_cache');

        $builder
            ->getRootNode()
                ->children()
                    ->arrayNode('main')
                        ->children()
                            ->scalarNode('scheme')->end()
                            ->scalarNode('path')->end()
                            ->scalarNode('host')->end()
                            ->scalarNode('port')->end()
                            ->scalarNode('db')->end()
                            ->scalarNode('timeout')->end()
                            ->scalarNode('prefix')->end()
                            ->scalarNode('persistent')->end()
                        ->end()
                    ->end()
                    ->arrayNode('counter')
                        ->children()
                            ->scalarNode('scheme')->end()
                            ->scalarNode('path')->end()
                            ->scalarNode('host')->end()
                            ->scalarNode('port')->end()
                            ->scalarNode('db')->end()
                            ->scalarNode('timeout')->end()
                            ->scalarNode('prefix')->end()
                            ->scalarNode('persistent')->end()
            ->end()
                    ->end()
                    ->arrayNode('expires')
                        ->children()
                            ->scalarNode('short')->end()
                            ->scalarNode('medium')->end()
                            ->scalarNode('long')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $builder;
    }
}
