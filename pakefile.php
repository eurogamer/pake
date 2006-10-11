<?php

/* registration */
pake_import('simpletest');
pake_import('pear');

pake_desc('create a single file with all pake classes');
pake_task('compact');

pake_desc('release a new pake version');
pake_task('release');

pake_task('foo');

function run_foo($task, $args)
{
  throw new Exception('test');
}

/* tasks */
/**
 * To be able to include a plugin in pake_runtime.php, you have to use include_once for external dependencies
 * and require_once for internal dependencies (for other included PI or pake classes) because we strip 
 * all require_once statements
 */
function run_compact($task, $args)
{
  $plugins = $args;

  // merge all files
  $content = '';
  $files = pakeFinder::type('file')->name('*.class.php')->in(getcwd().'/lib/pake');
  $files[] = getcwd().'/bin/pake.php';
  foreach ($args as $plugin_name)
  {
    $files[] = getcwd().'/lib/pake/tasks/pake'.$plugin_name.'Task.class.php';
  }

  foreach ($files as $file)
  {
    $content .= file_get_contents($file);
  }

  // strip require_once statements
  $content = preg_replace('/^\s*require_once[^;]+;/m', '', $content);

  // replace windows and mac format with unix format
  $content = str_replace(array("\r\n"), "\n", $content);

  // strip php tags
  $content = preg_replace(array("/<\?php/", "/<\?/", "/\?>/"), '', $content);

  // replace multiple new lines with a single newline
  $content = preg_replace(array("/\n\s+\n/s", "/\n+/s"), "\n", $content);

  $content = "<?php\n".trim($content)."\n";

  file_put_contents(getcwd().'/bin/pake_runtime.php', $content);

  // strip all comments
  pake_strip_php_comments(getcwd().'/bin/pake_runtime.php');
}

function run_create_pear_package($task, $args)
{
  if (!isset($args[0]) || !$args[0])
  {
    throw new pakeException('You must provide pake version to release (1.1.X for example).');
  }

  $version = $args[0];

  // create a pear package
  echo 'create pear package for version "'.$version."\"\n";

  pake_copy(getcwd().'/package.xml.tmpl', getcwd().'/package.xml');

  // add class files
  $class_files = pakeFinder::type('file')->prune('.svn')->not_name('/^pakeApp.class.php$/')->name('*.php')->relative()->in('lib');
  $xml_classes = '';
  foreach ($class_files as $file)
  {
    $dir_name  = dirname($file);
    $file_name = basename($file);
    $xml_classes .= '<file role="php" baseinstalldir="'.$dir_name.'" install-as="'.$file_name.'" name="lib/'.$file.'"/>'."\n";
  }

  // replace tokens
  pake_replace_tokens('package.xml', getcwd(), '##', '##', array(
    'PAKE_VERSION' => $version,
    'CURRENT_DATE' => date('Y-m-d'),
    'CLASS_FILES'  => $xml_classes,
  ));
  pake_replace_tokens('lib/pake/pakeApp.class.php', getcwd(), 'const VERSION = \'', '\';', array('1.1.DEV' => "const VERSION = '$version';"));

  pakePearTask::run_pear($task, $args);
  pake_remove('package.xml', getcwd());
  pake_replace_tokens('lib/pake/pakeApp.class.php', getcwd(), 'const VERSION = \'', '\';', array($version => "const VERSION = '1.1.DEV';"));
}

function run_release($task, $args)
{
  if (!isset($args[0]) || !$args[0])
  {
    throw new pakeException('You must provide pake version to release (1.1.X for example).');
  }

  $version = $args[0];

  pakeSimpletestTask::run_test($task, array());

  if ($task->is_verbose()) echo 'releasing pake version "'.$version."\"\n";

  array_unshift($args, $version);

  run_create_pear_package($task, $args);
}