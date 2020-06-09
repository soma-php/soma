<?php namespace Soma\Facades;

class App extends \Soma\Facade
{
    protected static function getFacadeAccessor()
    {
        return 'app';
    }
}
