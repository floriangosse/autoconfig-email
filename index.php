<?php

declare(strict_types = 1);

// Never expose errors (which can include server file paths) in the response;
// log them instead. Set here, before anything else runs, so it also covers
// errors that occur before Flight installs its own error handler.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/app/app.php');