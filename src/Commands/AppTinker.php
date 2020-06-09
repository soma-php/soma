<?php namespace Soma\Commands;

use Soma\Command;
use Psy\Configuration;
use Psy\Shell;
use Soma\Commands\Tinker\TinkerAutoLoader;
use Soma\Commands\Tinker\TinkerCaster;

class AppTinker extends Command
{
    protected $signature = 'app:tinker';
    protected $description = 'Run code in an interactive PHP shell';

    public function handle()
    {
        $this->app->getExceptionHandler()->unregister();

        $config = new Configuration(['updateCheck' => 'never']);
        $config->getPresenter()->addCasters($this->getCasters());

        $shell = new Shell($config);
        $shell->addCommands($this->getCommands());
        
        if (isset($_ENV['COMPOSER_VENDOR_DIR'])) {
            $vendor = $_ENV['COMPOSER_VENDOR_DIR'];
        } else {
            $vendor = $this->app->getRootPath().'/../vendor';
        }

        $loader = TinkerAutoLoader::register($shell, $vendor.'/composer/autoload_classmap.php');
        
        try {
            $shell->run();
        } finally {
            $loader->unregister();
        }

        return 0;
    }

    protected function getCommands()
    {
        return config('app.tinker.commands', []);
    }

    protected function getCasters()
    {
        $casters = config('app.tinker.casters', []);

        if (! isset($casters['Soma\Application'])) {
            $casters['Soma\Application'] = [TinkerCaster::class, 'application'];
        }
        if (! isset($casters['Soma\Store'])) {
            $casters['Soma\Store'] = [TinkerCaster::class, 'store'];
        }
        if (! isset($casters['Illuminate\Support\Collection'])) {
            $casters['Illuminate\Support\Collection'] = [TinkerCaster::class, 'collection'];
        }
        if (! isset($casters['Illuminate\Database\Eloquent\Model']) && class_exists('Illuminate\Database\Eloquent\Model')) {
            $casters['Illuminate\Database\Eloquent\Model'] = [TinkerCaster::class, 'model'];
        }

        return $casters;
    }
}