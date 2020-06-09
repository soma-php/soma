<?php namespace Soma;

use Exception;

use Dotenv\Dotenv;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\Adapter\EnvConstAdapter;

use DI\ContainerBuilder;
use DI\FactoryInterface;
use Invoker\InvokerInterface;
use Psr\Container\ContainerInterface;
use Interop\Container\ServiceProviderInterface as InteropInterface;

use Soma\Facade;
use Soma\Store;
use Soma\Config;
use Soma\Manifest;
use Soma\Contracts\Singleton;
use Soma\Contracts\ServiceProviderInterface;

use Soma\Command;
use Soma\Console;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\OutputInterface;

use Illuminate\Events\Dispatcher;
use Symfony\Component\Finder\Finder;

class Application implements ContainerInterface, FactoryInterface, InvokerInterface, Singleton
{
    protected $stage = "";

    protected $container;
    protected $config;
    protected $events;
    protected $whoops;
    public $urls;
    public $paths;

    protected $init = false;
    
    protected $configs = [];
    protected $factories = [];
    protected $extensions = [];
    protected $providers = [];
    protected $loadedProviders = [];
    protected $aliases = [];
    protected $autoloader = false;
    protected $commands = [];

    // Singleton
    use \Soma\Traits\Singleton;

    public function __construct()
    {
        // Will be bound in container
        $this->config = new Config();
        $this->paths = new Store();
        $this->urls = new Store();       

        // Used internally only unless loading the Events Provider
        $this->events = new Dispatcher();

        // Used for error handling and debugging
        $this->whoops = new \Whoops\Run;
    }

    public function export()
    {
        return [
            'stage' => $this->stage,
            'configs' => $this->configs,
            'paths' => $this->paths,
            'urls' => $this->urls,
            'providers' => $this->providers,
            'aliases' => $this->aliases,
            'commands' => $this->commands,
        ];
    }

