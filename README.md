![SOMA Logo](https://raw.githubusercontent.com/soma-php/resources/master/logo-256.png)

[![GitHub license](https://img.shields.io/github/license/soma-php/soma.svg)](https://github.com/soma-php/soma/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/soma-php/soma.svg)](https://github.com/soma-php/soma/releases/)
___

SOMA (*Slim Open Modular Framework*) is a lightweight PHP micro-framework, designed to provide the bare essentials and lay a foundation for a developer to modularly put together their application without the framework getting in the way. `soma/soma` is the core that provides config loading, DI container, environment loading, service providers, facades, class aliases and a command line interface.

## Installation

*Soma requires composer for dependency management*

```sh
composer require soma/soma
```

If you want to start an entirely new project rather than integrating it into your current solution you can use [soma/project](https://github.com/soma-php/project) as scaffolding:

```sh
composer create-project soma/project [project-directory]
```

The required paths need to be created by the framework before you can run your application. You can do so either by executing `install()` on a configured instance of `Application` or by running the `app:install` command.

## Usage

### Setup
All essential configuration can be set via the `Soma\Application` instance or by creating a `.env` file in the root of your project.

```sh
APP_URL="http://localhost:8000"
APP_STAGE="development"
APP_TIMEZONE="Europe/Stockholm"
APP_DEBUG=true
APP_OPTIMIZE=false
APP_CONFIG="/absolute/path/to/your/config/folder"
APP_STORAGE="/absolute/path/to/your/storage/folder"
```

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use Soma\Application;

$app = Application::getInstance()
    ->setRootPath(__DIR__)
    ->setRootUrl('http://localhost:8000/')
    ->setPath('storage', '/absolute/path/to/your/storage/folder')
    ->registerConfig('/absolute/path/to/your/config/folder')
    ->init();
```

*The storage directory needs to be writable by the application.*

Using the `.env` file is the recommended approach ([info](https://github.com/vlucas/phpdotenv#readme)) and it allows one to easily create a web entry-point as well as a cli with minimal setup:

**index.php**
```php
<?php

require __DIR__.'/../vendor/autoload.php';

use Soma\Application;

$app = Application::getInstance();

$app->init(__DIR__);
```

**appctrl**
```php
#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Soma\Console;
use Soma\Application;

$app = Application::getInstance();
$app->init(__DIR__.'/public');

$app->getConsole()->run();
```

### Configuration

Configuration files can be PHP files returning arrays (recommended) or JSON, YAML and INI configuration files:

**config/app.php**
```php
<?php return [
    'name' => 'My App',
    'version' => '1.0.0',
    'date-format' => '%Y-%m-%d',
    'timezone' => 'Europe/Stockholm',
    'providers' => [
        \Soma\Providers\EventsProvider::class,
        \MyApp\Providers\RoutingProvider::class,
        \MyApp\Providers\CacheProvider::class,
    ],
    'aliases' => [
        'ServiceProvider' => \Soma\ServiceProvider::class,
        'Container' => \Psr\Container\ContainerInterface::class,
        'Collection' => \Illuminate\Support\Collection::class,
        'Repository' => \Soma\Repository::class,
        'Store' => \Soma\Store::class,
        'Event' => \Symfony\Component\EventDispatcher\Event::class,
        'GenericEvent' => \Symfony\Component\EventDispatcher\GenericEvent::class,

        'App' => \Soma\Facades\App::class,
        'Config' => \Soma\Facades\Config::class,
        'Event' => \Soma\Facades\Event::class,
    ],
    'commands' => [
        \Soma\Commands\AppInstall::class,
        \Soma\Commands\AppUninstall::class,
        \Soma\Commands\AppRefresh::class,
        \Soma\Commands\AppTinker::class,
        \Soma\Commands\AppServe::class,
        \Soma\Commands\AppClearCache::class,
    ],
];
```

These values are then retrievable either via the `Config` facade or a helper function using dot-notation, namespaced by the name of the config file:

```php
$appName = config('app.name');
```

### Services

The service providers are how you can modularly add in functionality to your application. The `ServiceProvider` class has been designed to be compatible with `Illuminate\Support\ServiceProvider` and should be able to register them as well as long as they don't call any Laravel specific code. It's also been designed according to the now deprecated [ContainerInterop](https://github.com/container-interop/service-provider) standard. Unfortunately the extension definitions have the arguments reversed in SOMA for compatibility with PHP-DI, the container library. A typical `ServiceProvider` may look like the following:

```php
<?php namespace MyApp\Providers;

use Soma\Store;
use Soma\ServiceProvider;
use Psr\Container\ContainerInterface;

use MyRouter;

class RoutingProvider extends ServiceProvider
{
    public function boot(ContainerInterface $c) : void
    {
        if (! is_cli()) {
            listen('app.ready', function(Event $e) use ($c) {
                $c->get('router')->resolveRequest();
            });
        }
    }

    public function getFactories() : array
    {
        return [
            'router' => function(ContainerInterface $c) {
                return new MyRouter();
            },
        ];
    }

    public function getExtensions() : array
    {
        return [
            'paths' => function(Store $paths, ContainerInterface $c) {
                $paths['admin'] = realpath(__DIR__.'/../');
                $paths['admin.assets'] = $paths['admin'].'/assets');

                return $paths;
            },
            'urls' => function(Store $urls, ContainerInterface $c) {
                $urls['admin'] = $urls['root'].'/admin';

                return $urls;
            },
        ];
    }
}
```

All definitions from `getExtensions` are automatically wrapped with `DI\decorate` so that those changes gets applied whenever you resolve a definition from the container. However, you can use any [PHP-DI definition type](http://php-di.org/doc/php-definitions.html#definition-types) for both `getFactories` and `getExtensions` and the result of the latter isn't wrapped if it's already been wrapped by PHP-DI.

### Commands

The console engine is built on `Illuminate\Console` and the commands are defined in the same manner as in [Laravel 7.0](https://laravel.com/docs/7.0/artisan). For example:

```php
<?php namespace MyApp\Commands;

use Soma\Command;

class HelloWorld extends Command
{
    protected $signature = 'say:hello {who?}';
    protected $description = 'Say hello to the world.';

    public function handle()
    {
        $who = $this->argument('who', 'world');

        $this->info('Hello '.$who.'!');
    }
}
```

As long as the command has been registered it can be executed either via `Application::runCommand` or a console script:

```sh
php appctrl say:hello "everybody"
```

#### Predefined commands

##### app:tinker

The *tinker* command starts an interactive PHP shell within the application environment - an effective way to test code or make quick changes to data using the application API.

##### app:install

The *install* command should be run before you start coding to make sure all necessary directories have been created. By default the application creates a symbolic link to a sub-folder of the storage directory in the public directory in order to be able to serve cached or uploaded resources. Other service providers can implement the `install` [method](https://github.com/soma-php/framework/blob/master/src/Commands/Contracts/ServiceProviderInterface.php) and do necessary filesystem changes that the normal web server user wouldn't be able to (it's recommended to leave the web root read-only for the web server).

The framework keeps track of which service providers have been installed and which hasn't, so if new services are added they can be run with the same command without risking running procedures multiple times from one provider. Any single provider can be called on its own by specifying the fully qualified class name of the provider as an argument to the command.

##### app:uninstall

The command is [implemented](https://github.com/soma-php/framework/blob/master/src/Commands/Contracts/ServiceProviderInterface.php) the same way as *install* and is meant to reverse the changes made during installation.

##### app:refresh

If there are times one would like to make filesystem changes depending on the state of other components (for example when configuration changes) then *refresh* can be [implemented](https://github.com/soma-php/framework/blob/master/src/Commands/Contracts/ServiceProviderInterface.php) to handle those use cases. For example a "theme" service that requires a symbolic link to the active theme's assets in the public directory.

##### app:serve

The command starts the internal PHP web server in the public directory to allow one to quickly set up a development environment without the need for a fully featured HTTP server.

##### app:clear-cache

The *clear-cache* commands deletes by default the generated files by `Application` and provides functionality to hook in other service's procedures for emptying their cache. The framework uses a [PSR-14 event system](https://symfony.com/doc/4.4/event_dispatcher.html) internally that can be easily consumed by your own app by registering the `Soma\Providers\EventsProvider` service provider. Whenever the command is run the framework dispatches the event `app.cache.clear` which you can hook your own logic into by registering a listener (see definition for the `listen` helper in [helpers.php](https://github.com/soma-php/framework/blob/master/src/helpers.php)).

All paths registered under the cache namespace (e.g. `cache.storage`) can be automatically handled without having to define your own custom logic. They are automatically emptied/removed when the command is run or if it is specifically targeted when executing the command: `php appctrl app:cache-clear storage`

### Helpers

The file [helpers.php](https://github.com/soma-php/soma/blob/master/src/helpers.php) contain a couple of functions that are meant to simplify either calling app services or work with certain types of data. There's also useful classes for working with data-sets like `Soma\Store`, `Soma\Repository` and `Soma\Manifest`. The framework also depends on `illuminate\support` that provide [a whole bunch of helpers](https://github.com/illuminate/support/tree/826782d01ec7a0befe26b106713822df5933ee69) for you to make use of.

## License

MIT
