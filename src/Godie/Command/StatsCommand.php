<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 29/04/2016
 * Time: 3:43 PM
 */

namespace Godie\Command;

use Aws\CloudWatch\CloudWatchClient;
use \Zend\Config\Factory;


class StatsCommand extends \CLIFramework\Command {
    private $settings;
    private $instanceId;
    private $cloudWatch;

    public function brief() { return 'Push the statistics to AWS CloudWatch'; }

    public function aliases() { return array('stat'); }

    /**
     * @throws \Exception
     */
    public function init() {
        parent::init();
        $path = __DIR__ . '/../../../config/*{global,local}*.php';
        $files = glob($path, GLOB_BRACE);
        if(empty($files)) throw new \Exception ('No configuration file defined at ' . $path);
        $this->settings = Factory::fromFiles($files,true);
        if(empty($this->settings)) throw new \Exception ('No configuration data defined');

        $this->cloudWatch = new CloudWatchClient($this->getSettings()->get('aws')->toArray());
        $this->instanceId = file_get_contents('http://instance-data/latest/meta-data/instance-id');

    }

    /**
     * @param \GetOptionKit\OptionCollection $opts
     */
    public function options($opts) {

        $opts->add('t|test','set to test mode only');
        $opts->add('c|credentials:', 'use a credential file')
            ->isa('file') ;
        $opts->add('s|silent','silent mode for cron');
    }

    /**
     * @param $args
     */
    public function arguments($args) {

        $args->add('platform')
            ->validValues(['kamailio','asterisk']);
    }

