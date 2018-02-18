# PHPWander

This tool is part of my master's thesis and it is still under development.

A lot of the code is inspired by awesome PHPStan ([phpstan/phpstan](https://github.com/phpstan/phpstan/)). Some classes are just copied out and reworked/retyped according to my needs, some just share the structure or name.

Anyway, many thanks to Ondrej Mirtes for his devotion working on PHPStan and you should definitely check it out!  

## Installation

1. Clone this repository
2. Get [composer](https://getcomposer.org/download/)
3. Run ```composer install``` from command line in root directory of the repository

## CLI run
Usage: ```$ phpwander analyse [options] [--] [<paths>]...```
Example: ```./bin/phpwander analyse tests/cases/ --autoload```

### CLI options

- ```--configuration``` or ```-c```: Path to project configuration file, ```phpwander.neon``` or ```phpwander.local.neon``` in root directory are automatically detected
- ```--no-progress```: Do not show progress bar, only results
- ```--error-format```: Format in which to print the result of the analysis
- ```--autoload-file```: Project's additional autoload file path
- ```--autoload```: Paths with source code to run analysis on will be autoloaded

## Browser run
1. In browser navigate to web root of the repository and ```demo.php``` file (not having HTTP server? Run ```php -S localhost:8081```, then you can visit ```http://localhost:8081/demo.php```)
2. You can fiddle with the tool by changing a path to a test case directory on line 5 in file ```demo.php```
