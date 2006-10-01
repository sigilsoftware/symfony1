<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please component the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/../bootstrap.php');
require_once($_test_dir.'/unit/sfContextMock.class.php');

$t = new lime_test(1, new lime_output_color());

class myComponent extends sfComponent
{
  function execute () {}
}

$context = new sfContext();
$component = new myComponent();
$component->initialize($context);

// mixins
require_once($_test_dir.'/unit/sfMixerTest.class.php');
$mixert = new sfMixerTest($t);
$mixert->launchTests($component, 'sfComponent');
