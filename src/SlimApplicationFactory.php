<?php

namespace BrandEmbassy\Slim;

use BrandEmbassy\Slim\Request\RequestInterface;
use BrandEmbassy\Slim\Response\ResponseInterface;
use Closure;
use Nette\DI\Container;
use Slim\Collection;

final class SlimApplicationFactory
{

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var Container
     */
    private $container;

    /**
     * @param array $configuration
     * @param Container $container
     */
    public function __construct(array $configuration, Container $container)
    {
        $this->configuration = $configuration;
        $this->container = $container;
    }

    /**
     * @return SlimApp
     */
    public function create()
    {
        $app = new SlimApp($this->configuration);

        $configuration = $this->getConfiguration($this->configuration['apiDefinitionKey']);

        foreach ($configuration['routes'] as $apiName => $api) {
            $this->registerApis($app, $api, $apiName);
        }

        /** @var Collection $settings */
        $settings = $app->getContainer()['settings'];
        if ($settings->get('removeDefaultHandlers') === true) {
            $this->removeDefaultSlimErrorHandlers($app);
        }

        if (isset($configuration['handlers'])) {
            $this->registerHandlers($app, $configuration['handlers']);
        }

        return $app;

    }

    /**
     * @param string $configurationCode
     * @return array
     */
    private function getConfiguration($configurationCode)
    {
        $configuration = $this->container->getParameters()[$configurationCode];

        if (!is_array($configuration)) {
            throw new \LogicException(sprintf('Missing %s configuration', $configurationCode));
        }

        $this->validateConfiguration($configuration, $configurationCode, 'routes', 'array');
        $this->validateConfiguration($configuration, $configurationCode, 'handlers', 'array');

        return $configuration;
    }

    /**
     * @param array $configuration
     * @param string $configurationCode
     * @param string $name
     * @param string $type
     */
    private function validateConfiguration(array $configuration, $configurationCode, $name, $type)
    {
        if (!isset($configuration[$name]) || gettype($configuration[$name]) !== $type) {
            throw new \LogicException(
                sprintf(
                    'Missing or empty %s.%s configuration (has to be %s, but is %s)',
                    $configurationCode,
                    $name,
                    $type,
                    gettype($configuration[$name])
                )
            );
        }
    }

    /**
     * @param string $serviceName
     * @return Closure
     */
    private function getServiceProvider($serviceName)
    {
        return function () use ($serviceName) {
            /** @var object|null $service */
            $service = $this->container->getByType($serviceName, false);
            if ($service === null) {
                $service = $this->container->getService($serviceName);
            }

            return $service;
        };
    }

    /**
     * @param SlimApp $app
     */
    private function removeDefaultSlimErrorHandlers(SlimApp $app)
    {
        $app->getContainer()['phpErrorHandler'] = function () {
            return function (RequestInterface $request, ResponseInterface $response, \Exception $e) {
                throw $e;
            };
        };
    }

    /**
     * @param SlimApp $app
     * @param array $handlers
     */
    private function registerHandlers(SlimApp $app, array $handlers)
    {
        foreach ($handlers as $handlerName => $handlerClass) {
            $app->getContainer()[$handlerName . 'Handler'] = $this->getServiceProvider($handlerClass);
        }
    }

    /**
     * @param SlimApp $app
     * @param string $serviceName
     */
    private function registerServiceIntoContainer(SlimApp $app, $serviceName)
    {
        $app->getContainer()[$serviceName] = $this->getServiceProvider($serviceName);
    }

    /**
     * @param SlimApp $app
     * @param array $api
     * @param string $apiName
     */
    private function registerApis(SlimApp $app, array $api, $apiName)
    {
        foreach ($api as $version => $routes) {
            $this->registerApi($app, $apiName, $version, $routes);
        }
    }

    /**
     * @param SlimApp $app
     * @param string $apiName
     * @param string $version
     * @param array $routes
     */
    private function registerApi(SlimApp $app, $apiName, $version, array $routes)
    {
        foreach ($routes as $routeName => $routeData) {
            $urlPattern = $this->createUrlPattern($apiName, $version, $routeName);

            if (isset($routeData['type']) && $routeData['type'] === 'controller') {
                $this->registerControllerRoute($app, $urlPattern, $routeData);
            } else {
                $this->registerInvokableActionRoutes($app, $routeData, $urlPattern);
            }
        }
    }

    /**
     * @deprecated Do not use Controllers, use Invokable Action classes (use MiddleWareInterface)
     *
     * @param SlimApp $app
     * @param string $urlPattern
     * @param array $routeData
     */
    private function registerControllerRoute(SlimApp $app, $urlPattern, array $routeData)
    {
        $this->registerServiceIntoContainer($app, $routeData['service']);

        foreach ($routeData['methods'] as $method => $action) {
            $app->map([$method], $urlPattern, $routeData['service'] . ':' . $action)
                ->add($routeData['service'] . ':' . 'middleware');
        }
    }

    /**
     * @param SlimApp $app
     * @param array $routeData
     * @param string $urlPattern
     */
    private function registerInvokableActionRoutes(SlimApp $app, array $routeData, $urlPattern)
    {
        foreach ($routeData as $method => $config) {
            $service = $config['service'];

            $this->registerServiceIntoContainer($app, $service);
            $routeToAdd = $app->map([$method], $urlPattern, $service);

            if (isset($config['middleware']) && count($config['middleware']) > 0) {
                foreach ($config['middleware'] as $middleware) {
                    $container = $app->getContainer();

                    if (!$container->has($middleware)) {
                        $this->registerServiceIntoContainer($app, $middleware);
                    }

                    $routeToAdd->add($middleware);
                }
            }
        }
    }

    /**
     * @param string $apiName
     * @param string $version
     * @param string $routeName
     * @return string
     */
    private function createUrlPattern($apiName, $version, $routeName)
    {
        return sprintf('/%s/%s%s', $apiName, $version, $routeName);
    }

}
