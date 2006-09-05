<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfViewConfigHandler allows you to configure views.
 *
 * @package    symfony
 * @subpackage config
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfViewConfigHandler extends sfYamlConfigHandler
{
  /**
   * Execute this configuration handler.
   *
   * @param array An array of absolute filesystem path to a configuration file.
   *
   * @return string Data to be written to a cache file.
   *
   * @throws <b>sfConfigurationException</b> If a requested configuration file does not exist or is not readable.
   * @throws <b>sfParseException</b> If a requested configuration file is improperly formatted.
   * @throws <b>sfInitializationException</b> If a view.yml key check fails.
   */
  public function execute($configFiles)
  {
    // set our required categories list and initialize our handler
    $categories = array('required_categories' => array());
    $this->initialize($categories);

    // parse the yaml
    $myConfig = $this->parseYamls($configFiles);

    $myConfig['all'] = sfToolkit::arrayDeepMerge(
      isset($myConfig['default']) && is_array($myConfig['default']) ? $myConfig['default'] : array(),
      isset($myConfig['all']) && is_array($myConfig['all']) ? $myConfig['all'] : array()
    );

    // merge javascripts and stylesheets
    $myConfig['all']['stylesheets'] = array_merge(isset($myConfig['default']['stylesheets']) && is_array($myConfig['default']['stylesheets']) ? $myConfig['default']['stylesheets'] : array(), isset($myConfig['all']['stylesheets']) && is_array($myConfig['all']['stylesheets']) ? $myConfig['all']['stylesheets'] : array());
    $myConfig['all']['javascripts'] = array_merge(isset($myConfig['default']['javascripts']) && is_array($myConfig['default']['javascripts']) ? $myConfig['default']['javascripts'] : array(), isset($myConfig['all']['javascripts']) && is_array($myConfig['all']['javascripts']) ? $myConfig['all']['javascripts'] : array());

    unset($myConfig['default']);

    $this->yamlConfig = $myConfig;

    // init our data array
    $data = array();

    $data[] = "\$response = \$action->getResponse();\n";

    // iterate through all view names
    $first = true;
    foreach ($this->yamlConfig as $viewName => $values)
    {
      if ($viewName == 'all')
      {
        continue;
      }

      $data[] = ($first ? '' : 'else ')."if (\$this->actionName.\$this->viewName == '$viewName')\n".
                "{\n";

      $data[] = $this->addTemplate($viewName);

      $data[] = "  if (!\$actionStackEntry->isSlot())\n";
      $data[] = "  {\n";

      $data[] = $this->addLayout($viewName);
      $data[] = $this->addComponentSlots($viewName);
      $data[] = $this->addHtmlHead($viewName);
      $data[] = $this->addEscaping($viewName);

      $data[] = "  }\n";

      $data[] = $this->addHtmlAsset($viewName);

      $data[] = "}\n";

      $first = false;
    }

    // general view configuration
    $data[] = ($first ? '' : "else\n{")."\n";

    $data[] = $this->addTemplate();

    $data[] = "  if (!\$actionStackEntry->isSlot())\n";
    $data[] = "  {\n";

    $data[] = $this->addLayout();
    $data[] = $this->addComponentSlots();
    $data[] = $this->addHtmlHead();
    $data[] = $this->addEscaping();

    $data[] = "  }\n";

    $data[] = $this->addHtmlAsset();
    $data[] = ($first ? '' : "}")."\n";

    // compile data
    $retval = sprintf("<?php\n".
                      "// auto-generated by sfViewConfigHandler\n".
                      "// date: %s\n%s\n",
                      date('Y/m/d H:i:s'), implode('', $data));

    return $retval;
  }

  private function addComponentSlots($viewName = '')
  {
    $data = '';

    $components = $this->mergeConfigValue('components', $viewName);
    foreach ($components as $name => $component)
    {
      if (!is_array($component) || count($component) < 1)
      {
        $component = array(null, null);
      }

      $data .= "    \$this->setComponentSlot('$name', '{$component[0]}', '{$component[1]}');\n";
      $data .= "    if (sfConfig::get('sf_logging_active')) \$context->getLogger()->info('{sfViewConfig} set component \"$name\" ({$component[0]}/{$component[1]})');\n";
    }

    return $data;
  }

  private function addTemplate($viewName = 'all')
  {
    $data = '';

    $templateName = $this->getConfigValue('template', $viewName);
    $defaultTemplateName = $templateName ? "'$templateName'" : '$this->getContext()->getActionName()';
    $data .= "  \$templateName = null !== \$action->getTemplate() ? \$action->getTemplate() : $defaultTemplateName;\n";
    $data .= "  \$this->setTemplate(\$templateName.\$this->viewName.\$this->getExtension());\n";

    return $data;
  }

  private function addLayout($viewName = '')
  {
    $data = '';

    $layoutNameFromView = $this->getConfigValue('layout', $viewName);
    $hasLayout = $this->getConfigValue('has_layout', $viewName) && $layoutNameFromView != false ? 1 : 0;
    $localHasLayout = isset($this->yamlConfig[$viewName]['layout']) && $this->yamlConfig[$viewName]['layout'] ? 1 : 0;
    // if a layout specified for this view, no need to check if a layout name is specified by the action
    $cond1 = $hasLayout ? '' : "&& null !== \$layoutNameFromAction";
    // if a layout is specified especially for this view, no need to test if the request is AJAX
    $cond2 = $localHasLayout ? '' : "&& (!\$action->getRequest()->isXmlHttpRequest() || null !== \$layoutNameFromAction)";
    $data .= <<<EOF
     \$layoutNameFromAction = \$action->getLayout();
     if (false !== \$layoutNameFromAction $cond1 $cond2)
     {
       \$layoutName = \$layoutNameFromAction ? \$layoutNameFromAction : '$layoutNameFromView';
       \$this->setDecoratorDirectory(sfConfig::get('sf_app_template_dir'));
       \$this->setDecoratorTemplate(\$layoutName.'.php');
     }

EOF;

    return $data;
  }

  private function addHtmlHead($viewName = '')
  {
    $data = array();

    foreach ($this->mergeConfigValue('http_metas', $viewName) as $httpequiv => $content)
    {
      $data[] = sprintf("    \$response->addHttpMeta('%s', '%s', false);", $httpequiv, str_replace('\'', '\\\'', $content));
    }

    foreach ($this->mergeConfigValue('metas', $viewName) as $name => $content)
    {
      $data[] = sprintf("    \$response->addMeta('%s', '%s', false, true);", $name, str_replace('\'', '\\\'', preg_replace('/&amp;(?=\w+;)/', '&', htmlentities($content, ENT_QUOTES, sfConfig::get('sf_charset')))));
    }

    return implode("\n", $data)."\n";
  }

  private function addHtmlAsset($viewName = '')
  {
    $data = array();
    $omit = array();
    $delete = array();
    $delete_all = false;

    // Populate $stylesheets with the values from ONLY the current view
    $stylesheets = $this->getConfigValue('stylesheets', $viewName);

    // If we find results from the view, check to see if there is a '-*'
    // This indicates that we will remove ALL stylesheets EXCEPT for those passed in the current view
    if (is_array($stylesheets) AND in_array('-*', $stylesheets))
    {
      $delete_all = true;
      foreach ($stylesheets as $stylesheet)
      {
        $key = is_array($stylesheet) ? key($stylesheet) : $stylesheet;

        if ($key != '-*')
        {
          $omit[] = $key;
        }
      }
    }
    else
    {
      // If '-*' is not found and there are items in the current view's stylesheet array
      // loop through each one and see if there are any values that start with '-'.
      // If so, we add store the actual stylesheet name to the $delete array to be used below
      foreach ($stylesheets as $stylesheet)
      {
        if (!is_array($stylesheet))
        {
          if (substr($stylesheet, 0, 1) == '-')
          {
          $delete[] = substr($stylesheet, 1);
          }
        }
      }
    }

    // Merge the current view's stylesheets with the app's default stylesheets
    $stylesheets = $this->mergeConfigValue('stylesheets', $viewName);
    if (is_array($stylesheets))
    {
      // Loop through each stylesheet in the merged array
      foreach ($stylesheets as $index => $stylesheet)
      {
        $key = is_array($stylesheet) ? key($stylesheet) : $stylesheet;

        // If $delete_all is true, a '-*' was found above.
        // We remove all stylesheets from the array EXCEPT those specified in the $omit array
        if ($delete_all == true)
        {
          if (!in_array($key, $omit))
          {
            unset($stylesheets[$index]);
          }
        }
        else
        {
          // Loop through the $delete array and see if the stylesheet name is in the array
          // We check for both the stylesheet and the -stylesheet. If found, we remove them.
          foreach ($delete as $value)
          {
            if ($key == $value OR substr($key, 1) == $value)
            {
              unset($stylesheets[$index]);
            }
          }
        }
      }

      foreach ($stylesheets as $css)
      {
        $position = '';
        if (is_array($css))
        {
          $key = key($css);
          $options = $css[$key];
          if (isset($options['position']))
          {
            $position = $options['position'];
            unset($options['position']);
          }
        }
        else
        {
          $key = $css;
          $options = array();
        }

        if ($key)
        {
          $data[] = sprintf("  \$response->addStylesheet('%s', '%s', %s);", $key, $position, var_export($options, true));
        }
      }
    }

    $omit = array();
    $delete_all = false;

    // Populate $javascripts with the values from ONLY the current view
    $javascripts = $this->getConfigValue('javascripts', $viewName);

    // If we find results from the view, check to see if there is a '-*'
    // This indicates that we will remove ALL javascripts EXCEPT for those passed in the current view
    if (is_array($javascripts) AND in_array('-*', $javascripts))
    {
      $delete_all = true;
      foreach ($javascripts as $javascript)
      {     
        if (substr($javascript, 0, 1) != '-')
        {
          $omit[] = $javascript;
        }
      }
    }

    $javascripts = $this->mergeConfigValue('javascripts', $viewName);
    if (is_array($javascripts))
    {
      // remove javascripts marked with a beginning '-'
      // We exclude any javascripts that were omitted above
      $delete = array();

      foreach ($javascripts as $javascript)
      {
        if (!in_array($javascript, $omit) && (substr($javascript, 0, 1) == '-' || $delete_all == true))
        {
          $delete[] = $javascript;
          $delete[] = substr($javascript, 1);
        }
      }
      $javascripts = array_diff($javascripts, $delete);

      foreach ($javascripts as $js)
      {
        if ($js)
        {
          $data[] = sprintf("  \$response->addJavascript('%s');", $js);
        }
      }
    }

    return implode("\n", $data)."\n";
  }

  private function addEscaping($viewName = '')
  {
    $data = array();

    $escaping = $this->getConfigValue('escaping', $viewName);

    if(isset($escaping['strategy']))
    {
      $data[] = sprintf("  \$this->setEscaping(%s);", var_export($escaping['strategy'], true));
    }

    if(isset($escaping['method']))
    {
      $data[] = sprintf("  \$this->setEscapingMethod(%s);", var_export($escaping['method'], true));
    }

    return implode("\n", $data)."\n";
  }
}
