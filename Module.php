<?php
/**
 * CgmConfigAdmin
 *
 * @link      http://github.com/cgmartin/CgmConfigAdmin for the canonical source repository
 * @copyright Copyright (c) 2012-2013 Christopher Martin (http://cgmartin.com)
 * @license   New BSD License https://raw.github.com/cgmartin/CgmConfigAdmin/master/LICENSE
 */

namespace CgmConfigAdmin;

use CgmConfigAdmin\View\Helper\CgmFlashMessages;
use Psr\Container\ContainerInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Session\Container as SessionContainer;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ServiceProviderInterface
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    // if we're in a namespace deeper than one level we need to fix the \ in the path
                    __NAMESPACE__ => __DIR__ . '/src/' . str_replace('\\', '/' , __NAMESPACE__),
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getViewHelperConfig()
    {
        return array(
            'factories' => array(
                'cgmFlashMessages' =>  function($sm) {
                    $plugin = $sm->getServiceLocator()
                        ->get('ControllerPluginManager')
                        ->get('flashMessenger');
                    $helper = new CgmFlashMessages($plugin);
                    return $helper;
                },
            ),
        );
    }

    public function getServiceConfig()
    {
        return [
            'invokables' => [
                'cgmconfigadmin_form' => Form\ConfigOptionsForm::class,
                'cgmconfigadmin_configgroup' => Model\ConfigGroup::class,
                'cgmconfigadmin_configoption' => Model\ConfigOption::class,
                'cgmconfigadmin_configgroupfactory' => Model\ConfigGroupFactory::class,
            ],

            'shared' => [
                'cgmconfigadmin_configgroup'  => false,
                'cgmconfigadmin_configoption' => false,
            ],

            'factories' => [
                'cgmconfigadmin' => function(ContainerInterface $sm){
                    return new Service\ConfigAdmin($sm);
                },
                // Configuration options for entire module
                'cgmconfigadmin_module_options' => function($sm) {
                    $config = $sm->get('Config');
                    return new Options\ModuleOptions(
                        isset($config['cgmconfigadmin']) ? $config['cgmconfigadmin'] : []
                    );
                },

                // Session container
                'cgmconfigadmin_session' => function() {
                    $session = new SessionContainer('cgmconfigadmin');
                    return $session;
                },

                // Data Mapper for config values
                'cgmconfigadmin_configvalues_mapper' => function (ContainerInterface $sm) {
                    /** @var $options Options\ModuleOptions */
                    $options = $sm->get('cgmconfigadmin_module_options');

                    $mapper = new Entity\ConfigValuesMapper();
                    $mapper->setTableName($options->getConfigValuesTable());
                    $mapper->setDbAdapter($sm->get('cgmconfigadmin_zend_db_adapter'));
                    $mapper->setEntityPrototype(new Entity\ConfigValues);
                    $mapper->setHydrator(new Entity\ConfigValuesHydrator());
                    return $mapper;
                },

                // Example Per-user settings
                // * Requires ZfcUser *
                /*
                'cgmconfigadmin_userconfig' => function(ContainerInterface $sm) {
                    $authService = $sm->get('zfcuser_auth_service');
                    $userId = null;
                    if ($authService->hasIdentity()) {
                        $userId = $authService->getIdentity()->getId();
                    }
                    $service = new Service\ConfigAdmin('user', $userId);
                    $service->setIsPreviewEnabled(false); // To disable settings preview
                    return $service;
                }
                */
            ],
        ];
    }

}