    public function init($rootPath = null, $rootUrl = null)
    {
        if ($this->init) {
            throw new Exception("You cannot re-init the application");
        }

        // Load environment and set base URIs
        $envPath = $rootPath ?? env('APP_PATH', __DIR__);

        if (file_exists($env = $envPath.'/.env') || file_exists($env = dirname($envPath).'/.env')) {
            $repository = RepositoryBuilder::create()
                ->withReaders([new EnvConstAdapter()])
                ->immutable()
                ->make();

            $dotenv = Dotenv::create($repository, dirname($env));
            $dotenv->load();
        }

        if (! is_null($rootUrl = $rootUrl ?? env('APP_URL', null))) {
            $this->setRootUrl($rootUrl);
        }
        if (! is_null($rootPath = $rootPath ?? env('APP_PATH', null))) {
            $this->setRootPath($rootPath);
        } else {
            throw new Exception("You must set a root path");
        }
        
        $this->setStage(env('APP_STAGE', 'production'));
        $this->events->dispatch('app.environment');

        // Error handling
        $this->configureErrorHandling();

        // Configure resources
        $storageDir = realpath(env('APP_STORAGE', false)) ?: $this->paths->get('storage');

        if (! $storageDir) {
            throw new Exception('You have not set a storage directory');
        }

        $this->paths->set('storage', $storageDir);
        $this->paths->set('cache', $storageDir.'/cache');
        $this->paths->set('installation', $storageDir.'/app.php');

        $this->paths->set('cache.app', $storageDir.'/cache/app');
        $this->paths->set('cache.manifests', Manifest::$cacheDir = $storageDir.'/cache/app/manifests');
        $this->paths->set('cache.config', $storageDir.'/cache/app/config.php');
        $this->paths->set('cache.container', $storageDir.'/cache/app/container');
        $this->paths->set('storage.public', $storageDir.'/public');
        $this->paths->set('storage.link', $rootPath.'/storage');
        $this->paths->set('cache.public', $storageDir.'/public/cache');

        // Create "extensions" namespace in public directory to avoid routing issues when
        // linking modules that publish assets
        $this->paths->set('extensions.public', $rootPath.'/extensions');

        // Set URLs if a root has been configured
        if ($rootUrl = $this->getRootUrl()) {
            $this->urls->set('extensions.public', $rootUrl.'/extensions');
            $this->urls->set('storage.public', $rootUrl.'/storage');
            $this->urls->set('cache.public', $rootUrl.'/storage/cache');
        }
    
        // Set alias autoloader
        spl_autoload_register([$this, 'aliasLoader'], true, false);

        // Register config
        if (env('APP_CONFIG') && ! $this->paths->get('config')) {
            $this->paths->set('config', realpath(env('APP_CONFIG')));
        }
        if ($config = $this->paths->get('config')) {
            $this->registerConfig($config);
        }

        $this->loadConfig();
        $this->events->dispatch('app.config');

        // Load resources from config
        if ($paths = $this->config->get('app.paths', [])) {
            $this->paths->put($paths);
        }
        if ($urls = $this->config->get('app.urls', [])) {
            $this->urls->put($urls);
        }

        // Configure application from config
        if ($aliases = $this->config->get('app.aliases', [])) {
            $this->registerAlias($aliases);
        }
        if ($providers = $this->config->get('app.providers', [])) {
            $this->registerProvider($providers);
        }
        if ($definitions = $this->config->get('app.definitions', [])) {
            $this->factories['config'] = $definitions;
        }
        if ($this->commands = array_merge($this->commands, $this->config->get('app.commands', []))) {
            $this->factories['commands'] = [];

            foreach ($this->commands as $class) {
                $this->factories['commands'][$class] = \DI\create()->constructor(\DI\get('app'));
            }
        }

        // Set timezone
        $timezone = $this->config->get('app.timezone', env('APP_TIMEZONE', null));

        date_default_timezone_set($timezone ?? @date_default_timezone_get());

        // Unregister error handler if not wanted
        if (! $this->config->get('app.catch-exceptions', true)) {
            $this->whoops->unregister();
        }

        // Define date constant
        if (! defined('DATE_FORMAT') && $format = $this->config->get('app.date-format', false)) {
            define('DATE_FORMAT', $format);
        }

        // Configure container
        $builder = new ContainerBuilder();
        $this->factories['internal'] = $this->getInternalDefinitions();

        $builder->addDefinitions(...array_values($this->factories));
        $builder->addDefinitions(...array_values($this->extensions));
        $builder->useAutowiring($this->config->get('app.container.autowiring', false));
        $builder->useAnnotations($this->config->get('app.container.annotations', false));
        $builder->ignorePhpDocErrors($this->config->get('app.container.ignore-phpdoc-errors', false));

        // Enable container definition caching
        if ($this->isPerformanceMode()) {
            $cacheDir = $this->paths->get('cache.container');
            $builder->enableCompilation($cacheDir);
            $builder->writeProxiesToFile(true, $cacheDir.'/proxies');
        }
        
        $this->container = $builder->build();

        Facade::clearResolvedInstances();
        Facade::setFacadeContainer($this->container);

        $this->events->dispatch('app.container');

        // Load providers and bind systems into container
        $this->loadProviders();
        $this->events->dispatch('app.providers');

        // Hook to allow for modules to easily add functionality before ready-event
        $this->events->dispatch('app.extensions');

        // Execute ready methods on providers
        foreach (array_intersect_key($this->providers, $this->loadedProviders) as $class => $provider) {
            if (method_exists($provider, 'ready')) {
                $provider->ready($this->container);
            }
        }

        $this->events->dispatch('app.ready');
        $this->init = true;

        return $this;
    }

    public function install()
    {
        // Make sure directories exists
        $dirs = [
            'storage',
            'cache',
            'cache.app',
            'cache.manifests',
            'cache.container',
            'storage.public',
            'cache.public',
            'extensions.public',
        ];

        foreach ($dirs as $name) {
            ensure_dir_exists($this->paths->get($name));
        }

        // Symlink public folder
        if (! file_exists($link = $this->paths->get('storage.link'))) {
            symlink(realpath($this->paths->get('storage.public')), $link);
        }

        return true;
    }

