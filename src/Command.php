<?php namespace Soma;

use Illuminate\Console\Command as IlluminateCommand;
use \Soma\Application;

class Command extends IlluminateCommand
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;

        parent::__construct();
    }
}