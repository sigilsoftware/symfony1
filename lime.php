<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Unit test library.
 *
 * @package    lime
 * @author     Fabien Potencier <fabien.potencier@gmail.com>
 * @version    SVN: $Id$
 */

class lime_test
{
  public $plan = null;
  public $test_nb = 0;
  public $failed = 0;
  public $passed = 0;
  public $skipped = 0;
  public $output = null;

  function __construct($plan = null, $output_instance = null)
  {
    $this->plan = $plan;
    $this->output = $output_instance ? $output_instance : new lime_output();

    null !== $this->plan and $this->output->echoln(sprintf("1..%d", $this->plan));
  }

  function __destruct()
  {
    $total = $this->passed + $this->failed + $this->skipped;

    null === $this->plan and $this->plan = $total and $this->output->echoln(sprintf("1..%d", $this->plan));

    if ($total > $this->plan)
    {
      $this->output->diag(sprintf("Looks like you planned %d tests but ran %d extra.", $this->plan, $total - $this->plan));
    }
    elseif ($total < $this->plan)
    {
      $this->output->diag(sprintf("Looks like you planned %d tests but only ran %d.", $this->plan, $total));
    }

    if ($this->failed)
    {
      $this->output->diag(sprintf("Looks like you failed %d tests of %d.", $this->failed, $this->plan));
    }
  }

  function ok($exp, $message = '')
  {
    if ($result = (boolean) $exp)
    {
      ++$this->passed;
    }
    else
    {
      ++$this->failed;
    }
    $this->output->echoln(sprintf("%s %d%s", $result ? 'ok' : 'not ok', ++$this->test_nb, $message = $message ? sprintf('%s %s', 0 === strpos($message, '#') ? '' : ' -', $message) : ''));

    $traces = debug_backtrace();
    $i = strstr($traces[0]['file'], $_SERVER['PHP_SELF']) ? 0 : 1;

    !$result and $this->output->diag(sprintf('    Failed test (%s at line %d)', str_replace(getcwd(), '.', $traces[$i]['file']), $traces[$i]['line']));

    return $result;
  }

  function is($exp1, $exp2, $message = '')
  {
    if (!$result = $this->ok($exp1 == $exp2, $message))
    {
      $this->output->diag(sprintf("           got: %s", str_replace("\n", '', var_export($exp1, true))), sprintf("      expected: %s", str_replace("\n", '', var_export($exp2, true))));
    }

    return $result;
  }

  function isnt($exp1, $exp2, $message = '')
  {
    if (!$result = $this->ok($exp1 != $exp2, $message))
    {
      $this->output->diag(sprintf("      %s", str_replace("\n", '', var_export($exp1, true))), '          ne', sprintf("      %s", str_replace("\n", '', var_export($exp2, true))));
    }

    return $result;
  }

  function like($exp, $regex, $message = '')
  {
    if (!$result = $this->ok(preg_match($regex, $exp), $message))
    {
      $this->output->diag(sprintf("                    '%s'", $exp), sprintf("      doesn't match '%s'", $regex));
    }

    return $result;
  }

  function unlike($exp, $regex, $message = '')
  {
    if (!$result = $this->ok(!preg_match($regex, $exp), $message))
    {
      $this->output->diag(sprintf("               '%s'", $exp), sprintf("      matches '%s'", $regex));
    }

    return $result;
  }

  function cmp_ok($exp1, $op, $exp2, $message = '')
  {
    eval(sprintf("\$result = \$exp1 $op \$exp2;"));
    if (!$this->ok($result, $message))
    {
      $this->output->diag(sprintf("      %s", str_replace("\n", '', var_export($exp1, true))), sprintf("          %s", $op), sprintf("      %s", str_replace("\n", '', var_export($exp2, true))));
    }

    return $result;
  }

  function can_ok($object, $methods, $message = '')
  {
    $result = true;
    $failed_messages = array();
    foreach ((array) $methods as $method)
    {
      if (!method_exists($object, $method))
      {
        $failed_messages[] = sprintf("      method '%s' does not exist", $method);
        $result = false;
      }
    }

    !$this->ok($result, $message);

    !$result and $this->output->diag($failed_messages);

    return $result;
  }

  function isa_ok($var, $class, $message = '')
  {
    $type = is_object($var) ? get_class($var) : gettype($var);
    if (!$result = $this->ok($type == $class, $message))
    {
      $this->output->diag(sprintf("      isa_ok isn't a '%s' it's a '%s'", $class, $type));
    }

    return $result;
  }