    protected function configureErrorHandling()
    {
        // Show debug for web or ajax request
        if ($this->isDebug()) {
            if ($this->isWebRequest()) {
                $this->whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
            }
            if ($this->isAjaxRequest()) {
                $handler = new \Whoops\Handler\JsonResponseHandler();
                $handler->addTraceToOutput(true);
                
                $this->whoops->pushHandler($handler);
            }
        }
        // CLI
        $plainTextHandler = new \Whoops\Handler\PlainTextHandler();

        if (! $this->isCommandLine()) {
            $plainTextHandler->loggerOnly(true);
        }
        
        $this->whoops->pushHandler($plainTextHandler);
        $this->whoops->register();
    }

    public function paths()
    {
        if ($this->container) {
            return $this->container->get('paths');
        } else {
            return $this->paths;
        }
    }

    public function setRootPath($path)
    {
        $this->paths['root'] = rtrim(realpath($path), '/');

        return $this;
    }

    public function getRootPath($path = null)
    {
        $base = $this->paths['root'] ?? '';

        return (is_null($path)) ? $base : $base.'/'.$path;
    }

    public function urls()
    {
        if ($this->container) {
            return $this->container->get('urls');
        } else {
            return $this->urls;
        }
    }

    public function setRootUrl($url)
    {
        $this->urls['root'] = rtrim($url, '/');

        return $this;
    }

    public function getRootUrl($url = null)
    {
        $base = $this->urls['root'] ?? '';

        return (is_null($url)) ? $base : $base.'/'.$url;
    }

    public function getContainerBuilder()
    {
        return $this->builder;
    }

    public function getExceptionHandler()
    {
        return $this->whoops;
    }

    public function loadProviders()
    {
        if (! $this->container) {
            throw new Exception("A PSR-11 container must be set before loading providers");
        }

        // Load all unloaded providers
        $unloaded = array_diff_key($this->providers, $this->loadedProviders);
        $unloaded = array_intersect_key($this->providers, $unloaded);

        // Allow soft implementation of Illuminate-like service provider registration,
        // this can unfortunately not be done prior to the container builder compilation
        // since the register method expects an instance of a container
        foreach ($unloaded as $class => $provider) {
            if (method_exists($provider, 'register')) {
                $provider->register($this->container);
            }
        }

        // Run boot method (soft implementation allowed)
        foreach ($unloaded as $class => $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($this->container);
            }

            // Mark provider as loaded
            $this->loadedProviders[$class] = true;
            $this->events->dispatch($class.'.loaded');
        }

