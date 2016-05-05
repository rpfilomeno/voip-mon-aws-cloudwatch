<?php
/**
 * Created by PhpStorm.
 * User: RFilomeno
 * Date: 29/04/2016
 * Time: 6:32 PM
 */

return array(
    'asterisk' => [
        'active-calls' => "sudo asterisk -rx \"core show calls\" | grep \"active\" | cut -d' ' -f1",
        'channel-stats' => "sudo asterisk -rx \"sip show channelstats\""
    ]
);