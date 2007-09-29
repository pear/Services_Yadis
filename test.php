<?php

set_include_path(dirname(__FILE__) . PATH_SEPARATOR . get_include_path());

require_once 'Services/Yadis.php';
$yadis = new Services_Yadis('http://padraic.astrumfutura.com');
$serviceList = $yadis->discover();
foreach ($serviceList as $service) {
    $types = $service->getTypes();
    echo $types[0], ' at ', implode(', ', $service->getUris()), PHP_EOL;
    echo 'Priority is ', $service->getPriority(), PHP_EOL;
}