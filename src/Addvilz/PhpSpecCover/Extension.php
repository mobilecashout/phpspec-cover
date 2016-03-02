<?php

namespace Addvilz\PhpSpecCover;

use PhpSpec\Console\IO;
use PhpSpec\Extension\ExtensionInterface;
use PhpSpec\ServiceContainer;
use Symfony\Component\Console\Formatter\OutputFormatter;

class Extension implements ExtensionInterface
{
    /**
     * @param ServiceContainer $container
     */
    public function load(ServiceContainer $container)
    {

        /** @var IO $io */
        $io = $container->get('console.io');

        if (!extension_loaded('xdebug')) {
            $io->writeln('<fg=white;bg=red>Code coverage disabled (missing xdebug extension)</>');

            return;
        }

        $container->setShared('event_dispatcher.listeners.addvilz_code_coverage', function (ServiceContainer $container) use ($io, $options) {

            $defaults = [
                'max_specs' => 1
            ];

            $options = array_merge(
                $defaults,
                $container->getParam('phpspec_cover', [])
            );

            $listener = new Listener(
                new \PHP_CodeCoverage(),
                $io,
                new Reporter(new OutputFormatter($io->isDecorated())),
                $options['max_specs']
            );

            register_shutdown_function([$listener, 'shutdown']);

            return $listener;
        });
    }
}
