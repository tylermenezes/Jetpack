<?php
/* # Jetpack */
$jetpack_dir = dirname(dirname(__FILE__));
require_once(implode(DIRECTORY_SEPARATOR, [$jetpack_dir, 'Functions.php'])); // Bootstrap to get access to pathify()

require_once(pathify($jetpack_dir, 'Traits', 'User.php'));
require_once(pathify($jetpack_dir, 'Traits', 'SessionModel.php'));
require_once(pathify($jetpack_dir, 'Internal', 'Config.php'));
require_once(pathify($jetpack_dir, 'Internal', 'SplClassLoader.php'));



/* # Submodules */
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
require_once(pathify($jetpack_submodule_dir, 'ThinTasks', 'ThinTasks', 'Router.php'));
require_once(pathify($jetpack_submodule_dir, 'Twig', 'lib', 'Twig', 'Autoloader.php'));
\Twig_Autoloader::register();
