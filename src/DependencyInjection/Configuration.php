<?php

namespace Bash\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('bash_cache_bundle');

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
                            ->end()
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
        ;

        return $builder;
    }
}
