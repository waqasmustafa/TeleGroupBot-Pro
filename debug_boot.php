<?php
// debug_boot.php
define('LARAVEL_START', microtime(true));
require_once __DIR__.'/app/Helpers/Overwrites.php';
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
try {
    echo "Handling request...\n";
    $kernel->handle(Illuminate\Http\Request::capture());
    echo "Done.\n";
} catch (\Throwable $e) {
    echo "CAUGHT " . get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        $prev = $e->getPrevious();
        echo "PREVIOUS " . get_class($prev) . ": " . $prev->getMessage() . " in " . $prev->getFile() . ":" . $prev->getLine() . "\n";
    }
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}