  function is_deeply($exp1, $exp2, $message = '')
  {
    if (!$result = $this->ok($this->test_is_deeply($exp1, $exp2), $message))
    {
      $this->output->diag(sprintf("           got: %s", str_replace("\n", '', var_export($exp1, true))), sprintf("      expected: %s", str_replace("\n", '', var_export($exp2, true))));
    }

    return $result;
  }

  function pass($message = '')
  {
    return $this->ok(true, $message);
  }

  function fail($message = '')
  {
    return $this->ok(false, $message);
  }

  function diag($message)
  {
    $this->output->diag($message);
  }

  function skip($message = '', $nb_tests = 1)
  {
    for ($i = 0; $i < $nb_tests; $i++)
    {
      ++$this->skipped and --$this->passed;
      $this->pass(sprintf("# SKIP%s", $message ? ' '.$message : ''));
    }
  }

  function todo($message = '')
  {
    ++$this->skipped and --$this->passed;
    $this->pass(sprintf("# TODO%s", $message ? ' '.$message : ''));
  }

  function include_ok($file, $message = '')
  {
    if (!$result = $this->ok((@include($file)) == 1, $message))
    {
      $this->output->diag(sprintf("      Tried to include '%s'", $file));
    }

    return $result;
  }

  private function test_is_deeply($var1, $var2)
  {
    if (gettype($var1) != gettype($var2))
    {
      return false;
    }

    if (is_array($var1))
    {
      ksort($var1);
      ksort($var2);
      if (array_diff(array_keys($var1), array_keys($var2)))
      {
        return false;
      }
      $is_equal = true;
      foreach ($var1 as $key => $value)
      {
        $is_equal = $this->test_is_deeply($var1[$key], $var2[$key]);
        if ($is_equal === false)
        {
          break;
        }
      }

      return $is_equal;
    }
    else
    {
      return $var1 === $var2;
    }
  }

  function comment($message)
  {
    $this->output->comment($message);
  }
}

class lime_output
{
  function diag()
  {
    $messages = func_get_args();
    foreach ($messages as $message)
    {
      $this->echoln('# '.join("\n# ", (array) $message));
    }
  }

  function comment($message)
  {
    echo "$message\n";
  }

  function echoln($message)
  {
    echo "$message\n";
  }
}

class lime_output_color extends lime_output
{
  public $colorizer = null;

  function __construct()
  {
    $this->colorizer = new lime_colorizer();
  }

  function diag()
  {
    $messages = func_get_args();
    foreach ($messages as $message)
    {
      echo $this->colorizer->colorize('# '.join("\n# ", (array) $message), 'COMMENT')."\n";
    }
  }

  function comment($message)
  {
    echo $this->colorizer->colorize(sprintf('# %s', $message), 'COMMENT')."\n";
  }

  function echoln($message, $colorizer_parameter = null)
  {
    $message = preg_replace('/(?:^|\.)((?:not ok|dubious) *\d*)\b/e', '$this->colorizer->colorize(\'$1\', \'ERROR\')', $message);
    $message = preg_replace('/(?:^|\.)(ok *\d*)\b/e', '$this->colorizer->colorize(\'$1\', \'INFO\')', $message);
    $message = preg_replace('/"(.+?)"/e', '$this->colorizer->colorize(\'$1\', \'PARAMETER\')', $message);
    $message = preg_replace('/(\->|\:\:)?([a-zA-Z0-9_]+?)\(\)/e', '$this->colorizer->colorize(\'$1$2()\', \'PARAMETER\')', $message);

    echo ($colorizer_parameter ? $this->colorizer->colorize($message, $colorizer_parameter) : $message)."\n";
  }
}

class lime_colorizer
{
  static public $styles = array();

  static function style($name, $options = array())
  {
    self::$styles[$name] = $options;
  }

