<?php namespace Soma\Traits;

trait Singleton
{
    public static $instance;

    public static function getInstance()
    {
        if (! is_null(self::$instance)) {
            return self::$instance;
        }

        return self::$instance = new static();
    }
}
