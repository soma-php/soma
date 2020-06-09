<?php namespace Soma\Providers;

use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

class EventsProvider extends ServiceProvider
{
    public function getFactories() : array
    {
        return [
            'events' => function(ContainerInterface $c) {
                return $c->get('app')->getEventDispatcher();
            },
        ];
    }
}