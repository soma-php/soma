<?php namespace Soma;

use Psr\Container\ContainerInterface;
use Soma\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }
    
    public function install(ContainerInterface $c)
    {
        // ...
    }

    public function refresh(ContainerInterface $c)
    {
        // ...
    }

    public function uninstall(ContainerInterface $c)
    {
        // ...
    }

    public function getCommands() : array
    {
        return [];
    }

    public function getProviders() : array
    {
        return [];
    }

    public function getFactories() : array
    {
        return [];
    }

    public function getExtensions() : array
    {
        return [];
    }

    public function __call(string $method, array $parameters)
    {
        return $this->app->$method(...$parameters);
    }
}
