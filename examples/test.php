<?php

// Demonstrates Yadis discovery on both a URI and an XRI

set_include_path(dirname(__FILE__) . '/../' . PATH_SEPARATOR . get_include_path());

require_once 'Services/Yadis.php';
foreach (array('http://www.yahoo.com', '=self*shupp') as $id) {
    $yadis       = new Services_Yadis($id);
    $serviceList = $yadis->discover();
    foreach ($serviceList as $service) {
        $types = $service->getTypes();
        echo $types[0], ' at ', implode(', ', $service->getUris()), PHP_EOL;
        echo 'Priority is ', $service->getPriority(), PHP_EOL;
    }
}

?>
