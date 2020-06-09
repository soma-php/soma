<?php namespace Soma\Contracts;

use Psr\Container\ContainerInterface;
use Interop\Container\ServiceProviderInterface as InteropInterface;

interface ServiceProviderInterface extends InteropInterface
{
    public function install(ContainerInterface $c);

    public function refresh(ContainerInterface $c);

    public function uninstall(ContainerInterface $c);

    public function getProviders() : array;

    public function getCommands() : array;
}
