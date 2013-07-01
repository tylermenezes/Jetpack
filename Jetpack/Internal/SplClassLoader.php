<?php

namespace Jetpack\Internal;


/**
 * Requires classes automatically
 * 
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the BSD license.
 *
 * @package Jetpack\Internal
 */
class SplClassLoader {
    const file_extension = '.php';
    const namespace_separator = '\\';

    protected $namespace;
    protected $include_path;

    public function __construct($include_paths, $namespace = null)
    {
        $this->namespace = $namespace;
        $this->include_paths = $include_paths;
        spl_autoload_register(array($this, 'load_class'));
    }

    public function load_class($class_name)
    {
        // Can we load this class?
        if (null === $this->namespace || $this->namespace.self::namespace_separator === substr($class_name, 0,
                strlen($this->namespace.self::namespace_separator))) {

            $file_name = '';
            $namespace = '';

            // Is there a namespace set?
            if (false !== ($last_ns_position = strripos($class_name, self::namespace_separator))) {
                $namespace = substr($class_name, 0, $last_ns_position);
                $class_name = substr($class_name, $last_ns_position + 1);
                $file_name = str_replace(self::namespace_separator, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
            }

            // Add the file name to the class.
            $file_name .= str_replace('_', DIRECTORY_SEPARATOR, $class_name) . self::file_extension;


            foreach ($this->include_paths as $path) {
                if (file_exists($path . DIRECTORY_SEPARATOR . $file_name)) {
                    require_once($path . DIRECTORY_SEPARATOR . $file_name);
                }
            }

        }
    }
}