        return $this;
    }

    public function aliasLoader($alias)
    {
        // Try FQDN
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
        // Try base class name
        else {
            $name = basename(str_replace('\\', '/', $alias));

            if (isset($this->aliases[$name])) {
                return class_alias($this->aliases[$name], $alias);
            }
        }

        return false;
    }

    public function getEventDispatcher()
    {
        return $this->events;
    }

    protected function getInternalDefinitions()
    {
        return [
            // Container
            'container' => function (ContainerInterface $c) {
                return $c->get('app')->getContainer();
            },
            ContainerInterface::class => \DI\get('container'),
            // Config
            'config' => function (ContainerInterface $c) {
                return $c->get('app')->getConfig();
            },
            Config::class => \DI\get('config'),
            // Application
            'app' => function (ContainerInterface $c) {
                return Application::getInstance();
            },
            self::class => \DI\get('app'),
            // Resources
            'paths' => function (ContainerInterface $c) {
                return $c->get('app')->paths;
            },
            'urls' => function (ContainerInterface $c) {
                return $c->get('app')->urls;
            },
            // Error handler
            'error' => function (ContainerInterface $c) {
                return $c->get('app')->getExceptionHandler();
            },
            \Whoops\Run::class => \DI\get('error'),
            // Console
            'console' => function (ContainerInterface $c) {
                $app = $c->get('app');
                $config = $c->get('config');

                $name = $config->get('app.name', 'Soma');
                $version = $config->get('app.version', 'dev');
                $console = new Console($name, $version, $app);
                
                $console->resolveCommands($app->getCommands());

                return $console;
            },
            OutputStyle::class => \DI\create()->constructor(InputInterface::class, OutputInterface::class),
        ];
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function registerPath($name, $path = '')
    {
        return $this->paths->set($name, $path);
    }

    public function registerUrl($name, $url = '')
    {
        return $this->urls->set($name, $url);
    }

    protected function normalizeProviders($providers)
    {
        $providers = Arr::wrap($providers);

        // Normalize associative array with providers
        foreach ($providers as $key => $provider) {
            $class = (! is_string($provider)) ? get_class($provider) : $provider;
            $provider = (is_string($provider)) ? new $provider($this) : $provider;

            unset($providers[$key]);
            $providers[$class] = $provider;
        }

        return $providers;
    }

    public function getProviders()
    {
        return $this->providers;
    }

    public function registerProvider($providers)
    {
        $providers = $this->normalizeProviders($providers);

        // Fetch additional provider definitions
        foreach ($providers as $class => $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                $providers = array_merge($providers, $this->normalizeProviders($provider->getProviders()));
            }
        }

        // Register each provider
        foreach ($providers as $class => $provider) {
            // Ignore already registered providers
            if (isset($this->providers[$class])) {
                continue;
            }

            // Enable registering commands via the service provider
            if ($provider instanceof ServiceProviderInterface && $commands = $provider->getCommands()) {
                $this->registerCommand($commands);
            }

            // Enable configuring the builder via the container interop interface
            if ($provider instanceof InteropInterface) {
                if (! $this->init) {
                    if ($factories = $provider->getFactories()) {
                        $this->factories[$class] = $factories;
                    }
                    
                    foreach ($provider->getExtensions() as $id => $ext) {
                        if (! $ext instanceof Definition) {
                            $this->extensions[$class][$id] = \DI\decorate($ext);
                        } else {
                            $this->extensions[$class][$id] = $ext;
                        }
                    }
                }
                else {
                    foreach ($provider->getFactories() as $id => $factory) {
                        $this->container->set($id, $factory);
                    }

                    foreach ($provider->getExtensions() as $id => $ext) {
                        if (! $ext instanceof Definition) {
                            $this->container->set($id, \DI\decorate($ext));
                        } else {
                            $this->container->set($id, $ext);
                        }
                    }
                }
            }

            // Trigger event
            $this->providers[$class] = $provider;
            $this->events->dispatch($class.'.registered');
        }

        if ($this->init) {
            $this->loadProviders();
        }

        return $this;
    }

    public function registerAlias($alias, $class = null)
    {
        if (is_array($alias)) {
            $this->aliases = array_merge($this->aliases, $alias);
        }
        else {
            $this->aliases[$alias] = $class;
        }

        return $this;
    }

    public function registerConfig($file)
    {
        $this->configs = array_merge($this->configs, Arr::wrap($file));

        if ($this->init) {
            $this->loadConfig(true);
        }

        return $this;
    }

    public function getConsole()
    {
        if (! $this->container) {
            throw new Exception("You must first initialize the application");
        }

        return $this->container->get('console');
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function runCommand($command)
    {
        $console = $this->getConsole();
        
        return $console->call($command);
    }

    public function registerCommand($commands)
    {
        if ($this->init) {
            foreach (Arr::wrap($commands) as $class) {
                if (! $this->container->has($class)) {
                    $this->container->set($class, \DI\create()->constructor(\DI\get('app')));
                    $this->commands[] = $class;
                }
            }
        }
        else {
            $this->commands = array_unique(array_merge($this->commands, Arr::wrap($commands)));
        }

        return $this;
    }

    public function isStage($stage)
    {
        return (strtolower($this->stage) == strtolower($stage)) ? true : false;
    }

    public function setStage($stage)
    {
        $this->stage = $stage;

        return $this;
    }

    public function getStage()
    {
        return $this->stage;
    }

    public function clearCache($key = "")
    {
        // Clear the application's file cache
        if ($this->init) {
            // Clear all cache
            if (empty($key)) {
                $all = $this->paths->all();

                $cachePaths = array_filter($all, function ($value, $key) {
                    return Str::startsWith($key, 'cache.');
                }, ARRAY_FILTER_USE_BOTH);

                uksort($cachePaths, 'version_compare');

                foreach ($cachePaths as $id => $path) {
                    if (is_link($path)) {
                        $path = realpath($path);
                    }

                    if (is_dir($path)) {
                        empty_dir($path, true, true);
                    } elseif (is_file($path)) {
                        unlink($path);
                    }
                }

                empty_dir($this->paths->get('cache'), false);
                $this->events->dispatch('cache.app.clear');
            }
            // Clear specific cache
            else {
                // Remove defined cache path
                if ($path = $this->paths->get('cache.'.$key)) {
                    if (is_link($path)) {
                        $path = realpath($path);
                    }

                    if (is_dir($path)) {
                        empty_dir($path, true, true);
                    } elseif (is_file($path)) {
                        unlink($path);
                    }
                }

                $this->events->dispatch('cache.'.$key.'.clear');
            }
        }

        $this->events->dispatch('app.cache.clear');

        return $this;
    }

    public function loadConfig($force = false)
    {
        $cachePath = $this->paths->get('cache.config');

        // If a caching and cache file exist, load it instead
        if (! $force && $this->isPerformanceMode() && file_exists($cachePath)) {
            $this->config->replace(Manifest::parseFile($cachePath));
        }
        else {
            foreach ($this->configs as $path) {
                $path = realpath($path);

                // Make sure that the config file/dir exists
                if (! file_exists($path)) {
                    throw new Exception("Config path doesn't exist: ".$path);
                }

                // Process config directory
                if (is_dir($path)) {
                    // Load all config files
                    $files = (new Finder)->in($path)
                        ->name('/\.(php|json|yml|ini)$/')
                        ->followLinks()
                        ->files();

                    foreach ($files as $file) {
                        // Create a config key for each file
                        $filePath = $file->getPath().'/'.$file->getFilename();
                        $fileExtension = $file->getExtension();
                        $relPath = rel_path($filePath, $path);
                        $configKey = str_replace('/', '.', $file->getBasename('.'.$fileExtension));

                        $this->config->set($configKey, Manifest::parseFile($filePath));
                    }
                }
                // Process single file
                else {
                    $fileExtension = pathinfo($path, PATHINFO_EXTENSION);
                    $configKey = basename($path, '.'.$fileExtension);

                    $this->config->set($configKey, Manifest::parseFile($path));
                }
            }

            // Cache the config file
            if ($this->isPerformanceMode() && is_dir(dirname($cachePath))) {
                Manifest::dumpFile($cachePath, $this->config->all());
            }
        }

        return $this;
    }

    public function isPerformanceMode()
    {
        return env('APP_OPTIMIZE', true);
    }

    public function isDebug()
    {
        return env('APP_DEBUG', false);
    }

    public function isAjaxRequest()
    {
        return \Whoops\Util\Misc::isAjaxRequest();
    }

    public function isWebRequest()
    {
        if (! $this->isCommandLine() && ! $this->isAjaxRequest()) {
            return true;
        }

        return false;
    }

    public function isCommandLine()
    {
        return \Whoops\Util\Misc::isCommandLine();
    }

    public function has($id)
    {
        if (! $this->container) {
            throw new Exception("You must first initialize the application");
        }

        return $this->container->has($id);
    }

    public function get($id)
    {
        if (! $this->container) {
            throw new Exception("You must first initialize the application");
        }

        return $this->container->get($id);
    }

    public function make($name, array $parameters = [])
    {
        if (! $this->container) {
            throw new Exception("You must first initialize the application");
        }

        return $this->container->make($name, $parameters);
    }

    public function call($callable, array $parameters = [])
    {
        if (! $this->container) {
            throw new Exception("You must first initialize the application");
        }

        return $this->container->call($callable, $parameters);
    }
}
