<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use wanghanwanghan\someUtils\control;

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
        $res=control::aesDecode('1dfe42308746363cae7e30edacc1af4e','15110256200');

        dd($res);

        return true;
    }
}
