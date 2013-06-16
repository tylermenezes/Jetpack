<?php

namespace Jetpack;

/* # Loading */

/* ## Jetpack */
$jetpack_dir = dirname(__FILE__);
require_once(implode(DIRECTORY_SEPARATOR, [$jetpack_dir, 'Functions.php'])); // Bootstrap to get access to pathify()
require_once(pathify($jetpack_dir, 'Config.php'));

/* ## Submodules */
$jetpack_submodule_dir = pathify(dirname($jetpack_dir), 'submodules');

// Check if the submodule wasn't checked out recursively
if (!file_exists(pathify($jetpack_submodule_dir, 'TinyDb', 'TinyDb', 'Db.php'))) {
    // See if we can check it out ourselves
    if (function_exists('exec') &&
        is_writable($jetpack_submodule_dir) &&
        is_writable(pathify(app_dir(), '.git'))) {
        exec('cd '.app_dir().';git submodule update --init --recursive 2>&1');
        if (is_cli()) {
            print "[setup] Checked out Jetpack submodules\n";
        }
    } else {
        throw new \Exception('Jetpack submodules were not initialized. Run `git submodule --init --recursive` from '.app_dir());
    }
}

require_once(pathify($jetpack_submodule_dir, 'TinyDb', 'TinyDb', 'Db.php'));
require_once(pathify($jetpack_submodule_dir, 'CuteControllers', 'CuteControllers', 'Router.php'));
require_once(pathify($jetpack_submodule_dir, 'Twig', 'lib', 'Twig', 'Autoloader.php'));
\Twig_Autoloader::register();

class Application
{
    public static $dir = null;
    public static $config = null;
    public static $twig = null;

    const config_file_name = '.config.json';

    public static function start()
    {
        self::load_config();
        self::set_directories();
        self::enable_debugging();
        self::tinydb_connect();
        self::twig_load();

        if (is_cli()) {
            // TODO: tasks
        } else {
            self::cutecontrollers_start();
        }
    }

    /* # Individual Loading Steps */

    /**
     * Loads the config file, copying the default config if it doesn't exist
     */
    private static function load_config()
    {
        $jetpack_dir = dirname(__FILE__);

        $config_file = pathify(app_dir(), self::config_file_name);

        if (!file_exists($config_file)) {
            if (!is_cli()) {
                echo "Config file error. See error logs for details.";
            }
            throw new \Exception("Config file doesn't exist. Create it at ".$config_file.". See the Jetpack docs for more info.");
        }

        self::$config = new Config($config_file);
    }

    /**
     * Gets directory path information and stores them in the $dir object.
     */
    private static function set_directories()
    {
        if (!isset(self::$config->directories->controllers)) {
            throw new \RuntimeException('Config file is missing setting for controllers directory.');
        }

        if (isset(self::$config->directories->includes)) {
            $includes_dir = self::$config->directories->includes;
        } else {
            $includes_dir = 'includes';
        }

        self::$dir = (object)[
            'webroot' => app_dir(),
            'includes' => pathify(app_dir(), $includes_dir),
            'controllers' => pathify(app_dir(), self::$config->directories->controllers)
        ];
    }

    /**
     * Enables debugging to the browser if it's enabled in the config
     */
    private static function enable_debugging()
    {
        if (self::$config->debug) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', 'on');
        }
    }

    /**
     * Connects to the database
     */
    private static function tinydb_connect()
    {
        $write_strings = [];
        $read_strings = [];

        if (is_string(self::$config->db)) {
            $write_strings[] = self::$config->db;
        } else if (is_object(self::$config->db) && !isset(self::$config->db->write)) {
            $write_strings[] = self::tinydb_generate_connection_strings(self::$config->db);
        } else {
            if (isset(self::$config->db->write)) {
                $write_strings = self::tinydb_generate_connection_strings(self::$config->db->write);
            }
            if (isset(self::$config->db->read)) {
                $read_strings = self::tinydb_generate_connection_strings(self::$config->db->read);
            }
        }


        if (count($write_strings) > 0) {
            \TinyDb\Db::set($write_strings, $read_strings);
        }

    }

    /**
     * Automatically loads Twig using the filesystem loader if a path to a templates directory was provided
     */
    private static function twig_load()
    {
        if (isset(self::$config->twig->dir)) {
            self::set_twig(pathify(self::$dir->webroot, self::$config->twig->dir));
        }
    }

    /**
     * Loads twig for the given template directory
     * @param mixed $template_dir_or_loader Template directory to use for templates, or a class which implements Twig_LoaderInterface
     */
    public function set_twig($template_dir_or_loader)
    {
        $twig_config = [];

        // Enable debugging if enabled
        if (isset(self::$config->debug) && self::$config->debug) {
            $twig_config['debug'] = true;
        }

        // Load the config overrides if any
        $twig_config_options = ['charset', 'strict_variables', 'auto_reload', 'autoescape', 'optimizations', 'cache'];
        foreach ($twig_config_options as $option) {
            if (isset(self::$config->twig->$option)) {
                $twig_config[$option] = self::$config->twig->$option;
            }
        }

        // Load the loader if we were given a directory
        if (is_string($template_dir_or_loader)) {
            $template_dir_or_loader = new \Twig_Loader_Filesystem($template_dir_or_loader);
        }

        // Load Twig
        static::$twig = new \Twig_Environment($template_dir_or_loader, $twig_conifg);

        // Load the debugging extension if necessary
        if (isset(self::$config->debug) && self::$config->debug) {
            self::$twig->addExtension(new \Twig_Extension_Debug());
        }
    }

    /**
     * Starts the app's routing
     */
    private static function cutecontrollers_start()
    {
        try {
            \CuteControllers\Router::start(self::$dir->controllers);
        } catch (\CuteControllers\HttpError $err) {
            header("HTTP/1.1 ".$err->getCode()." ".$err->getMessage());

            // Load error page if one was provided
            $code = $err->getCode();
            if (isset(self::$config->error_routes->$code)) {
                try {
                    $error_router = new \CuteControllers\Router(self::$dir->controllers);
                    $request = \CuteControllers\Request::current();
                    $request->path = self::$config->error_routes->$code;
                    $error_router->route($request);
                } catch (\Exception $ex) {
                    error_log('Could not route to error route: '.self::$config->error_routes->$code);
                }
            }
        }
    }

    /**
     * Generates a connection string from a database connection config object
     * @param  object $db_config Database connection config from config file
     * @return string            MDB2 connection string
     */
    private static function tinydb_generate_connection_strings($db_config)
    {
        if (is_array($db_config)) {
            $r = [];
            foreach ($db_config as $entry)
            {
                $r = array_merge($r, self::tinydb_generate_connection_strings($entry));
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
