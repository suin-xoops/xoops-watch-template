#!/usr/bin/env php
<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

function print_usage()
{
	$scriptName = basename($_SERVER['argv'][0]);

	echo "usage: $scriptName <mainfile_path> <module_dirname> <watch_dir>", PHP_EOL;
	echo PHP_EOL;
	echo "Template auto-update tool for XOOPS.", PHP_EOL;
	echo PHP_EOL;
	echo "positional arguments:", PHP_EOL;
	echo "  mainfile_path  : Full path to mainfile.php", PHP_EOL;
	echo "  module_dirname : Module directory name", PHP_EOL;
	echo "  watch_dir      : Directory to watch", PHP_EOL;
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

function execute_module_update($dirname)
{
	$updateSuccess = new XCube_Delegate();
	$updateSuccess->register('Legacy_ModuleUpdateAction.UpdateSuccess');

	$updateFail = new XCube_Delegate();
	$updateFail->register('Legacy_ModuleUpdateAction.UpdateFail');

	$module = get_module($dirname);

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
		return true;
	}
	else
	{
		$updateFail->call(new XCube_Ref($module), new XCube_Ref($installer->mLog));
		XCube_DelegateUtils::call(
			'Legacy.Admin.Event.ModuleUpdate.' . ucfirst($dirname . '.Fail'),
			new XCube_Ref($module),
			new XCube_Ref($installer->mLog));
		return false;
	}

	/*
	foreach ( $installer->mLog->mMessages as $message )
	{
		echo $message['message'], PHP_EOL;
	}
	*/
}

if ( $_SERVER['argc'] !== 4 )
{
	print_usage();
	exit(1);
}

$mainfile = $_SERVER['argv'][1];
$dirname  = $_SERVER['argv'][2];
$watchDir = $_SERVER['argv'][3];
$watchDir = rtrim($watchDir, '/\\');

if ( file_exists($mainfile) === false )
{
	echo "mainfile.php not found: ", $mainfile, PHP_EOL;
	exit(1);
}

// This is need: Developers sometimes use theses parameters as XOOPS_URL
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

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
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

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

$module = get_module($dirname);

if ( is_object($module) === false )
{
	echo "Module not found: ", $dirname, PHP_EOL;
	exit(1);
}

if ( file_exists($watchDir) === false )
{
	echo "Watch directory not found: ", $watchDir, PHP_EOL;
	exit(1);
}

if ( is_dir($watchDir) === false )
{
	echo "Not directory: ", $watchDir, PHP_EOL;
	exit(1);
}

if ( is_readable($watchDir) === false )
{
	echo "Not readable: ", $watchDir, PHP_EOL;
	exit(1);
}

echo "Start watching module: ", $module->get('name'), ' ', $watchDir , PHP_EOL;
echo "To stop watching: Ctrl + C", PHP_EOL;

$watchDirFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($watchDir));

$files = array();

foreach ( $watchDirFiles as $file )
{
	$file->lastMTime = $file->getMTime();
	$files[] = $file;
}

while ( true )
{
	$hasUpdate = false;
	clearstatcache();

	foreach ( $files as $file )
	{
		if ( $file->isFile() === false )
		{
			continue;
		}

		if ( $file->getMTime() > $file->lastMTime )
		{
			echo sprintf('[%s] update: %s', date('Y-m-d H:i:s'), $file), PHP_EOL;
			$hasUpdate = true;
		}

		$file->lastMTime = $file->getMTime();
	}

	if ( $hasUpdate === true )
	{
		$success = execute_module_update($dirname);

		if ( $success === true )
		{
			echo sprintf('[%s] success update module: %s', date('Y-m-d H:i:s'), $dirname), PHP_EOL;
		}
		else
		{
			echo sprintf('[%s] fail update module: %s', date('Y-m-d H:i:s'), $dirname), PHP_EOL;
		}
	}

	sleep(1);
}
