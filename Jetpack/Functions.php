<?php

function pathify()
{
    $path = '';
    $args = func_get_args();

    $i = 0;
    foreach ($args as $arg) {
        if (is_array($arg)) {
            $path .= DIRECTORY_SEPARATOR . call_user_func_array('pathify', $arg);
        } else {

            // Remove leading slash unless it's the first part of the path
            if ($i !== 0) {
                $arg = ltrim($arg, '/');
            }

            // Remove trailing slash unless it's the last part of the path
            if ($i !== count($args) - 1) {
                $arg = rtrim($arg, '/');
            }

            $path .= DIRECTORY_SEPARATOR . $arg;
        }

        $i++;
    }

    return substr($path, 1);
}

function app_dir()
{
    if (is_cli()) {
        global $argv;
        return dirname(realpath($argv[0]));
    } else {
        return dirname($_SERVER["SCRIPT_FILENAME"]);
    }
}

function is_cli()
{
    return php_sapi_name() === 'cli';
}
