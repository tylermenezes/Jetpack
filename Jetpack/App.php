<?php

namespace Jetpack;
use \Jetpack\Internal;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Internal' . DIRECTORY_SEPARATOR . 'require.php');

/**
 * Loads up all the components of the application
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the Perl Artistic License 2.0.
 *
 * @package Jetpack\Internal
 */
abstract class App
{
    public static $dir = null;
    public static $config = null;
    public static $twig = null;

    const config_file_name = '.config.json';
    const local_config_file_name = '.local.json';

    public static function before() {}
    public static function after() {}

    public static function start()
    {
        static::before();

        static::load_config();
        static::set_directories();
        static::set_timezone();
        static::enable_debugging();
        static::spl_load();
        static::tinydb_connect();
        static::twig_load();

        static::after();

        if (is_cli()) {
            static::thintasks_start();
        } else {
            static::cutecontrollers_start();
        }
    }

    /* # Individual Loading Steps */

    /**
     * Loads the config file, copying the default config if it doesn't exist
     */
    protected static function load_config()
    {
        $config_file = pathify(app_dir(), static::config_file_name);
        $local_config_file = pathify(app_dir(), static::local_config_file_name);

        if (!file_exists($config_file)) {
            if (!is_cli()) {
                echo "Config file error. See error logs for details.";
            }
            throw new \Exception("Config file doesn't exist. Create it at ".$config_file.". See the Jetpack docs for more info.");
        }

        static::$config = new Internal\Config($config_file, $local_config_file);
    }

    /**
     * Gets directory path information and stores them in the $dir object.
     */
    protected static function set_directories()
    {
        if (!isset(static::$config->directories->controllers)) {
            throw new \RuntimeException('Config file is missing setting for controllers directory.');
        }

        if (isset(static::$config->directories->includes)) {
            $includes_dir = static::$config->directories->includes;
        } else {
            $includes_dir = 'includes';
        }

        static::$dir = (object)[
            'webroot' => app_dir(),
            'includes' => pathify(app_dir(), $includes_dir)
        ];

        if (isset(static::$config->directories->controllers)) {
            static::$dir->controllers = pathify(app_dir(), static::$config->directories->controllers);
        }

        if (isset(static::$config->directories->tasks)) {
            static::$dir->tasks = pathify(app_dir(), static::$config->directories->tasks);
        }
    }

    /**
     * Sets the timezone
     */
    protected static function set_timezone()
    {
        if (static::$config->timezone) {
            date_default_timezone_set(static::$config->timezone);
        }
    }

    /**
     * Enables debugging to the browser if it's enabled in the config
     */
    protected static function enable_debugging()
    {
        if (static::$config->debug) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', 'on');
        }
    }

    /**
     * Registers SPL autoloaders.
     */
    protected static function spl_load()
    {
        $paths = [static::$dir->includes];
        if (file_exists(pathify(static::$dir->includes, 'submodules'))) {
            array_unshift($paths, pathify(static::$dir->includes, 'submodules'));
        }
        new Internal\SplClassLoader($paths);
    }

    /**
     * Connects to the database
     */
    protected static function tinydb_connect()
    {
        $write_strings = [];
        $read_strings = [];

        if (is_string(static::$config->db)) {
            $write_strings[] = static::$config->db;
        } else if (is_object(static::$config->db) && !isset(static::$config->db->write)) {
            $write_strings[] = static::tinydb_generate_connection_strings(static::$config->db);
        } else {
            if (isset(static::$config->db->write)) {
                $write_strings = static::tinydb_generate_connection_strings(static::$config->db->write);
            }
            if (isset(static::$config->db->read)) {
                $read_strings = static::tinydb_generate_connection_strings(static::$config->db->read);
            }
        }


        if (count($write_strings) > 0) {
            \TinyDb\Db::set($write_strings, $read_strings);
        }

    }

    /**
     * Automatically loads Twig using the filesystem loader if a path to a templates directory was provided
     */
    protected static function twig_load()
    {
        if (isset(static::$config->twig->dir)) {
            static::set_twig(pathify(static::$dir->webroot, static::$config->twig->dir));
        }
    }

    /**
     * Loads twig for the given template directory
     * @param mixed $template_dir_or_loader Template directory to use for templates, or a class which implements Twig_LoaderInterface
     */
    protected function set_twig($template_dir_or_loader)
    {
        $twig_config = [];

        // Enable debugging if enabled
        if (isset(static::$config->debug) && static::$config->debug) {
            $twig_config['debug'] = true;
        }

        // Load the config overrides if any
        $twig_config_options = ['charset', 'strict_variables', 'auto_reload', 'autoescape', 'optimizations', 'cache'];
        foreach ($twig_config_options as $option) {
            if (isset(static::$config->twig->$option)) {
                $twig_config[$option] = static::$config->twig->$option;
            }
        }

        // Load the loader if we were given a directory
        if (is_string($template_dir_or_loader)) {
            $template_dir_or_loader = new \Twig_Loader_Filesystem($template_dir_or_loader);
        }

        // Load Twig
        static::$twig = new \Twig_Environment($template_dir_or_loader, $twig_config);
        static::$twig->addExtension(new \AutoAB\AB());

        // Load the debugging extension if necessary
        if (isset(static::$config->debug) && static::$config->debug) {
            static::$twig->addExtension(new \Twig_Extension_Debug());
        }
    }

    /**
     * Starts a web app's routing
     */
    protected static function cutecontrollers_start()
    {
        try {
            \CuteControllers\Router::start(static::$dir->controllers);
        } catch (\CuteControllers\HttpError $err) {
            header("HTTP/1.1 ".$err->getCode()." ".$err->getMessage());

            // Load error page if one was provided
            $code = $err->getCode();
            if (isset(static::$config->error_routes->$code)) {
                try {
                    $error_router = new \CuteControllers\Router(static::$dir->controllers);
                    $request = \CuteControllers\Request::current();
                    $request->path = static::$config->error_routes->$code;
                    $error_router->route($request);
                } catch (\Exception $ex) {
                    error_log('Could not route to error route: '.static::$config->error_routes->$code);
                    echo $err->getMessage();
                }
            } else {
                echo $err->getMessage();
            }
        }
    }

    /**
     * Starts a CLI app's routing
     */
    protected static function thintasks_start()
    {
        try {
            \ThinTasks\Router::start(static::$dir->tasks);
        } catch (\ThinTasks\CommandNotFoundException $ex) {
            echo "Command not found!";
        }
    }

    /**
     * Generates a connection string from a database connection config object
     * @param  object $db_config Database connection config from config file
     * @return string            MDB2 connection string
     */
    protected static function tinydb_generate_connection_strings($db_config)
    {
        if (is_array($db_config)) {
            $r = [];
            foreach ($db_config as $entry)
            {
                $r = array_merge($r, static::tinydb_generate_connection_strings($entry));
            }
            return $r;
        } else if (is_object($db_config)) {
            return [implode('', [
                                $db_config->type,
                                '://',
                                $db_config->username,
                                ':',
                                $db_config->password,
                                '@',
                                $db_config->host,
                                '/',
                                $db_config->db
                            ])];
        } else if (is_string($db_config)) {
            return [$db_config];
        } else {
            return [];
        }
    }
}
