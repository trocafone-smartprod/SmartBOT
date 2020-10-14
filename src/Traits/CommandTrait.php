<?php
namespace App\Traits;

trait CommandTrait
{
    /** @var App\Application */
    protected $app;
    public function __construct()
    {
        $this->app = $GLOBALS['app'];
    }
}
