<?php
namespace Civi\Cxn\Rpc;

if (PHP_SAPI != 'cli') {
  exit("This is a CLI tool");
}

$autoloaders = array(
  implode(DIRECTORY_SEPARATOR, array(dirname(__DIR__), 'vendor', 'autoload.php')),
  implode(DIRECTORY_SEPARATOR, array(dirname(dirname(dirname(dirname(__DIR__)))), 'vendor', 'autoload.php')),
);
foreach ($autoloaders as $autoloader) {
  if (file_exists($autoloader)) {
    $loader = require $autoloader;
    break;
  }
}

if (!isset($loader)) {
  die("Failed to find autoloader");
}

global $argv;
main($argv);

// ------------------------------------------------------------------

/**
 * @param $argv
 * @return mixed
 */
function main($argv) {
  $prog = basename(array_shift($argv));

  $mode = $ca = $me = $remote = NULL;
  if (!empty($argv[0])) {
    $mode = $argv[0];
    array_shift($argv);
  }
  if (!empty($argv[0])) {
    $ca = CaIdentity::loadFiles($argv[0]);
    array_shift($argv);
  }
  if (!empty($argv[0])) {
    $me = AgentIdentity::loadFiles($argv[0]);
    array_shift($argv);
  }
  if (!empty($argv[0])) {
    $remote = AgentIdentity::loadFiles($argv[0]);
    array_shift($argv);
  }

  if (empty($ca) || empty($me) || empty($remote)) {
    echo "usage: php $prog [app|site] <ca-prefix> <site-prefix> <app-prefix> [key=value...]\n";
    echo "usage: echo '{...json...}' | php $prog [app|site] <ca-prefix> <site-prefix> <app-prefix>\n";
    exit(1);
  }

  if (empty($argv)) {
    $request = json_decode(file_get_contents('php://stdin'), TRUE);
  }
  else {
    $request = array();
    foreach ($argv as $eq) {
      list ($k, $v) = explode('=', $eq, 2);
      $request[$k] = $v;
    }
  }

  switch ($mode) {
    case 'app':
      $client = new AppClient($ca, $me, $remote);
      break;

    case 'site':
      $client = new SiteClient($ca, $me, $remote);
      break;

    default:
      user_error("unrecognized mode\n");
      break;
  }

  echo "----- REQUEST -----\n";
  echo "URL: " . $remote->getCallbackUrl() . "\n";
  echo "ID: " . $remote->getAgentId() . "\n";
  print_r($request);

  echo "----- RESPONSE -----\n";

  $response = $client->sendRequest($request);
  print_r($response);
  echo "\n";
}
