<?php

namespace Addvilz\PhpSpecCodeCoverage;

use PhpSpec\Extension\ExtensionInterface;
use PhpSpec\ServiceContainer;

class Extension implements ExtensionInterface
{
    /**
     * @param ServiceContainer $container
     */
    public function load(ServiceContainer $container)
    {
        $container->setShared('event_dispatcher.listeners.addvilz_code_coverage', function ($container) {
            $listener = new Listener(
                new \PHP_CodeCoverage(),
                $container->get('console.io'),
                1
            );

            register_shutdown_function([$listener, 'shutdown']);

            return $listener;
        });
    }
}