  static function colorize($text = '', $parameters = array())
  {
    // disable colors if not supported (windows or non tty console)
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' || !function_exists('posix_isatty') || !@posix_isatty(STDOUT))
    {
      return $text;
    }

    static $options    = array('bold' => 1, 'underscore' => 4, 'blink' => 5, 'reverse' => 7, 'conceal' => 8);
    static $foreground = array('black' => 30, 'red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'magenta' => 35, 'cyan' => 36, 'white' => 37);
    static $background = array('black' => 40, 'red' => 41, 'green' => 42, 'yellow' => 43, 'blue' => 44, 'magenta' => 45, 'cyan' => 46, 'white' => 47);

    !is_array($parameters) && isset(self::$styles[$parameters]) and $parameters = self::$styles[$parameters];

    $codes = array();
    isset($parameters['fg']) and $codes[] = $foreground[$parameters['fg']];
    isset($parameters['bg']) and $codes[] = $background[$parameters['bg']];
    foreach ($options as $option => $value)
    {
      isset($parameters[$option]) && $parameters[$option] and $codes[] = $value;
    }

    return "\033[".implode(';', $codes).'m'.$text."\033[0m";
  }
}

lime_colorizer::style('ERROR', array('bg' => 'red', 'fg' => 'white', 'bold' => true));
lime_colorizer::style('INFO',  array('fg' => 'green', 'bold' => true));
lime_colorizer::style('PARAMETER', array('fg' => 'cyan'));
lime_colorizer::style('COMMENT',  array('fg' => 'yellow'));

class lime_harness extends lime_registration
{
  public $php_cli = '';
  public $failed = array();
  public $passed = array();
  public $stats = array();
  public $output = null;

  function __construct($output_instance, $php_cli = null)
  {
    $this->php_cli = null === $php_cli ? PHP_BINDIR.DIRECTORY_SEPARATOR.'php' : $php_cli;
    if (!is_executable($this->php_cli))
    {
      throw new Exception(sprintf("Unable to find PHP (%s).", $this->php_cli));
    }

    $this->output = $output_instance ? $output_instance : new lime_output();
  }

  function run()
  {
    if (!count($this->files))
    {
      throw new Exception('You must register some test files before running them!');
    }

    $this->stats =array(
      'failed_files' => array(),
      'failed_tests' => 0,
      'nb_tests' => 0,
    );

    foreach ($this->files as $file)
    {
      $this->failed[$file] = array();
      $this->passed[$file] = array();
      $this->current_file = $file;
      $this->current_test = 0;

      ob_start(array($this, 'process_test_output'), 2);
      passthru(sprintf('%s %s 2>&1', $this->php_cli, $file), $return);
      ob_end_clean();

      if ($return > 0)
      {
        $this->stats[$file]['status'] = 'dubious';
        $this->stats[$file]['status_code'] = $return;
      }
      else
      {
        $this->stats[$file]['status_code'] = 0;
        $this->stats[$file]['status'] = $this->failed[$file] ? 'not ok' : 'ok';
      }

      $relative_file = $this->get_relative_file($file);
      $this->output->echoln(sprintf('%s%s%s', substr($relative_file, -37), str_repeat('.', 40 - min(37, strlen($relative_file))), $this->stats[$file]['status']));

      if ($nb = count($this->failed[$file]) || $return > 0)
      {
        if (count($this->failed[$file]))
        {
          $this->output->echoln(sprintf("    Failed tests: %s", implode(', ', $this->failed[$file])));
        }
        $this->stats['failed_files'][] = $file;
        $this->stats['failed_tests'] += $nb;
      }
    }

    if (count($this->stats['failed_files']))
    {
      $format = "%-30s  %4s  %5s  %5s  %s";
      $this->output->echoln(sprintf($format, 'Failed Test', 'Stat', 'Total', 'Fail', 'List of Failed'));
      $this->output->echoln("------------------------------------------------------------------");
      foreach ($this->failed as $file => $tests)
      {
        if (!in_array($file, $this->stats['failed_files'])) continue;

        $this->output->echoln(sprintf($format, substr($this->get_relative_file($file), -30), $this->stats[$file]['status_code'], count($this->failed[$file]) + count($this->passed[$file]), count($this->failed[$file]), implode(' ', $tests)));
      }

      $this->output->echoln(sprintf('Failed %d/%d test scripts, %.2f%% okay. %d/%d subtests failed, %.2f%% okay.',
        $nb_failed_files = count($this->stats['failed_files']),
        $nb_files = count($this->files),
        $nb_failed_files * 100 / $nb_files,
        $nb_failed_tests = $this->stats['failed_tests'],
        $nb_tests = $this->stats['nb_tests'],
        $nb_tests > 0 ? ($nb_tests - $nb_failed_tests) * 100 / $nb_tests : 0
      ), 'ERROR');
    }
    else
    {
      $this->output->echoln('All tests successful.', 'INFO');
      $this->output->echoln(sprintf('Files=%d, Tests=%d', count($this->files), $this->stats['nb_tests']), 'INFO');
    }
  }

