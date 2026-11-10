<?php

return [
    // Leave unset to let Browsershot/Puppeteer locate Chrome on its own.
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

    // Pass --no-sandbox to Chrome. Set true on Linux servers where the sandbox cannot start.
    'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', false),

    // Where puppeteer is installed (npm ci). Avoids relying on a global `npm root -g`.
    'node_modules_path' => env('BROWSERSHOT_NODE_MODULES_PATH', base_path('node_modules')),

    // Hard ceiling (seconds) so a Chrome launch failure cannot hang the request.
    'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
];
