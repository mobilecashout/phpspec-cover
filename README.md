# addvilz/phpspec-code-coverage

WIP, but working.

## About

This is a slightly more opinionated version of PhpSpec code coverage library. It is designed to give you insight about spec you are currently working on, without interfering when PHPSpec is executed against full test suite (there is extension for that).

Features:

- Automatically disables itself for when you run suites (more than 1 specs, will be configurable in later versions);
- Only outputs reports in CLI;
- Outputs line-by-line source code with coverage information in CLI as well;
- Coverage report is only generated for class that the current spec is implemented against.

## Installation

```
extensions:
    - Addvilz\PhpSpecCodeCoverage\Extension
```

### Credit where credit is due

[henrikbjorn/phpspec-code-coverage](https://github.com/henrikbjorn/PhpSpecCodeCoverageExtension) by [henrikbjorn](https://github.com/henrikbjorn). If you need a full fledged code coverage extension for PHPSpec, this is the one you should be looking at.

### License

Apache 2.0