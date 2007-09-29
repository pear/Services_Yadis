<?php
require_once('PEAR/PackageFileManager2.php');
PEAR::setErrorHandling(PEAR_ERROR_DIE);
$packagexml = new PEAR_PackageFileManager2;

$e = $packagexml->setOptions(
    array('baseinstalldir' => 'Services',
     'packagedirectory' => 'D:/xampp/htdocs/projects/pear/trunk/Services_Yadis',
     'filelistgenerator' => 'file',
     'dir_roles' => array('docs' => 'doc', 'tests' => 'test'),
     'ignore' => array('generate_package_xml.php', '.svn')
    )
);

$packagexml->setPackage('Services_Yadis');
$packagexml->setSummary('Implementation of Yadis Specification 1.0 protocol for PHP5');
$packagexml->setDescription("Implementation of the Yadis Specification 1.0 protocol allowing a client to discover a list of Services a Yadis Identity Provider offers.");
$packagexml->setChannel('pear.php.net');
$packagexml->setAPIVersion('0.1.0');
$packagexml->setReleaseVersion('0.1.0a3');
$packagexml->setReleaseStability('alpha');
$packagexml->setAPIStability('alpha');
$packagexml->setNotes("* Added package dependencies for HTTP_Request and Validate");
$packagexml->setPackageType('php');
$packagexml->setPhpDep('5.1.4');
$packagexml->setPearinstallerDep('1.4.0');
$packagexml->addPackageDepWithChannel('required', 'HTTP_Request', 'pear.php.net');
$packagexml->addPackageDepWithChannel('required', 'Validate', 'pear.php.net');
$packagexml->addMaintainer('lead', 'padraic', 'Pádraic Brady', 'padraic.brady@yahoo.com');
$packagexml->setLicense('New BSD License', 'http://opensource.org/licenses/bsd-license.php');
$packagexml->generateContents();

if (isset($_GET['make']) || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')) {
    $packagexml->writePackageFile();
} else {
    $packagexml->debugPackageFile();
}
?>