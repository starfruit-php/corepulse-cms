<?php

namespace CorepulseBundle\DependencyInjection;

use Pimcore\Bundle\CoreBundle\DependencyInjection\ConfigurationHelper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class CorepulseExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
        $container->setParameter('corepulse_admin.sidebar', $config['sidebar'] ?? null);
        $container->setParameter('corepulse.event_listener.update_indexing', isset($config['event_listener']) && isset($config['event_listener']['update_indexing']) ?  $config['event_listener']['update_indexing'] : null);
        $container->setParameter('corepulse.event_listener.inspection_index', isset($config['event_listener']) && isset($config['event_listener']['inspection_index']) ?  $config['event_listener']['inspection_index'] : null);
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('corepulse_admin.api_firewall_settings')) {
            $containerConfig = ConfigurationHelper::getConfigNodeFromSymfonyTree($container, 'corepulse');
            $container->setParameter('corepulse_admin.api_firewall_settings', $containerConfig['api_firewall']);
        }
    }
}
