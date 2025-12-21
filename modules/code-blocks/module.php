<?php
/**
 * Module: Code Blocks
 */

// Convention: Core Loader handles require, but this file is the entry point
// We can require the main class file here if we aren't using a strict autoloader map for modules yet.
// Our Core Autoloader maps Cotex\Core\*, but maybe not modules.
// Let's require the class file manually here to be safe and explicit as per "lazy load".

require_once __DIR__ . '/class-code-blocks.php';
