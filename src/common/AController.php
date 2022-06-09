<?php
namespace Jef\common;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

abstract class AController
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    abstract public function __invoke(RouteCollectorProxy $group);

}