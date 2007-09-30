<?php
require_once('PEAR/PackageFileManager2.php');
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$packagefile = dirname(__FILE__) . '/package.xml';

$options = array(
    'filelistgenerator' => 'cvs',
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => 'Services',
    'packagedirectory'  => dirname(__FILE__),
    //'packagefile'       => $packagefile,
    'clearcontents'     => true,
    'ignore'            => array('generate_package_xml.php', '.svn', '.cvs*'),
    'dir_roles'         => array(
        'docs'     => 'doc',
        'examples' => 'doc',
        'tests'    => 'test',
    ),
);

$packagexml = &PEAR_PackageFileManager2::importOptions($packagefile, $options);
$packagexml->setPackageType('php');

$packagexml->setPackage('Services_Yadis');
$packagexml->setSummary('Implementation of the Yadis Specification 1.0 protocol for PHP5.');
$packagexml->setDescription("Implementation of the Yadis Specification 1.0 protocol allowing a client to discover a list of Services a Yadis Identity Provider offers.");

$packagexml->setChannel('pear.php.net');

$notes = <<<EOT
* Fixed base directory install issue
EOT;
$packagexml->setNotes($notes);

$packagexml->setPhpDep('5.0.0');
$packagexml->setPearinstallerDep('1.4.0b1');
$packagexml->addPackageDepWithChannel('required', 'PEAR', 'pear.php.net', '1.3.6');
$packagexml->addPackageDepWithChannel('required', 'HTTP_Request', 'pear.php.net');
$packagexml->addPackageDepWithChannel('required', 'Validate', 'pear.php.net');
$packagexml->addExtensionDep('required', 'simplexml');

$packagexml->addMaintainer('lead', 'padraic', 'Pádraic Brady', 'padraic@php.net');
$packagexml->setLicense('New BSD License', 'http://opensource.org/licenses/bsd-license.php');

$packagexml->addRelease();
$packagexml->generateContents();

$packagexml->setAPIVersion('0.2.1');
$packagexml->setReleaseVersion('0.2.1');
$packagexml->setReleaseStability('beta');
$packagexml->setAPIStability('beta');

if (isset($_GET['make']) || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')) {
    $packagexml->writePackageFile();
} else {
    $packagexml->debugPackageFile();
}