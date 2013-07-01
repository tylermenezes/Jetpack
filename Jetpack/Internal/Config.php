<?php

namespace Jetpack\Internal;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'require.php');

class Config
{
    protected $config;

    public function __construct($file)
    {
        if (file_exists($file)) {
            $this->config = json_decode(file_get_contents($file));
        } else {
            $this->config = array();
        }
    }

    public function __get($name)
    {
        if (isset($this->config->$name)) {
            return $this->config->$name;
        } else {
            return null;
        }
    }
}
