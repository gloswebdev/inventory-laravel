<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Set some default values. It is possible to add all defines that can be set
    | in dompdf_config.inc.php. You can also override the entire config file.
    |
    */
    'show_warnings' => false,

    /*
    |--------------------------------------------------------------------------
    | Public Path Override
    |--------------------------------------------------------------------------
    |
    | On shared hosting (e.g. Hostinger), the app root has no "public/"
    | subdirectory — instead "public_html/" IS the public root.
    | `base_path('public')` does not exist there, so realpath() returns false
    | and dompdf throws "Cannot resolve public path".
    |
    | We explicitly set this to public_path() so Laravel's resolved public path
    | (set via $app->usePublicPath(__DIR__) in public_html/index.php) is used.
    |
    */
    'public_path' => public_path(),

    /*
     * Dejavu Sans font is missing glyphs for converted entities, turn it off if you need to show € and £.
     */
    'convert_entities' => true,

    'options' => [
        /**
         * The location of the DOMPDF font directory
         *
         * Use storage path so it is always writable on shared hosting.
         */
        'font_dir' => storage_path('fonts'),

        /**
         * The location of the DOMPDF font cache directory
         */
        'font_cache' => storage_path('fonts'),

        /**
         * The location of a temporary directory.
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * dompdf's "chroot": All local files opened by dompdf must be in a
         * subdirectory of this directory.
         */
        'chroot' => realpath(base_path()),

        /**
         * Protocol whitelist
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,

        'log_output_file' => null,

        'enable_font_subsetting' => false,

        'pdf_backend' => 'CPDF',

        'default_media_type' => 'screen',

        'default_paper_size' => 'a4',

        'default_paper_orientation' => 'portrait',

        'default_font' => 'serif',

        'dpi' => 96,

        'enable_php' => false,

        'enable_javascript' => true,

        'enable_remote' => false,

        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,

        'enable_html5_parser' => true,
    ],

];
