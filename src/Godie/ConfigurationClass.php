<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 29/04/2016
 * Time: 7:24 PM
 */

namespace Godie;


class ConfigurationClass {

    private $KamailioConfig;
    private $AsteriskConfig;

    public function __construct(){
        $path = __DIR__ . '/../../config/*{global,local}*.php';

        $files = glob($path, GLOB_BRACE);
        if(empty($files)) throw new \Exception ('No configuration files defined at ' . $path );
        $settings = \Zend\Config\Factory::fromFiles($files,true);
        if(empty($settings)) throw new \Exception ('No configuration data defined');
        $this->KamailioConfig = $settings->get('kamailio', null);
        $this->AsteriskConfig = $settings->get('asterisk', null);
    }

    /**
     * @return \Zend\Config\Config
     */
    public function getKamailioConfig ()
    {
        return $this->KamailioConfig;
    }

    /**
     * @return \Zend\Config\Config
     */
    public function getAsteriskConfig ()
    {
        return $this->AsteriskConfig;
    }

} 