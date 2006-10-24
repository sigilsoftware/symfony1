<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfWebResponse class.
 *
 * This class manages web reponses. It supports cookies and headers management.
 * 
 * @package    symfony
 * @subpackage response
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfWebResponse extends sfResponse
{
  protected
    $cookies    = array(),
    $statusCode = 200,
    $statusText = 'OK',
    $statusTexts = array();

  /**
   * Initialize this sfWebResponse.
   *
   * @param sfContext A sfContext instance.
   *
   * @return bool true, if initialization completes successfully, otherwise false.
   *
   * @throws <b>sfInitializationException</b> If an error occurs while initializing this Response.
   */
  public function initialize ($context, $parameters = array())
  {
    parent::initialize($context, $parameters);

    $this->statusTexts = array(
      '100' => 'Continue',
      '101' => 'Switching Protocols',
      '200' => 'OK',
      '201' => 'Created',
      '202' => 'Accepted',
      '203' => 'Non-Authoritative Information',
      '204' => 'No Content',
      '205' => 'Reset Content',
      '206' => 'Partial Content',
      '300' => 'Multiple Choices',
      '301' => 'Moved Permanently',
      '302' => 'Found',
      '303' => 'See Other',
      '304' => 'Not Modified',
      '305' => 'Use Proxy',
      '306' => '(Unused)',
      '307' => 'Temporary Redirect',
      '400' => 'Bad Request',
      '401' => 'Unauthorized',
      '402' => 'Payment Required',
      '403' => 'Forbidden',
      '404' => 'Not Found',
      '405' => 'Method Not Allowed',
      '406' => 'Not Acceptable',
      '407' => 'Proxy Authentication Required',
      '408' => 'Request Timeout',
      '409' => 'Conflict',
      '410' => 'Gone',
      '411' => 'Length Required',
      '412' => 'Precondition Failed',
      '413' => 'Request Entity Too Large',
      '414' => 'Request-URI Too Long',
      '415' => 'Unsupported Media Type',
      '416' => 'Requested Range Not Satisfiable',
      '417' => 'Expectation Failed',
      '500' => 'Internal Server Error',
      '501' => 'Not Implemented',
      '502' => 'Bad Gateway',
      '503' => 'Service Unavailable',
      '504' => 'Gateway Timeout',
      '505' => 'HTTP Version Not Supported',
    );
  }

  /**
   * Set a cookie.
   *
   * @param string HTTP header name
   * @param string value
   *
   * @return void
   */
  public function setCookie ($name, $value, $expire = null, $path = '/', $domain = '', $secure = false, $httpOnly = false)
  {
    if ($expire !== null)
    {
      if (is_numeric($expire))
      {
        $expire = (int) $expire;
      }
      else
      {
        $expire = strtotime($expire);
        if ($expire === false || $expire == -1)
        {
          throw new sfException('Your expire parameter is not valid.');
        }
      }
    }

    $this->cookies[] = array(
      'name'     => $name,
      'value'    => $value,
      'expire'   => $expire,
      'path'     => $path,
      'domain'   => $domain,
      'secure'   => $secure ? true : false,
      'httpOnly' => $httpOnly,
    );
  }

  /**
   * Set response status code.
   *
   * @param string HTTP status code
   * @param string HTTP status text
   *
   * @return void
   */
  public function setStatusCode ($code, $name = null)
  {
    $this->statusCode = $code;
    $this->statusText = null !== $name ? $name : $this->statusTexts[$code];
  }

  public function getStatusCode ()
  {
    return $this->statusCode;
  }

  /**
   * Set a HTTP header.
   *
   * @param string HTTP header name
   * @param string value
   *
   * @return void
   */
  public function setHttpHeader ($name, $value, $replace = true)
  {
    $name = $this->normalizeHeaderName($name);

    if ('Content-Type' == $name)
    {
      if ($replace)
      {
        $this->setContentType($value);
      }

      return;
    }

    if (!$replace)
    {
      $current = $this->getParameter($name, '', 'symfony/response/http/headers');
      $value = ($current ? $current.', ' : '').$value;
    }

    $this->setParameter($name, $value, 'symfony/response/http/headers');
  }

  /**
   * Get HTTP header current value.
   *
   * @return array
   */
  public function getHttpHeader ($name, $default = null)
  {
    return $this->getParameter($this->normalizeHeaderName($name), $default, 'symfony/response/http/headers');
  }

  /**
   * Has a HTTP header.
   *
   * @return boolean
   */
  public function hasHttpHeader ($name)
  {
    return $this->hasParameter($this->normalizeHeaderName($name), 'symfony/response/http/headers');
  }

  /**
   * Set response content type.
   *
   * @param string value
   *
   * @return void
   */
  public function setContentType ($value)
  {
    // add charset if needed
    if (false === stripos($value, 'charset'))
    {
      $value .= '; charset='.sfConfig::get('sf_charset');
    }

    $this->setParameter('Content-Type', $value, 'symfony/response/http/headers');
  }

  /**
   * Get response content type.
   *
   * @return array
   */
  public function getContentType ()
  {
    return $this->getHttpHeader('Content-Type', 'text/html; charset='.sfConfig::get('sf_charset'));
  }

  /**
   * Send HTTP headers and cookies.
   *
   * @return void
   */
  public function sendHttpHeaders ()
  {
    // status
    if (substr(php_sapi_name(), 0, 3) == 'cgi' && isset($_SERVER['SERVER_SOFTWARE']) && false !== stripos($_SERVER['SERVER_SOFTWARE'], 'apache/2'))
    {
      // fix bug http://www.symfony-project.com/trac/ticket/669 for apache2/mod_fastcgi
      $status = 'Status: '.$this->statusCode.' '.$this->statusText;
    }
    else
    {
      $status = 'HTTP/1.0 '.$this->statusCode.' '.$this->statusText;
    }

    header($status);

    if (sfConfig::get('sf_logging_active'))
    {
      $this->getContext()->getLogger()->info('{sfWebResponse} send status "'.$status.'"');
    }

    // headers
    foreach ($this->getParameterHolder()->getAll('symfony/response/http/headers') as $name => $value)
    {
      header($name.': '.$value);

      if (sfConfig::get('sf_logging_active') && $value != '')
      {
        $this->getContext()->getLogger()->info('{sfWebResponse} send header "'.$name.'": "'.$value.'"');
      }
    }

    // cookies
    foreach ($this->cookies as $cookie)
    {
      if (version_compare(phpversion(), '5.2', '>='))
      {
        setrawcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httpOnly']);
      }
      else
      {
        setrawcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure']);
      }

      if (sfConfig::get('sf_logging_active'))
      {
        $this->getContext()->getLogger()->info('{sfWebResponse} send cookie "'.$cookie['name'].'": "'.$cookie['value'].'"');
      }
    }
  }

  protected function normalizeHeaderName($name)
  {
    return preg_replace('/\-(.)/e', "'-'.strtoupper('\\1')", strtr(ucfirst(strtolower($name)), '_', '-'));
  }

  public function getDate($timestamp, $type = 'rfc1123')
  {
    $type = strtolower($type);

    if ($type == 'rfc1123')
    {
      return substr(gmdate('r', $timestamp), 0, -5).'GMT';
    }
    else if ($type == 'rfc1036')
    {
      return gmdate('l, d-M-y H:i:s ', $timestamp).'GMT';
    }
    else if ($type == 'asctime')
    {
      return gmdate('D M j H:i:s', $timestamp);
    }
    else
    {
      $error = 'The second getDate() method parameter must be one of: rfc1123, rfc1036 or asctime';

      throw new sfParameterException($error);
    }
  }

  public function addVaryHttpHeader($header)
  {
    $vary = $this->getHttpHeader('Vary');
    $currentHeaders = array();
    if ($vary[0])
    {
      $currentHeaders = split('/\s*,\s*/', $vary[0]);
    }
    $header = $this->normalizeHeaderName($header);

    if (!in_array($header, $currentHeaders))
    {
      $currentHeaders[] = $header;
      $this->setHttpHeader('Vary', implode(', ', $currentHeaders));
    }
  }

  public function addCacheControlHttpHeader($name, $value = null)
  {
    $cacheControl = $this->getHttpHeader('Cache-Control');
    $currentHeaders = array();
    if ($cacheControl[0])
    {
      $currentHeaders = split('/\s*,\s*/', $cacheControl[0]);
    }
    $name = strtr(strtolower($name), '_', '-');

    if (!in_array($name, $currentHeaders))
    {
      $currentHeaders[] = $name.($value !== null ? '='.$value : '');
      $this->setHttpHeader('Cache-Control', implode(', ', $currentHeaders));
    }
  }

  public function getHttpMetas()
  {
    return $this->getParameterHolder()->getAll('helper/asset/auto/httpmeta');
  }

  public function addHttpMeta($key, $value, $replace = true)
  {
    $key = $this->normalizeHeaderName($key);

    // set HTTP header
    $this->setHttpHeader($key, $value, $replace);

    if ('Content-Type' == $key)
    {
      $value = $this->getContentType();
    }

    if (!$replace)
    {
      $current = $this->getParameter($key, '', 'helper/asset/auto/httpmeta');
      $value = ($current ? $current.', ' : '').$value;
    }

    $this->setParameter($key, $value, 'helper/asset/auto/httpmeta');
  }

  public function getMetas()
  {
    return $this->getParameterHolder()->getAll('helper/asset/auto/meta');
  }

  public function addMeta($key, $value, $replace = true, $escape = true)
  {
    $key = $this->normalizeHeaderName($key);

    if (sfConfig::get('sf_i18n'))
    {
      $value = sfConfig::get('sf_i18n_instance')->__($value);
    }

    if ($escape)
    {
      $value = htmlentities($value, ENT_QUOTES, sfConfig::get('sf_charset'));
    }

    if (!$replace)
    {
      $current = $this->getParameter($key, '', 'helper/asset/auto/meta');
      $value = ($current ? $current.', ' : '').$value;
    }

    $this->setParameter($key, $value, 'helper/asset/auto/meta');
  }

  public function getTitle()
  {
    $metas = $this->getParameterHolder()->getAll('helper/asset/auto/meta');

    return (array_key_exists('title', $metas)) ? $metas['title'] : '';
  }

  public function setTitle($title, $escape = true)
  {
    if (sfConfig::get('sf_i18n'))
    {
      $title = sfConfig::get('sf_i18n_instance')->__($title);
    }

    if ($escape)
    {
      $title = htmlentities($title, ENT_QUOTES, sfConfig::get('sf_charset'));
    }

    $this->setParameter('title', $title, 'helper/asset/auto/meta');
  }

  public function getStylesheets($position = '')
  {
    if ($position)
    {
      $position = '/'.$position;
    }

    return $this->getParameterHolder()->getAll('helper/asset/auto/stylesheet'.$position);
  }

  public function addStylesheet($css, $position = '', $options = array())
  {
    if ($position)
    {
      $position = '/'.$position;
    }

    $this->setParameter($css, $options, 'helper/asset/auto/stylesheet'.$position);
  }

  public function getJavascripts($position = '')
  {
    if ($position)
    {
      $position = '/'.$position;
    }

    return $this->getParameterHolder()->getAll('helper/asset/auto/javascript'.$position);
  }

  public function addJavascript($js, $position = '')
  {
    if ($position)
    {
      $position = '/'.$position;
    }

    $this->setParameter($js, $js, 'helper/asset/auto/javascript'.$position);
  }

  public function getCookies()
  {
    $cookies = array();
    foreach ($this->cookies as $cookie)
    {
      $cookies[$cookie['name']] = $cookie;
    }

    return $cookies;
  }

  public function getHttpHeaders()
  {
    return $this->getParameterHolder()->getAll('symfony/response/http/headers');
  }

  public function clearHttpHeaders()
  {
    $this->getParameterHolder()->removeNamespace('symfony/response/http/headers');
  }

  public function mergeProperties($response)
  {
    $ph  = $this->getParameterHolder();
    $phn = $response->getParameterHolder();

    // slots
    $ph->add($phn->getAll('symfony/view/sfView/slot'), 'symfony/view/sfView/slot');

    // view configuration
    $ph->add($phn->getAll('symfony/action/view'), 'symfony/action/view');

    // add stylesheets
    foreach (array('first', '', 'last') as $position)
    {
      $ph->add($response->getStylesheets($position), 'helper/asset/auto/stylesheet'.$position);
    }

    // add javascripts
    foreach (array('first', '', 'last') as $position)
    {
      $ph->add($response->getJavascripts($position), 'helper/asset/auto/javascript'.$position);
    }

    // add headers
    foreach ($response->getHttpHeaders() as $name => $values)
    {
      foreach ($values as $value)
      {
        $this->setHttpHeader($name, $value);
      }
    }
  }

  public function __sleep()
  {
    return array('content', 'statusCode', 'statusText', 'parameter_holder');
  }

  public function __wakeup()
  {
  }

  /**
   * Execute the shutdown procedure.
   *
   * @return void
   */
  public function shutdown ()
  {
  }
}
