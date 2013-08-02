<?php

namespace Jetpack\Traits;

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Internal' . DIRECTORY_SEPARATOR . 'require.php');

/**
 * Stores at most one of the model in the current session. Class must extend \TinyDb\Orm
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the Perl Artistic License 2.0.
 *
 * @package Jetpack\Traits
 */
trait SessionModel
{
    public static function current()
    {
        session_start();
        if (!self::has_current()) {
            throw new \RuntimeException('The current session has no '.static::$table_name);
        }

        try {
            return self::one($_SESSION[static::$table_name]);
        } catch (\TinyDb\NoRecordException $ex) {
            self::clear_current();
            throw $ex;
        }
    }

    public function set_current()
    {
        session_start();
        $_SESSION[static::$table_name] = $this->id;
    }

    public function clear_current()
    {
        session_start();
        unset($_SESSION[static::$table_name]);
    }

    public static function has_current()
    {
        session_start();
        return isset($_SESSION[static::$table_name]);
    }
}
