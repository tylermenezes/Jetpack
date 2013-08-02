<?php

namespace Jetpack\Internal;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'require.php');

class Config
{
    protected $config;

    public function __construct($file)
    {
        $this->config = (object)[];
        foreach (func_get_args() as $file) {
            if (file_exists($file)) {
                foreach ((array)json_decode(file_get_contents($file)) as $k=>$v) {
                    $this->config->$k = $v;
                }
            }
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
