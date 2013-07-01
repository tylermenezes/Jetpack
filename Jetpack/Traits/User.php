<?php

namespace Jetpack\Traits;

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Internal' . DIRECTORY_SEPARATOR . 'require.php');

/**
 * Stores the current object in the session, and throws 401 Forbidden errors if ::current() is accessed without a current model.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) Tyler Menezes. Released under the BSD license.
 *
 * @package Jetpack\Traits
 */
trait User
{
    /**
     * Gets the current user. Throws a 401 HTTP error if the user is not logged in.
     *
     * @return mixed    User model, if one was set for the current session
     * @throws \CuteControllers\HttpError
     */
    public static function me()
    {
        session_start();
        if (!self::is_logged_in()) {
            throw new \CuteControllers\HttpError(401);
        }

        try {
            return self::one($_SESSION[static::$table_name]);
        } catch (\TinyDb\NoRecordException $ex) {
            // In the rare case it gets deleted, call logout before throwing the error so the user can continue without clearing cookies.
            self::logout();
            throw $ex;
        }
    }

    /**
     * Marks the user model as the current user for this session
     */
    public function login()
    {
        session_start();
        $_SESSION[static::$table_name] = $this->id;
    }

    /**
     * Checks if the user is logged in.
     *
     * @return bool True if the user is logged in, false otherwise
     */
    public static function is_logged_in()
    {
        session_start();
        return isset($_SESSION[static::$table_name]);
    }

    /**
     * Logs the user out
     */
    public static function logout()
    {
        session_start();
        unset($_SESSION[static::$table_name]);
    }

    private static $password_hashing_algorithm ='sha256';
    private static $password_hashing_length = 64;
    private static $password_seperator = '$';

    /**
     * Magic setter for password field.
     *
     * @param $new_password string  New password to set
     */
    public function set_password($new_password)
    {
        $this->password  = $this->create_password($new_password);
    }

    /**
     * Magic getter for password field. Throws an exception to prevent hash leaks.
     *
     * @throws \TinyDb\AccessException
     */
    public function get_password()
    {
        throw new \TinyDb\AccessException('Password is hashed -- use check_password($password_to_check) instead.');
    }

    /**
     * Gets a password hash for the specified new password. Magic creator for password field.
     *
     * @param $new_password string  New password
     * @return string               Salted and hashed password
     */
    protected function create_password($new_password)
    {
        self::verify_password_length();
        if (defined(MCRYPT_DEV_URANDOM)) {
            $salt = hash(self::$password_hashing_algorithm, mcrypt_create_iv(64, MCRYPT_DEV_URANDOM));
        } else {
            $salt = hash(self::$password_hashing_algorithm, time() . mt_rand(0, mt_getrandmax()));
        }

        $hash = hash(self::$password_hashing_algorithm, implode(self::$password_seperator, [$salt, $new_password]));

        return implode(self::$password_seperator, [self::$password_hashing_algorithm, $salt, $hash]);
    }

    /**
     * Checks if the specified password was correct
     *
     * @param $check_password   string  Password to validate
     * @return bool                     True if the passwords matched, false otherwise.
     */
    public function check_password($check_password)
    {
        self::verify_password_length();
        list($algorithm, $salt, $expected_hash) = explode(self::$password_seperator, $this->password);

        return $expected_hash === hash($algorithm, implode(self::$password_seperator, [$salt, $check_password]));
    }

    private static function verify_password_length()
    {
        // The password field is going to be two hashes, plus the name of the hash algorithm, plus two separators:
        $min_length = (self::$password_hashing_length * 2) + strlen(self::$password_hashing_algorithm) +
        (strlen(self::$password_seperator) * 2);

        $table_info = new \TinyDb\Internal\TableInfo(static::$table_name);

        if (!$table_info->is_stringy('password') ||
            (($table_info->field_info('password', 'type') === 'char' || $table_info->field_info('password', 'type') === 'varchar') &&
                !$table_info->field_info('password', 'length') < $min_length)) {
            throw new \Exception('`password` field in `'.static::$table_name.'` does not exist, is not stringy, or is not long enough to '.
            'store password hash. It should be a stringy type at least '.$min_length.' characters long.');
        }
    }
}