    public function execute($platform) {
        $metricsCollection = array();
        $metrics = array();
        $timestamp = time();

        switch($platform)
        {
            case 'kamailio':
                $kamailio = $this->getSettings()->get( 'kamailio', null );
                if(!$kamailio) throw new \Exception ('No kamailio configuraiton defined');
                $output = shell_exec($kamailio['all']);
                $lines = explode("\n",$output);
                array_pop($lines);

                foreach($lines as $line) {
                    list( $name, $value ) = explode ( "=", $line );
                    $unit = 'Count';

                    if($name) {

                        //partial match
                        if (preg_match('/^shmem:/', $name)) $unit = 'Bytes';
                        if (preg_match('/^registrar:/', $name)) $unit = 'Seconds';

                        //exact match
                        if($name == 'registrar:rejected_regs') $unit = 'Count';
                        list( $group, $label ) = explode ( ":", $name );

                        settype($value, "float");
                        $metrics[] = array(
                            'MetricName' => 'KamailioUtilization',
                            'Dimensions' => array(
                                array(
                                    'Name' => 'InstanceId',
                                    'Value' => $this->getInstanceid(),
                                ),
                                array(
                                    'Name' => 'Group',
                                    'Value' => trim($group),
                                ),
                                array(
                                    'Name' => 'Name',
                                    'Value' => trim($label),
                                ),
                            ),
                            'Timestamp' => $timestamp,
                            'Unit' => $unit,
                            'Value' => trim($value),
                        );

                    }
                    if(count($metrics) >= 20) {
                        $metricsCollection[ ] = $metrics;
                        $metrics = array();
                    }
                }
                break;

            case 'asterisk':
                $asterisk = $this->getSettings()->get( 'asterisk', null );

                $output = shell_exec($asterisk['active-calls']);
                $activeCalls = trim($output);

                $output = shell_exec($asterisk['channel-stats']);

/*                $output= <<<EOT
Peer             Call ID      Duration Recv: Pack  Lost       (     %) Jitter Send: Pack  Lost       (     %) Jitter
10.0.0.2         509253366ac  02:43:14 0000000168K 0000000000 ( 0.00%) 0.0000 0000000489K 0000000000 ( 0.00%) 0.0000
10.100.2.171     c3aed11f-9f  02:43:14 0000000489K 0000000000 ( 0.00%) 0.0000 0000000168K 0000000000 ( 0.00%) 0.0001
2 active SIP channels
EOT;*/

                $lines = explode("\n",$output);
                array_shift($lines);
                array_pop($lines);

                $receivePacketLoss = 0;
                $receiveJitter = 0;
                $sendPacketLoss = 0;
                $sendJitter = 0;
                $count = 0;
                foreach($lines as $line) {
                    if (preg_match('/.*\(( \d+\.\d+%)\)\s(\d+\.\d+).*\( (\d+\.\d+%)\) (\d+\.\d+)/', $line, $regs)) {
                        $receivePacketLoss += $regs[ 1 ];
                        $receiveJitter += $regs[ 2 ];
                        $sendPacketLoss += $regs[ 3 ];
                        $sendJitter += $regs[ 4 ];
                        $count++;
                    }

                }

                $receivePacketLoss =    ($receivePacketLoss > 0)    ? sprintf("%01.2f",$receivePacketLoss/$count)   : 0;
                $receiveJitter =        ($receiveJitter > 0)        ? sprintf("%01.2f",$receiveJitter/$count)       : 0;
                $sendPacketLoss =       ($sendPacketLoss > 0)       ? sprintf("%01.2f",$sendPacketLoss/$count)      : 0;
                $sendJitter =           ($sendJitter > 0)           ? sprintf("%01.2f",$sendJitter/$count)          : 0;

                $metrics[] = array(
                    'MetricName' => 'AsteriskUtilization',
                    'Dimensions' => array(
                        array(
                            'Name' => 'InstanceId',
                            'Value' => $this->getInstanceid(),
                        ),
                        array(
                            'Name' => 'Group',
                            'Value' => 'core',
                        ),
                        array(
                            'Name' => 'Name',
                            'Value' => 'active_calls',
                        ),
                    ),
                    'Timestamp' => $timestamp,
                    'Unit' => 'Count',
                    'Value' => $activeCalls,
                );

                $metrics[] = array(
                    'MetricName' => 'AsteriskUtilization',
                    'Dimensions' => array(
                        array(
                            'Name' => 'InstanceId',
                            'Value' => $this->getInstanceid(),
                        ),
                        array(
                            'Name' => 'Group',
                            'Value' => 'receive',
                        ),
                        array(
                            'Name' => 'Name',
                            'Value' => 'packet_loss',
                        ),
                    ),
                    'Timestamp' => $timestamp,
                    'Unit' => 'Count',
                    'Value' => $receivePacketLoss,
                );

                $metrics[] = array(
                    'MetricName' => 'AsteriskUtilization',
                    'Dimensions' => array(
                        array(
                            'Name' => 'InstanceId',
                            'Value' => $this->getInstanceid(),
                        ),
                        array(
                            'Name' => 'Group',
                            'Value' => 'receive',
                        ),
                        array(
                            'Name' => 'Name',
                            'Value' => 'jitter',
                        ),
                    ),
                    'Timestamp' => $timestamp,
                    'Unit' => 'Count',
                    'Value' => $receiveJitter,
                );

                $metrics[] = array(
                    'MetricName' => 'AsteriskUtilization',
                    'Dimensions' => array(
                        array(
                            'Name' => 'InstanceId',
                            'Value' => $this->getInstanceid(),
                        ),
                        array(
                            'Name' => 'Group',
                            'Value' => 'send',
                        ),
                        array(
                            'Name' => 'Name',
                            'Value' => 'packet_loss',
                        ),
                    ),
                    'Timestamp' => $timestamp,
                    'Unit' => 'Count',
                    'Value' => $sendPacketLoss,
                );

                $metrics[] = array(
                    'MetricName' => 'AsteriskUtilization',
                    'Dimensions' => array(
                        array(
                            'Name' => 'InstanceId',
                            'Value' => $this->getInstanceid(),
                        ),
                        array(
                            'Name' => 'Group',
                            'Value' => 'send',
                        ),
                        array(
                            'Name' => 'Name',
                            'Value' => 'jitter',
                        ),
                    ),
                    'Timestamp' => $timestamp,
                    'Unit' => 'Count',
                    'Value' => $sendJitter,
                );

                $metricsCollection[ ] = $metrics;

                break;

        }

        foreach($metricsCollection as $metrics) {
            $data = array (
                'Namespace' => 'Platform/VOIP',
                'MetricData' =>
                    $metrics

            );


            if(!$this->options->has('test')) {
                $result = $this->getCloudwatch ()->putMetricData ( $data );
            }
            if(!$this->options->has('silent')) {
                $log = ($this->options->has('test')) ? "TEST MODE - No actual data will be sent.\n" : '';
                $log .= 'putMetricData: ';
                $log .= json_encode ( $data );
                $this->getLogger ()->info ( $log );
            }
        }
    }

    /**
     * @return \Aws\CloudWatch\CloudWatchClient
     */
    public function getCloudwatch ()
    {
        return $this->cloudWatch;
    }

    /**
     * @return String
     */
    public function getInstanceid ()
    {
        return $this->instanceId;
    }

    /**
     * @return \Zend\Config\Config
     */
    public function getSettings ()
    {
        return $this->settings;
    }
}