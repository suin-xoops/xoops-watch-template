#!/usr/bin/env php
<?php

error_reporting(E_ALL ^ E_STRICT);
ini_set('display_errors', 1);

function print_usage()
{
	$scriptName = basename($_SERVER['argv'][0]);

	echo "usage: $scriptName <mainfile_path>", PHP_EOL;
	echo PHP_EOL;
	echo "Template auto-update tool for XOOPS.", PHP_EOL;
	echo PHP_EOL;
	echo "positional arguments:", PHP_EOL;
	echo "  mainfile_path  : Full path to mainfile.php", PHP_EOL;
}

function get_module($dirname)
{
	$moduleHandler = xoops_gethandler('module');
	$module = $moduleHandler->getByDirname($dirname);

	if ( is_object($module) === false )
	{
		return false;
	}

	return $module;
}

if ( $_SERVER['argc'] !== 2 )
{
	print_usage();
	exit(1);
}

$mainfile = $_SERVER['argv'][1];


if ( file_exists($mainfile) === false )
{
	echo "mainfile.php not found: ", $mainfile, PHP_EOL;
	exit(1);
}

// This is need: Developers sometimes use theses parameters as XOOPS_URL
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

define('_LEGACY_PREVENT_EXEC_COMMON_', true);
define('OH_MY_GOD_HELP_ME', true);

require $mainfile;

// Emulate HTTP request
$urlInfo = parse_url(XOOPS_URL);
$_SERVER['HTTP_HOST'] = $urlInfo['host'];
$_SERVER['SERVER_NAME'] = $urlInfo['host'];
$_SERVER['HTTP_REFERER'] = XOOPS_URL . '/index.php';
$_SERVER['REQUEST_URI'] = XOOPS_URL . '/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['REQUEST_URI'];
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_USER_AGENT'] = 'CLI XOOPS Client';

// I'm admin
$_SESSION['xoopsUserId'] = 1;
$_SESSION['xoopsUserGroups'] = array(1, 2, 3);

// Run controller
$root = XCube_Root::getSingleton();
$controller = $root->getController();
$controller->_setupFilterChain();
$controller->_processFilter();
$controller->_setupErrorHandler();
$controller->_setupEnvironment();
$controller->_setupLogger();
$controller->_setupDB();
$controller->_setupLanguage();
$controller->_setupTextFilter();
$controller->_setupConfig();
$controller->_setupDebugger();
error_reporting(E_ALL ^ E_STRICT);//TODO
$controller->_processPreBlockFilter();
$controller->_setupUser();
$controller->setupModuleContext();
$controller->_processModule();
$controller->_processPostFilter();

while ( ob_get_level() > 0 )
{
	ob_end_clean();
}

require_once XOOPS_LEGACY_PATH . '/admin/class/ModuleInstallUtils.class.php';
require_once XOOPS_LEGACY_PATH . '/language/english/admin.php';


// List up modules
echo "Listing up modules...", PHP_EOL;

$moduleHandler = xoops_gethandler('module');
$moduleObjects = $moduleHandler->getObjects();
$moduleDirectoryNames = array();

foreach ( $moduleObjects as $moduleObject )
{
	$moduleDirectoryName = $moduleObject->get('dirname');

	if ( is_dir(XOOPS_MODULE_PATH.'/'.$moduleDirectoryName) === true )
	{
		$moduleDirectoryNames[] = $moduleDirectoryName;
	}
}



function get_trust_dirname($dirname)
{
	$mytrustdirnameFile = XOOPS_MODULE_PATH . '/' . $dirname . '/mytrustdirname.php';

	if ( file_exists($mytrustdirnameFile) === true )
	{
		$mytrustdirname = null;
		require($mytrustdirnameFile);

		if ( $mytrustdirname ) {
			return $mytrustdirname;
		}
	}

	return false;
}

// List up template paths
$templateDirectories = array();

foreach ( $moduleDirectoryNames as $moduleDirectoryName )
{
	$trustDirectoryName = get_trust_dirname($moduleDirectoryName);

	if ( $trustDirectoryName === false )
	{
		$templateDirectory = sprintf('%s/%s/templates', XOOPS_MODULE_PATH, $moduleDirectoryName);
	}
	else
	{
		$templateDirectory = sprintf('%s/modules/%s/templates', XOOPS_TRUST_PATH, $trustDirectoryName);
	}

	if ( is_dir($templateDirectory) === true )
	{
		$templateDirectories[$moduleDirectoryName] = $templateDirectory;
	}
}

