<?php
namespace App\Commands;

use Illuminate\Console\Command;

class HelloCommand extends Command
{
    protected $signature = 'hello {name=World}';

    protected $description = 'Say hello';

    public function handle()
    {
        $this->info('Hello '.$this->argument('name'));
    }
}
