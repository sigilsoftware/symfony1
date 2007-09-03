<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/../../bootstrap/unit.php');
require_once(dirname(__FILE__).'/sfCacheDriverTests.class.php');

$t = new lime_test(61, new lime_output_color());

// setup
sfConfig::set('sf_logging_enabled', false);
$temp = tempnam('/tmp/cachedir', 'tmp');
unlink($temp);
mkdir($temp);

// ->initialize()
$t->diag('->initialize()');
try
{
  $cache = new sfFileCache();
  $t->fail('->initialize() throws an sfInitializationException exception if you don\'t pass a "cacheDir" parameter');
}
catch (sfInitializationException $e)
{
  $t->pass('->initialize() throws an sfInitializationException exception if you don\'t pass a "cacheDir" parameter');
}

$cache = new sfFileCache(array('cacheDir' => $temp));

sfCacheDriverTests::launch($t, $cache);

// teardown
sfToolkit::clearDirectory($temp);
rmdir($temp);
