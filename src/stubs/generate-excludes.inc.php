<?php

declare(strict_types=1);

use PhpParser\ParserFactory;
use Snicco\PhpScoperExcludes\Option;

return [
    // use the current working directory
    Option::OUTPUT_DIR => null,
    // pass files as command arguments
    Option::FILES => [],
];