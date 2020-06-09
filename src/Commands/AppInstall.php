<?php namespace Soma\Commands;

use Exception;
use Soma\Command;
use Soma\Manifest;
use Soma\Contracts\ServiceProviderInterface;

class AppInstall extends Command
{
    protected $signature = 'app:install {provider?}';
    protected $description = 'Run installation of not yet configured service providers';

    public function handle()
    {
        $installed = new Manifest(get_path('installation'));
        $providers = $this->app->getProviders();
        $only = ltrim($this->argument('provider', ''), '\\');

        // Selected providers to process
        if ($only) {
            $this->info('Executing install on '.$only.'...');

            $providers = array_filter($providers, function($class) use ($only) {
                return ($class == $only) ? true : false;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($providers) && class_exists($only)) {
                $providers[$only] = new $only($this->app);
            }
        } else {
            $this->info('Executing install on registered service providers...');
        }
        
        // First make sure Application is properly configured
        $this->app->install();

        // Process service providers
        if (empty($providers)) {
            $this->error("No registered service provider found");
            return;
        }

        foreach ($providers as $class => $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                if (! ($installed[$class] ?? false)) {
                    $this->line('Installing '.$class);

                    try {
                        $provider->install($this->app);
                        $installed[$class] = true;
                    }
                    catch (Exception $e) {
                        $this->error('Installation failed for '.$class.':');
                        $this->line(PHP_EOL.$e->getMessage().PHP_EOL);
                        return;
                    }
                }
            }
        }

        $installed->save();

        $this->info('Installation successful!');

        return 0;
    }
}