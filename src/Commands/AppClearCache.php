<?php namespace Soma\Commands;

use Soma\Command;

class AppClearCache extends Command
{
    protected $signature = 'app:clear-cache {key? : Either a defined path under the cache namespace or an event to be triggered (cache.[key].clear)}';
    protected $description = 'Clears the application cache';

    public function handle()
    {
        $key = $this->argument('key', null);

        $this->info('Clearing cache'.($key ? '.'.$key : '').'...');

        $this->app->clearCache($key);

        $this->info('Done!');

        return 0;
    }
}