// Print template directories.
echo "Template directories", PHP_EOL;

$padding = max(array_map('strlen', array_keys($templateDirectories)));

foreach ( $templateDirectories as $moduleDirectoryName => $templateDirectory )
{
	$moduleDirectoryName = str_pad($moduleDirectoryName, $padding, ' ', STR_PAD_RIGHT);
	echo sprintf("  - %s : %s", $moduleDirectoryName, $templateDirectory), PHP_EOL;
}

class Template
{
	protected $filename = '';
	protected $lastMTime = 0;

	public function __construct($filename)
	{
		$this->filename = $filename;
		$this->lastMTime = filemtime($filename);
	}

	public function getFilename()
	{
		return $this->filename;
	}

	public function updated()
	{
		clearstatcache();

		if ( is_file($this->filename) === false )
		{
			return false;
		}

		$fileMTime = filemtime($this->filename);

		if ( $fileMTime > $this->lastMTime )
		{
			$this->lastMTime = $fileMTime;
			return true;
		}

		return false;
	}
}

class Module
{
	protected $name = '';
	protected $templateDirectory = '';
	/** @var Template[] */
	protected $templates = array();

	public function __construct($name, $templateDirectory)
	{
		$this->name = $name;
		$this->templateDirectory = $templateDirectory;

		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateDirectory));

		foreach ( $files as $file )
		{
			$this->templates[] = new Template($file->getPathname());
		}
	}

	public function getName()
	{
		return $this->name;
	}

	public function hasUpdate()
	{
		$hasUpdate = false;

		foreach ( $this->templates as $template )
		{
			if ( $template->updated() === true )
			{
				echo sprintf('[%s] update: %s', date('Y-m-d H:i:s'), $template->getFilename()), PHP_EOL; // TODO >> use observer
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	function update()
	{
		$updateSuccess = new XCube_Delegate();
		$updateSuccess->register('Legacy_ModuleUpdateAction.UpdateSuccess');

		$updateFail = new XCube_Delegate();
		$updateFail->register('Legacy_ModuleUpdateAction.UpdateFail');

		$module = get_module($this->name);

		$dirname = $module->get('dirname');
		$installer = Legacy_ModuleInstallUtils::createUpdater($dirname);
		$installer->setCurrentXoopsModule($module);

		// Load the manifesto, and set it as the target object.
		$module->loadInfoAsVar($dirname);
		$module->set('name', $module->get('name'));
		$installer->setTargetXoopsModule($module);
		$installer->executeUpgrade();

		if ( $installer->mLog->hasError() === false )
		{
			$updateSuccess->call(new XCube_Ref($module), new XCube_Ref($installer->mLog));
			XCube_DelegateUtils::call(
				'Legacy.Admin.Event.ModuleUpdate.' . ucfirst($dirname . '.Success'),
				new XCube_Ref($module),
				new XCube_Ref($installer->mLog));
			$success = true;
		}
		else
		{
			$updateFail->call(new XCube_Ref($module), new XCube_Ref($installer->mLog));
			XCube_DelegateUtils::call(
				'Legacy.Admin.Event.ModuleUpdate.' . ucfirst($dirname . '.Fail'),
				new XCube_Ref($module),
				new XCube_Ref($installer->mLog));
			$success = false;
		}


/*		foreach ($installer->mLog->mMessages as $message)
		{
			echo sprintf('[%s] update: %s', date('Y-m-d H:i:s'), $message['message']), PHP_EOL; // TODO >> observer
		}
*/
		return $success;
	}
}

echo "Listing up template files...", PHP_EOL;

/** @var Module[] $modules */
$modules = array();

foreach ( $templateDirectories as $moduleDirectoryName => $templateDirectory )
{
	$modules[] = new Module($moduleDirectoryName, $templateDirectory);
}

echo "Start watching update", PHP_EOL;
echo "To stop watching: Ctrl + C", PHP_EOL;

while ( true )
{
	foreach ( $modules as $module )
	{
		if ( $module->hasUpdate() === true )
		{
			$success = $module->update();

			if ( $success === true )
			{
				echo sprintf('[%s] success update module: %s', date('Y-m-d H:i:s'), $module->getName()), PHP_EOL;
			}
			else
			{
				echo sprintf('[%s] fail update module: %s', date('Y-m-d H:i:s'), $module->getName()), PHP_EOL;
			}
		}
	}

	sleep(1);
}
