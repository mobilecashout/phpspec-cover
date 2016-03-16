<?php

namespace Addvilz\PhpSpecCover;

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
     * @var Reporter
     */
    private $reporter;

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
     * @param Reporter          $reporter
     * @param int               $maxSpecs
     */
    public function __construct(\PHP_CodeCoverage $coverage, IO $io, Reporter $reporter, $maxSpecs)
    {
        $this->coverage = $coverage;
        $this->io = $io;
        $this->reporter = $reporter;
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
            ->getResource();

        $this->coverage->filter()->setWhitelistedFiles([]);

        $this
            ->coverage
            ->filter()
            ->addFileToWhitelist($resource->getSrcFilename());

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
        
        if(0 !== $event->getResult()) {
            return;
        }

        $this->output[] = $this
            ->reporter
            ->getOutput($this->coverage)
        ;
    }

    public function shutdown()
    {
        if (!$this->enabled) {
            return;
        }

        foreach ($this->output as $report) {
            echo $report.PHP_EOL;
        }
    }
}
