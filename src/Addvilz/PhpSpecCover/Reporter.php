<?php

namespace Addvilz\PhpSpecCover;

use Symfony\Component\Console\Formatter\OutputFormatter;

class Reporter
{
    const COVERAGE_META = 0;
    const COVERAGE_COVERED = 1;
    const COVERAGE_NOT_COVERED = 2;

    /**
     * @var OutputFormatter
     */
    private $formatter;

    /**
     * @param OutputFormatter $formatter
     */
    public function __construct(OutputFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function getOutput(\PHP_CodeCoverage $coverage)
    {
        $output = '';
        $report = $coverage->getReport();

        foreach ($report as $item) {
            if (!$item instanceof \PHP_CodeCoverage_Report_Node_File) {
                continue;
            }

            $coverageData = $item->getCoverageData();
            $codeLines = explode(PHP_EOL, file_get_contents($item->getPath()));
            $widestLine = $this->getWidestLine($codeLines);
            $reportLines = [];

            $i = 1;
            foreach ($codeLines as $line) {
                if (array_key_exists($i, $coverageData)) {
                    $numTests = count($coverageData[$i]);
                    if ($coverageData[$i] === null) {
                        $code = self::COVERAGE_META;
                    } elseif ($numTests == 0) {
                        $code = self::COVERAGE_NOT_COVERED;
                    } else {
                        $code = self::COVERAGE_COVERED;
                    }
                } else {
                    $code = self::COVERAGE_META;
                }
                $reportLines[] = [
                    'line' => $line,
                    'code' => $code,
                ];
                ++$i;
            }

            $classCoverage = $this->getClassCoverage($item->getClassesAndTraits());
            $totalCoverage = $this->getTotalCoverage($classCoverage);
            $output .= $this->renderClassCoverage($classCoverage);
            if (!$totalCoverage) {
                $output .= $this->renderCodeCoverage($reportLines, $widestLine);
            }
        }

        return $output;
    }

    private function getClassCoverage(array $classes)
    {
        $classCoverage = [];

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

                $methodInfo[$method['methodName']] = $method;

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
                'className' => $className,
                'methodsCovered' => $coveredMethods,
                'methodCount' => $classMethods,
                'statementsCovered' => $coveredClassStatements,
                'statementCount' => $classStatements,
                'methodInfo' => $methodInfo,
            ];
        }

        return $classCoverage;
    }

    private function getWidestLine($codeLines)
    {
        $widestLine = 0;
        foreach ($codeLines as $line) {
            $len = mb_strlen($line);
            if ($len > $widestLine) {
                $widestLine = $len + 10;
            }
        }

        return $widestLine;
    }

    private function renderClassCoverage(array $classCoverage)
    {
        $output = '';
        foreach ($classCoverage as $class) {
            $classColor = $class['methodsCovered'] < $class['methodCount'] ? 'red' : 'green';

            $output .= $this
                    ->formatter
                    ->format(sprintf(
                        '<bg=%s;fg=white;options=bold>%s%s (Methods: %d/%d | Lines: %d/%d)</>',
                        $classColor,
                        $class['namespace'],
                        $class['className'],
                        $class['methodsCovered'],
                        $class['methodCount'],
                        $class['statementsCovered'],
                        $class['statementCount']
                    )).PHP_EOL;

            foreach ($class['methodInfo'] as $method) {
                $methodColor = $method['executedLines'] < $method['executableLines'] ? 'red' : 'green';

                $output .= $this
                        ->formatter
                        ->format(sprintf(
                            '<bg=%s;fg=white>- [%d:%d] %s (Coverage: %s | CCN: %s | CRAP %s)</>',
                            $methodColor,
                            $method['startLine'],
                            $method['endLine'],
                            $method['methodName'],
                            $method['coverage'],
                            $method['ccn'],
                            $method['crap']
                        )).PHP_EOL;
            }
        }

        return $output;
    }

    private function renderCodeCoverage(array $reportLines, $widestLine)
    {
        $output = '';
        $widestLineNum = mb_strlen(count($reportLines));

        foreach ($reportLines as $i => $line) {
            $source = $this->formatter->escape(str_pad($line['line'], $widestLine));
            $lineNum = str_pad((string) ($i + 1), $widestLineNum, ' ', STR_PAD_LEFT);
            switch ($line['code']) {
                case self::COVERAGE_COVERED:
                    $output .= $this->formatter->format(sprintf(
                        '<bg=white;fg=green>[%s] %s</>',
                        $lineNum,
                        $source
                    ));
                    break;
                case self::COVERAGE_NOT_COVERED:
                    $output .= $this->formatter->format(sprintf(
                        '<bg=white;fg=red>[%s] %s</>',
                        $lineNum,
                        $source
                    ));
                    break;
                case self::COVERAGE_META:
                    $output .= $this->formatter->format(sprintf(
                        '<bg=white;fg=black>[%s] %s</>',
                        $lineNum,
                        $source
                    ));
                    break;
            }
            $output .= PHP_EOL;
        }

        return $output;
    }

    private function getTotalCoverage(array $classCoverage)
    {
        foreach ($classCoverage as $class) {
            if ($class['methodsCovered'] < $class['methodCount']) {
                return false;
            }
        }

        return true;
    }
}