  private function process_test_output($lines)
  {
    foreach (explode("\n", $lines) as $text)
    {
      if (0 === strpos($text, 'not ok '))
      {
        ++$this->current_test;
        $test_number = (int) substr($text, 7);
        $this->failed[$this->current_file][] = $test_number;

        ++$this->stats['nb_tests'];
      }
      else if (0 === strpos($text, 'ok '))
      {
        ++$this->stats['nb_tests'];
      }
    }

    return;
  }
}

class lime_coverage extends lime_registration
{
  public $files = array();
  public $extension = '.php';
  public $base_dir = '';
  public $harness = null;
  public $verbose = false;

  function __construct($harness)
  {
    $this->harness = $harness;
  }

  function run()
  {
    if (!function_exists('xdebug_start_code_coverage'))
    {
      throw new Exception('You must install and enable xdebug before using lime coverage.');
    }

    if (!count($this->harness->files))
    {
      throw new Exception('You must register some test files before running coverage!');
    }

    if (!count($this->files))
    {
      throw new Exception('You must register some files to cover!');
    }

    $coverage = array();
    $tmp_file = '/tmp/test.php';
    foreach ($this->harness->files as $file)
    {
      $tmp = <<<EOF
<?php
xdebug_start_code_coverage();
ob_start();
include('$file');
ob_end_clean();
echo serialize(xdebug_get_code_coverage());
EOF;
      file_put_contents($tmp_file, $tmp);
      ob_start();
      passthru(sprintf('%s %s 2>&1', $this->harness->php_cli, $tmp_file), $return);
      $retval = ob_get_clean();
      if (0 == $return)
      {
        $cov = unserialize($retval);
        foreach ($cov as $file => $lines)
        {
          if (!isset($coverage[$file]))
          {
            $coverage[$file] = array();
          }

          foreach ($lines as $line => $count)
          {
            if (!isset($coverage[$file][$line]))
            {
              $coverage[$file][$line] = 0;
            }
            $coverage[$file][$line] = $coverage[$file][$line] + $count;
          }
        }
      }
    }
    unlink($tmp_file);

    ksort($coverage);
    $total_php_lines = 0;
    $total_covered_lines = 0;
    foreach ($coverage as $file => $cov)
    {
      if (!file_exists($file) || !in_array($file, $this->files))
      {
        continue;
      }

      list($coverage, $php_lines) = $this->compute(file_get_contents($file), $cov);

      $output = $this->harness->output;
      $percent = count($php_lines) ? count($coverage) * 100 / count($php_lines) : 100;

      $total_php_lines += count($php_lines);
      $total_covered_lines += count($coverage);

      $output->echoln(sprintf("%-30s %3.0f%%", substr($this->get_relative_file($file), -30), $percent), $percent == 100 ? 'INFO' : ($percent > 90 ? 'PARAMETER' : ($percent < 20 ? 'ERROR' : '')));
      if ($this->verbose && $percent != 100)
      {
        $output->comment(sprintf("missing: %s", $this->format_range(array_keys(array_diff_key($php_lines, $cov)))));
      }
    }

    $output->echoln(sprintf("TOTAL COVERAGE: %3.0f%%", $total_covered_lines * 100 / $total_php_lines));
  }

