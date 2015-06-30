<?php namespace Kielabokkie\BehatJsonApi\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class BehatJsonApiExtension implements Extension
{
    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return 'jsonapi';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('base_url')->defaultValue('http://localhost')->end()
            ->arrayNode('parameters')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('access_token')->end()
                    ->arrayNode('oauth')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('uri')->defaultValue('/v1/oauth/token')->end()
                            ->scalarNode('client_id')->defaultValue('testclient')->end()
                            ->scalarNode('client_secret')->defaultValue('testsecret')->end()
                            ->booleanNode('use_bearer_token')->defaultValue(false)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config'));
        $loader->load('services.yml');

        $container->setParameter('jsonapi.base_url', $config['base_url']);
        $container->setParameter('jsonapi.parameters', $config['parameters']);
    }
}
