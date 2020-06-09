<?php namespace Soma\Commands;

use Exception;
use Soma\Command;
use Soma\Manifest;
use Soma\Contracts\ServiceProviderInterface;

class AppRefresh extends Command
{
    protected $signature = 'app:refresh {provider?}';
    protected $description = 'Refresh service providers';

    public function handle()
    {
        $installed = new Manifest(get_path('installation'));
        $providers = $this->app->getProviders();
        $only = ltrim($this->argument('provider', ''), '\\');

        // Selected providers to process
        if ($only) {
            $this->info('Executing refresh on '.$only.'...');

            $providers = array_filter($providers, function($class) use ($only) {
                return ($class == $only) ? true : false;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($providers) && class_exists($only)) {
                $providers[$only] = new $only($this->app);
            }
        } else {
            $this->info('Executing refresh on installed service providers...');
        }
        
        // Make sure Application is properly configured
        // but also force refresh the config
        $this->app->loadConfig(true);
        $this->app->install();

        // Process service providers
        if (empty($providers)) {
            $this->error("No registered service provider found");
            return;
        }

        // Process all service providers
        foreach ($providers as $class => $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                if ($installed[$class] ?? false) {
                    $this->line('Refreshing '.$class);

                    try {
                        $provider->refresh($this->app);
                    }
                    catch (Exception $e) {
                        $this->error('Refresh failed for '.$class.':');
                        $this->line(PHP_EOL.$e->getMessage().PHP_EOL);
                        return;
                    }
                }
            }
        }

        $installed->save();

        $this->info('Refresh successful!');

        return 0;
    }
}