<?php

namespace Changelog;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\XmlRpc\Client as XmlRpcClient;

class Module implements ConsoleUsageProviderInterface
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array('namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
            ))
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array('factories' => array(
            'Changelog\XmlRpc\Client' => function ($services) {
                $config   = $services->get('Config');
                if (!isset($config['changelog'])) {
                    throw new RuntimeException(
                        'Expecting a "changelog" key in configuration; none found'
                    );
                }
                $config = $config['changelog'];
                if (!isset($config['jira']) || !is_array($config['jira'])) {
                    throw new RuntimeException(
                        'Expecting an array of JIRA credentials in "changelog" configuration; none found'
                    );
                }
                $jiraUrl = isset($config['jira']['url']) ? $config['jira']['url'] : 'http://framework.zend.com/issues/rpc/xmlrpc';
                $cxn    = new XmlRpcClient($jiraUrl);
                $client = $cxn->getProxy('jira1');
                return $client;
            },
            'Changlog\Jira\Auth' => function ($services) {
                if (!isset($config['changelog'])) {
                    throw new RuntimeException(
                        'Expecting a "changelog" key in configuration; none found'
                    );
                }
                $config = $config['changelog'];
                if (!isset($config['jira']) || !is_array($config['jira'])) {
                    throw new RuntimeException(
                        'Expecting an array of JIRA credentials in "changelog" configuration; none found'
                    );
                }
                $jiraCredentials = $config['jira'];

                $client = $services->get('Changlog\XmlRpc\Client');
                $auth   = $client->login(
                    $jiraCredentials['username'], 
                    $jiraCredentials['password']
                );
                return $auth;
            },
        ));
    }

    public function getControllerConfig()
    {
        return array('factories' => array(
            'Changelog\Controller\Changelog' => function ($controllers) {
                $services = $controllers->getServiceLocator();
                $config   = $services->get('Config');
                if (!isset($config['changelog'])) {
                    throw new ServiceNotCreatedException(
                        'Could not create ChangelogController; missing configuration'
                    );
                }

                $zf1data = include $config['changelog']['zf1_file'];
                if (!$zf1data || !is_array($zf1data)) {
                    throw new ServiceNotCreatedException(
                        'Could not create ChangelogController; invalid or missing ZF1 changelog data'
                    );
                }

                $zf2data = include $config['changelog']['zf2_file'];
                if (!$zf2data || !is_array($zf2data)) {
                    throw new ServiceNotCreatedException(
                        'Could not create ChangelogController; invalid or missing ZF2 changelog data'
                    );
                }

                $data = array_merge($zf1data, $zf2data);

                $controller = new Controller\ChangelogController();
                $controller->setChangelogData($data);
                return $controller;
            },
            'Changelog\Controller\Fetch' => function ($controllers) {
                $services = $controllers->getServiceLocator();
                $config   = $services->get('Config');
                if (!isset($config['changelog'])) {
                    throw new RuntimeException(
                        'Expecting a "changelog" key in configuration; none found'
                    );
                }
                $config = $config['changelog'];
                if (!isset($config['zf1_file'])) {
                    throw new RuntimeException(
                        'Expecting an "file" key in "changelog" configuration; none found'
                    );
                }
                $changelogDataFile = $config['zf1_file'];

                $controller = new Controller\FetchController();
                $controller->setConsole($services->get('Console'));
                $controller->setZf1DataFile($changelogDataFile);
                $controller->setXmlRpcClient($services->get('Changelog\XmlRpc\Client'));
                $controller->setJiraAuth($services->get('Changelog\Jira\Auth'));

                return $controller;
            },
        ));
    }

    public function getConsoleUsage(Console $console)
    {
        return array(
            'changelog fetch (zf1|zf2)' => 'Retrieve and process ZF1 or ZF2 changelog',
        );
    }
}
