<?php namespace Soma\Commands;

use Soma\Command;

class AppServe extends Command
{
    protected $signature = 'app:serve {--host=} {--port=}';
    protected $description = 'Serve the application on the PHP development server.';

    public function handle()
    {
        $name = config('app.name', 'Soma');
        $host = $this->option('host') ?? 'localhost';
        $port = $this->option('port') ?? '8000';
        $root = get_path('root');

        chdir($root);

        $this->info($name." development server started on http://{$host}:{$port}/");

        passthru(PHP_BINARY . " -S {$host}:{$port} 2>&1");

        return 0;
    }
}