  function compute($content, $cov)
  {
    $tokens = token_get_all($content);
    $php_lines = array();
    $current_line = 1;
    $in_class = false;
    $in_function = false;
    $in_function_declaration = false;
    $open_braces = 0;
    foreach ($tokens as $token)
    {
      if (is_string($token))
      {
        switch ($token)
        {
          case '=':
            if (false === $in_class || (false !== $in_function && !$in_function_declaration))
            {
              $php_lines[$current_line] = true;
            }
            break;
          case '{':
            ++$open_braces;
            $in_function_declaration = false;
            break;
          case ';':
            $in_function_declaration = false;
            break;
          case '}':
            --$open_braces;
            if ($open_braces == $in_class)
            {
              $in_class = false;
            }
            if ($open_braces == $in_function)
            {
              $in_function = false;
            }
            break;
        }

        continue;
      }

      list($id, $text) = $token;

      switch ($id)
      {
        case T_CURLY_OPEN:
        case T_DOLLAR_OPEN_CURLY_BRACES:
          ++$open_braces;
          break;
        case T_WHITESPACE:
        case T_OPEN_TAG:
        case T_CLOSE_TAG:
          $current_line += count(explode("\n", $text)) - 1;
          break;
        case T_COMMENT:
        case T_DOC_COMMENT:
          $current_line += count(explode("\n", $text)) - 1;
          break;
        case T_CLASS:
          $in_class = $open_braces;
          break;
        case T_FUNCTION:
          $in_function = $open_braces;
          $in_function_declaration = true;
          break;
        case T_AND_EQUAL:
        case T_BREAK:
        case T_CASE:
        case T_CATCH:
        case T_CLONE:
        case T_CONCAT_EQUAL:
        case T_CONTINUE:
        case T_DEC:
        case T_DECLARE:
        case T_DEFAULT:
        case T_DIV_EQUAL:
        case T_DO:
        case T_ECHO:
        case T_ELSEIF:
        case T_EMPTY:
        case T_ENDDECLARE:
        case T_ENDFOR:
        case T_ENDFOREACH:
        case T_ENDIF:
        case T_ENDSWITCH:
        case T_ENDWHILE:
        case T_EVAL:
        case T_EXIT:
        case T_FOR:
        case T_FOREACH:
        case T_GLOBAL:
        case T_IF:
        case T_INC:
        case T_INCLUDE:
        case T_INCLUDE_ONCE:
        case T_INSTANCEOF:
        case T_ISSET:
        case T_IS_EQUAL:
        case T_IS_GREATER_OR_EQUAL:
        case T_IS_IDENTICAL:
        case T_IS_NOT_EQUAL:
        case T_IS_NOT_IDENTICAL:
        case T_IS_SMALLER_OR_EQUAL:
        case T_LIST:
        case T_LOGICAL_AND:
        case T_LOGICAL_OR:
        case T_LOGICAL_XOR:
        case T_MINUS_EQUAL:
        case T_MOD_EQUAL:
        case T_MUL_EQUAL:
        case T_NEW:
        case T_OBJECT_OPERATOR:
        case T_OR_EQUAL:
        case T_PLUS_EQUAL:
        case T_PRINT:
        case T_REQUIRE:
        case T_REQUIRE_ONCE:
        case T_RETURN:
        case T_SL:
        case T_SL_EQUAL:
        case T_SR:
        case T_SR_EQUAL:
        case T_SWITCH:
        case T_THROW:
        case T_TRY:
        case T_UNSET:
        case T_UNSET_CAST:
        case T_USE:
        case T_WHILE:
        case T_XOR_EQUAL:
          $php_lines[$current_line] = true;
          break;
        default:
          //print "$current_line: ".token_name($id)."\n";
      }
    }

    // we remove from $cov non php lines
    foreach (array_diff_key($cov, $php_lines) as $line => $tmp)
    {
      unset($cov[$line]);
    }

    return array($cov, $php_lines);
  }

  function format_range($lines)
  {
    sort($lines);
    $formatted = '';
    $first = -1;
    $last = -1;
    foreach ($lines as $line)
    {
      if ($last + 1 != $line)
      {
        if ($first != -1)
        {
          $formatted .= $first == $last ? "$first " : "[$first - $last] ";
        }
        $first = $line;
        $last = $line;
      }
      else
      {
        $last = $line;
      }
    }
    if ($first != -1)
    {
      $formatted .= $first == $last ? "$first " : "[$first - $last] ";
    }

    return $formatted;
  }
}

class lime_registration
{
  public $files = array();
  public $extension = '.php';
  public $base_dir = '';

  function register($files_or_directories)
  {
    foreach ((array) $files_or_directories as $f_or_d)
    {
      if (is_file($f_or_d))
      {
        $this->files[] = realpath($f_or_d);
      }
      elseif (is_dir($f_or_d))
      {
        $this->register_dir($f_or_d);
      }
      else
      {
        throw new Exception(sprintf('The file or directory "%s" does not exist.', $f_or_d));
      }
    }
  }

  function register_glob($glob)
  {
    foreach (glob($glob) as $file)
    {
      $this->files[] = realpath($file);
    }
  }

  function register_dir($directory)
  {
    if (!is_dir($directory))
    {
      throw new Exception(sprintf('The directory "%s" does not exist.', $directory));
    }

    $files = array();

    $current_dir = opendir($directory);
    while ($entry = readdir($current_dir))
    {
      if ($entry == '.' || $entry == '..') continue;

      if (is_dir($entry))
      {
        $this->register_dir($entry);
      }
      elseif (preg_match('#'.$this->extension.'$#', $entry))
      {
        $files[] = realpath($directory.DIRECTORY_SEPARATOR.$entry);
      }
    }

    $this->files = array_merge($this->files, $files);
  }

  protected function get_relative_file($file)
  {
    return str_replace(array(realpath($this->base_dir).'/', $this->extension), '', $file);
  }
}
