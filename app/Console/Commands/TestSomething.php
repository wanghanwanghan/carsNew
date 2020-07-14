<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestSomething extends Command
{
    protected $signature = 'TestSomething';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {


        return true;
    }
}
