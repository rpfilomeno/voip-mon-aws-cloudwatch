<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 29/04/2016
 * Time: 11:43 AM
 */

namespace Godie;


class Application extends \CLIFramework\Application {
    const NAME = 'voipstat-cloudwatch';
    const VERSION = '1.0.0';



    public function init()
    {
        parent::init();
        $this->command('stats');
        $this->topic('basic');
    }
} 