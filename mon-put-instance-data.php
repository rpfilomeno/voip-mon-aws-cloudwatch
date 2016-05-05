#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 28/04/2016
 * Time: 10:26 PM
 */

$loader = require 'vendor/autoload.php';
$loader->add('Godie','src');

require 'src/Godie/Application.php';
require 'src/Godie/Command/StatsCommand.php';

$app = new Godie\Application;
$app->runWithTry($argv);
//$app->run($argv);
