<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Installation Telemetry
    |--------------------------------------------------------------------------
    |
    | When enabled, finishing an installation posts the application URL, the
    | name and e-mail address of the first administrator and the default
    | country to https://updates.krayincrm.com/api/updates. The installer's
    | image cache route (`/cache/{filename}`) also downloads the Krayin logo
    | from the same host.
    |
    | It is disabled by default so a locally hosted instance never talks to a
    | third party. Set KRAYIN_TELEMETRY_ENABLED=true in the environment to opt
    | back in.
    */
    'enabled' => env('KRAYIN_TELEMETRY_ENABLED', false),
];
