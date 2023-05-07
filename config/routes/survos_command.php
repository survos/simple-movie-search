<?php

// config/routes.php
use Survos\CommandBundle\Controller\CommandController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;


return function (RoutingConfigurator $routes) {
    $routes->add('survos_commands', '/commands')
        // the controller value has the format [controller_class, method_name]
        ->controller([CommandController::class, 'commands'])
    ;

    $routes->add('survos_command', '/run-command/{commandName}')
        // the controller value has the format [controller_class, method_name]
        ->controller([CommandController::class, 'runCommand'])
    ;

};
