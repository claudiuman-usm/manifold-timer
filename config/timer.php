<?php

return [
    /*
    | Parent PIN for the /parent view. Kept out of the DB so it is trivially
    | editable by hand in production (.env), separate from the kids' PINs.
    */
    'parent_pin' => env('PARENT_PIN', '0000'),

    // App version — surfaced in the UI and CHANGELOG.
    'version' => '1.6.1',
];
