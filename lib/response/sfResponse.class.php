<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfResponse provides methods for manipulating client response information such
 * as headers, cookies and content.
 *
 * @package    symfony
 * @subpackage response
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class sfResponse implements Serializable
{
  protected
    $parameterHolder = null,
    $dispatcher      = null,
    $content         = '';

  /**
   * Class constructor.
   *
   * @see initialize()
   */
  public function __construct(sfEventDispatcher $dispatcher, $parameters = array())
  {
    $this->initialize($dispatcher, $parameters);
  }

  /**
   * Initializes this sfResponse.
   *
   * @param  sfEventDispatcher  A sfEventDispatcher instance
   * @param  array         An array of parameters
   *
   * @return Boolean       true, if initialization completes successfully, otherwise false
   *
   * @throws <b>sfInitializationException</b> If an error occurs while initializing this sfResponse
   */
  public function initialize(sfEventDispatcher $dispatcher, $parameters = array())
  {
    $this->dispatcher = $dispatcher;

    $this->parameterHolder = new sfParameterHolder();
    $this->parameterHolder->add($parameters);
  }

  /**
   * Sets the response content
   *
   * @param string Content
   */
  public function setContent($content)
  {
    $this->content = $content;
  }

  /**
   * Gets the current response content
   *
   * @return string Content
   */
  public function getContent()
  {
    return $this->content;
  }

  /**
   * Outputs the response content
   */
  public function sendContent()
  {
    if (sfConfig::get('sf_logging_enabled'))
    {
      $this->dispatcher->notify(new sfEvent($this, 'application.log', array(sprintf('Send content (%s o)', strlen($this->getContent())))));
    }

    echo $this->getContent();
  }

  /**
   * Retrieves the parameters from the current response.
   *
   * @return sfParameterHolder List of parameters
   */
  public function getParameterHolder()
  {
    return $this->parameterHolder;
  }

  /**
   * Retrieves a parameter from the current response.
   *
   * @param string A parameter name
   * @param string A default paramter value
   * @param string Namespace for the current response
   *
   * @return mixed A parameter value
   */
  public function getParameter($name, $default = null, $ns = null)
  {
    return $this->parameterHolder->get($name, $default, $ns);
  }

  /**
   * Indicates whether or not a parameter exist for the current response.
   *
   * @param string A parameter name
   * @param string Namespace for the current response
   *
   * @return boolean true, if the parameter exists otherwise false
   */
  public function hasParameter($name, $ns = null)
  {
    return $this->parameterHolder->has($name, $ns);
  }

  /**
   * Sets a parameter for the current response.
   *
   * @param string A parameter name
   * @param string The parameter value to be set
   * @param string Namespace for the current response
   */
  public function setParameter($name, $value, $ns = null)
  {
    $this->parameterHolder->set($name, $value, $ns);
  }

  /**
   * Calls methods defined via sfEventDispatcher.
   *
   * @param string The method name
   * @param array  The method arguments
   *
   * @return mixed The returned value of the called method
   *
   * @throws <b>sfException</b> If the calls fails
   */
  public function __call($method, $arguments)
  {
    $event = $this->dispatcher->notifyUntil(new sfEvent($this, 'response.method_not_found', array('method' => $method, 'arguments' => $arguments)));
    if (!$event->isProcessed())
    {
      throw new sfException(sprintf('Call to undefined method sfResponse::%s.', $method));
    }

    return $event->getReturnValue();
  }
}
