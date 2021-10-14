<?php

return [
    /** default connection name */
    'connection' => 'mongodb',

    /** default filesystem disk */
    'storage' => 'local',

    /** default directory for downloads */
    'directory' => 'mongodb/',

    /**
     * If set to true, numeric values will be automatically considered integers
     * passing a specific `cast` will overwrite the autocast
     */
    'autocast_int' => true,

    /**
     * If set to true, numeric float will be automatically casted as `float`
     * passing a specific `cast` will overwrite the autocast
     */
    'autocast_float' => true
];