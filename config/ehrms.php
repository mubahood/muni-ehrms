<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-mail notifications
    |--------------------------------------------------------------------------
    |
    | In-app notifications (the bell) are always sent. E-mail copies go out only
    | when this is true. Keep it false anywhere the database holds test data,
    | so nobody real is e-mailed about a test request.
    |
    */

    'notify_email' => env('NOTIFY_EMAIL', false),

];
