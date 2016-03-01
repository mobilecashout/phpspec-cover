<?php

namespace Addvilz\PhpSpecCodeCoverage;

/**
 * Modified version of Text report formatter by Sebastian Bergmann <sebastian@phpunit.de>.
 *
 * @see https://github.com/sebastianbergmann/php-code-coverage
 *
 * Class CLIReporter
 */
class CLIReporter
{
    protected $lowUpperBound;
    protected $highLowerBound;
    protected $showUncoveredFiles;
    protected $showOnlySummary;

    protected $colors = [
        'green' => "\x1b[30;42m",
        'gray' => "\x1b[30;47m",
        'yellow' => "\x1b[30;43m",
        'red' => "\x1b[37;41m",
        'header' => "\x1b[1;37;40m",
        'reset' => "\x1b[0m",
        'eol' => "\x1b[2K",
    ];

    public function __construct($lowUpperBound, $highLowerBound, $showUncoveredFiles, $showOnlySummary)
    {
        $this->lowUpperBound = $lowUpperBound;
        $this->highLowerBound = $highLowerBound;
        $this->showUncoveredFiles = $showUncoveredFiles;
        $this->showOnlySummary = $showOnlySummary;
    }

    /**
     * @param \PHP_CodeCoverage $coverage
     * @param bool              $showColors
     *
     * @return string
     */
    public function process(\PHP_CodeCoverage $coverage, $showColors = false)
    {
        $output = PHP_EOL.PHP_EOL;
        $report = $coverage->getReport();
        unset($coverage);

        $colors = [
            'header' => '',
            'classes' => '',
            'methods' => '',
            'lines' => '',
            'reset' => '',
            'eol' => '',
        ];

        if ($showColors) {
            $colors['classes'] = $this->getCoverageColor(
                $report->getNumTestedClassesAndTraits(),
                $report->getNumClassesAndTraits()
            );
            $colors['methods'] = $this->getCoverageColor(
                $report->getNumTestedMethods(),
                $report->getNumMethods()
            );
            $colors['lines'] = $this->getCoverageColor(
                $report->getNumExecutedLines(),
                $report->getNumExecutableLines()
            );
            $colors['reset'] = $this->colors['reset'];
            $colors['header'] = $this->colors['header'];
            $colors['eol'] = $this->colors['eol'];
        }

        $classes = sprintf(
            '  Classes: %6s (%d/%d)',
            \PHP_CodeCoverage_Util::percent(
                $report->getNumTestedClassesAndTraits(),
                $report->getNumClassesAndTraits(),
                true
            ),
            $report->getNumTestedClassesAndTraits(),
            $report->getNumClassesAndTraits()
        );

        $methods = sprintf(
            '  Methods: %6s (%d/%d)',
            \PHP_CodeCoverage_Util::percent(
                $report->getNumTestedMethods(),
                $report->getNumMethods(),
                true
            ),
            $report->getNumTestedMethods(),
            $report->getNumMethods()
        );

        $lines = sprintf(
            '  Lines:   %6s (%d/%d)',
            \PHP_CodeCoverage_Util::percent(
                $report->getNumExecutedLines(),
                $report->getNumExecutableLines(),
                true
            ),
            $report->getNumExecutedLines(),
            $report->getNumExecutableLines()
        );

        $padding = max(array_map('strlen', [$classes, $methods, $lines]));

        if ($this->showOnlySummary) {
            $title = 'Code Coverage Report Summary:';
            $padding = max($padding, strlen($title));

            $output .= $this->format($colors['header'], $padding, $title);
        } else {
            $date = date('  Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
            $title = 'Code Coverage Report:';

            $output .= $this->format($colors['header'], $padding, $title);
            $output .= $this->format($colors['header'], $padding, $date);
            $output .= $this->format($colors['header'], $padding, '');
            $output .= $this->format($colors['header'], $padding, ' Summary:');
        }

        $output .= $this->format($colors['classes'], $padding, $classes);
        $output .= $this->format($colors['methods'], $padding, $methods);
        $output .= $this->format($colors['lines'], $padding, $lines);

        if ($this->showOnlySummary) {
            return $output.PHP_EOL;
        }

        $classCoverage = [];

        foreach ($report as $item) {
            if (!$item instanceof \PHP_CodeCoverage_Report_Node_File) {
                continue;
            }

            $classes = $item->getClassesAndTraits();

            $source = [];

            $coverageData = $item->getCoverageData();
            $codeLines = explode(PHP_EOL, file_get_contents($item->getPath()));
            $i = 1;
            $widestLine = 0;

            foreach ($codeLines as $line) {
                $len = mb_strlen($line);
                if ($len > $widestLine) {
                    $widestLine = $len + 10;
                }
            }

            foreach ($codeLines as $line) {
                if (array_key_exists($i, $coverageData)) {
                    $numTests = count($coverageData[$i]);

                    if ($coverageData[$i] === null) {
                        $line = trim($this->format(
                            $this->colors['gray'],
                            $widestLine,
                            sprintf(
                                '[/] %s',
                                $line
                            )
                        ));
                    } elseif ($numTests == 0) {
                        $line = trim($this->format(
                            $this->colors['red'],
                            $widestLine,
                            sprintf(
                                '[!] %s',
                                $line
                            )
                        ));
                    } else {
                        $line = trim($this->format(
                            $this->colors['green'],
                            $widestLine,
                            sprintf(
                                '[y] %s',
                                $line
                            )
                        ));
                    }
                } else {
                    $line = trim($this->format(
                        $this->colors['gray'],
                        $widestLine,
                        sprintf(
                            '[/] %s',
                            $line
                        )
                    ));
                }

                $source[] = '  '.$line;
                ++$i;
            }

            foreach ($classes as $className => $class) {
                $classStatements = 0;
                $coveredClassStatements = 0;
                $coveredMethods = 0;
                $classMethods = 0;
                $methodInfo = [];

                foreach ($class['methods'] as $method) {
                    if ($method['executableLines'] == 0) {
                        continue;
                    }

                    $methodInfo[$method['methodName']] = sprintf(
                        '    [%s] Cyclomatic complexity: %s CRAP: %s',
                        $method['methodName'],
                        $method['ccn'],
                        $method['crap']
                    );

                    ++$classMethods;
                    $classStatements += $method['executableLines'];
                    $coveredClassStatements += $method['executedLines'];
                    if ($method['coverage'] == 100) {
                        ++$coveredMethods;
                    }
                }

                if (!empty($class['package']['namespace'])) {
                    $namespace = '\\'.$class['package']['namespace'].'::';
                } elseif (!empty($class['package']['fullPackage'])) {
                    $namespace = '@'.$class['package']['fullPackage'].'::';
                } else {
                    $namespace = '';
                }

                $classCoverage[$namespace.$className] = [
                    'namespace' => $namespace,
                    'className ' => $className,
                    'methodsCovered' => $coveredMethods,
                    'methodCount' => $classMethods,
                    'statementsCovered' => $coveredClassStatements,
                    'statementCount' => $classStatements,
                    'source' => implode(PHP_EOL, $source),
                    'methodInfo' => implode(PHP_EOL, $methodInfo),
                ];
            }
        }

        ksort($classCoverage);

        $methodColor = '';
        $linesColor = '';
        $resetColor = '';

        foreach ($classCoverage as $fullQualifiedPath => $classInfo) {
            if ($classInfo['statementsCovered'] != 0 ||
                $this->showUncoveredFiles
            ) {
                if ($showColors) {
                    $methodColor = $this->getCoverageColor($classInfo['methodsCovered'], $classInfo['methodCount']);
                    $linesColor = $this->getCoverageColor($classInfo['statementsCovered'], $classInfo['statementCount']);
                    $resetColor = $colors['reset'];
                }

                $output .= PHP_EOL.$fullQualifiedPath.PHP_EOL
                    .'  '.$methodColor.'Methods: '.$this->printCoverageCounts($classInfo['methodsCovered'], $classInfo['methodCount'], 2).$resetColor.' '
                    .'  '.$linesColor.'Lines: '.$this->printCoverageCounts($classInfo['statementsCovered'], $classInfo['statementCount'], 3).$resetColor.
                    PHP_EOL.$classInfo['methodInfo'].PHP_EOL.
                    PHP_EOL.$classInfo['source'];
            }
        }

        return $output.PHP_EOL;
    }

    protected function getCoverageColor($numberOfCoveredElements, $totalNumberOfElements)
    {
        $coverage = \PHP_CodeCoverage_Util::percent(
            $numberOfCoveredElements,
            $totalNumberOfElements
        );

        if ($coverage >= $this->highLowerBound) {
            return $this->colors['green'];
        } elseif ($coverage > $this->lowUpperBound) {
            return $this->colors['yellow'];
        }

        return $this->colors['red'];
    }

    protected function printCoverageCounts($numberOfCoveredElements, $totalNumberOfElements, $precision)
    {
        $format = '%'.$precision.'s';

        return \PHP_CodeCoverage_Util::percent(
            $numberOfCoveredElements,
            $totalNumberOfElements,
            true,
            true
        ).
        ' ('.sprintf($format, $numberOfCoveredElements).'/'.
        sprintf($format, $totalNumberOfElements).')';
    }

    private function format($color, $padding, $string)
    {
        $reset = $color ? $this->colors['reset'] : '';

        return $color.str_pad($string, $padding).$reset.PHP_EOL;
    }
}
