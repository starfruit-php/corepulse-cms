<?php

namespace CorepulseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('corepulse');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->variableNode('security_firewall')->end()
                ->variableNode('api_firewall')->end()
                ->arrayNode('sidebar')
                    ->children()
                        ->booleanNode('home')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('document')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('asset')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('object')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('translation')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('report')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('catalog')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('catalogDocument')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('plausible')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('user')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('role')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('seo')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('emails')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('sitemap')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('httpError')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('indexing')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('cache')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('import')
                            ->defaultTrue()
                        ->end()

                    ->end()
                ->end()
                ->arrayNode('event_listener')
                    ->children()
                        ->booleanNode('update_indexing')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('inspection_index')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
