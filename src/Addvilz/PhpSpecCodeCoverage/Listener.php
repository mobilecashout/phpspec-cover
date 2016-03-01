<?php

namespace Addvilz\PhpSpecCodeCoverage;

use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Listener implements EventSubscriberInterface
{
    /**
     * @var \PHP_CodeCoverage
     */
    private $coverage;

    /**
     * @var IO
     */
    private $io;

    /**
     * @var int
     */
    private $maxSpecs;

    /**
     * @var array
     */
    private $output = [];

    /**
     * @var bool
     */
    private $enabled = false;

    /**
     * @param \PHP_CodeCoverage $coverage
     * @param IO                $io
     * @param int               $maxSpecs
     */
    public function __construct(\PHP_CodeCoverage $coverage, $io, $maxSpecs)
    {
        $this->coverage = $coverage;
        $this->io = $io;
        $this->maxSpecs = $maxSpecs;
    }

    public static function getSubscribedEvents()
    {
        return [
            'beforeExample' => ['beforeExample'],
            'afterExample' => ['afterExample'],
            'beforeSuite' => ['beforeSuite'],
            'afterSuite' => ['afterSuite'],
        ];
    }

    public function beforeExample(ExampleEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $example = $event->getExample();
        $resource = $example
            ->getSpecification()
            ->getResource()
        ;

        $this->coverage->filter()->setWhitelistedFiles([]);

        $this
            ->coverage
            ->filter()
            ->addFileToWhitelist($resource->getSrcFilename())
        ;

        $this->coverage->start($resource->getSrcClassname());
    }

    public function beforeSuite(SuiteEvent $event)
    {
        $this->enabled = $this->maxSpecs >= count($event->getSuite()->getSpecifications());
    }

    public function afterExample(ExampleEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $this->coverage->stop();
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $report = new CLIReporter(35, 70, true, false);
        $this->output[] = $report->process($this->coverage, $this->io->isDecorated());
    }

    public function shutdown()
    {
        if (!$this->enabled) {
            $this->io->writeln(sprintf(
                'Code coverage disabled, too many specs to cover (Max %d)',
                $this->maxSpecs
            ));

            return;
        }

        foreach ($this->output as $report) {
            echo $report.PHP_EOL;
        }
    }
}
