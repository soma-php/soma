<?php namespace Soma;

use BadMethodCallException;
use Illuminate\Console\Application as IlluminateApplication;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Soma\Application;

class Console extends IlluminateApplication
{
    protected $app;

    public function __construct(string $name, string $version, Application $app)
    {
        SymfonyApplication::__construct($name, $version);

        $this->app = $this->laravel = $app;
        $this->events = $app->getEventDispatcher();
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->resolveCommands($app->getCommands());

        $this->events->dispatch('console.start');

        $this->bootstrap();
    }

    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $commandName = $this->getCommandName(
            $input = $input ?: new ArgvInput
        );

        $this->events->dispatch($commandName.'.start', [
            'command' => $commandName,
            'input' => $input,
            'output' => $output = $output ?: new ConsoleOutput,
        ]);

        try {
            $exitCode = SymfonyApplication::run($input, $output);
        }
        catch (CommandNotFoundException $e) {
            if (is_debug()) {
                throw $e;
            }
            else {
                $exitCode = $e->getCode();
                $message = $e->getMessage();

                $output->writeln($message);
            }
        }

        $this->events->dispatch($commandName.'.finish', [
            'command' => $commandName,
            'input' => $input,
            'output' => $output,
            'code' => $exitCode,
        ]);

        return $exitCode;
    }

    public function getLaravel()
    {
        throw new BadMethodCallException("You're not in Kansas anymore.");
    }
}