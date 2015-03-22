<?php
namespace Civi\Cxn\Rpc;

/**
 * Class Examples
 *
 * This is a collection of example identity files. Generating new identities (keys+certs)
 * can be a drag on the testing-time, so we cache across test runs.
 *
 * @package Civi\Cxn\Rpc
 */
class Examples {
  /**
   * @var bool
   */
  protected static $init = FALSE;

  /**
   * @var CaIdentity
   */
  public static $ca;

  /**
   * @var SiteIdentity
   */
  public static $siteA;

  /**
   * @var SiteIdentity
   */
  public static $siteB;

  /**
   * @var SiteIdentity
   */
  public static $siteBadUrl;

  /**
   * @var SiteIdentity
   */
  public static $siteBadId;

  /**
   * @var SiteIdentity
   */
  public static $siteBadOld;

  /**
   * @var AppIdentity
   */
  public static $appA;

  /**
   * @var AppIdentity
   */
  public static $appB;

  /**
   * @var CaIdentity
   */
  public static $malloryCa;

  /**
   * @var SiteIdentity
   */
  public static $mallorySiteA;

  /**
   * @var AppIdentity
   */
  public static $malloryAppA;

  public static function init() {
    if (self::$init) return;

    self::$ca = self::cachedCreate('ca', 'CaIdentity', "/O=test");
    self::$siteA = self::cachedCreate('siteA', 'SiteIdentity', self::$ca, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    self::$siteB = self::cachedCreate('siteB', 'SiteIdentity', self::$ca, 'siteB-fdsa-fdsa-fdsa', 'http://site-b.org/callback');
    self::$siteBadUrl = self::cachedCreate('siteBadUrl', 'SiteIdentity', self::$ca, 'siteC-fdsa-fdsa-fdsa', 'htt://site-bad-url.org/callback');
    self::$siteBadId = self::cachedCreate('siteBadId', 'SiteIdentity', self::$ca, 'me', 'http://site-bad-id.org/callback');

    Time::setTime('-2 year');
    self::$siteBadOld = self::cachedCreate('siteBadOld', 'SiteIdentity', self::$ca, 'site-old-jkl-jkl-jkl', 'http://site-bad-old.org/callback');
    Time::resetTime();

    self::$appA = self::cachedCreate('appA', 'AppIdentity', self::$ca, 'appA-1234-1234-1234', 'http://app-a.com/callback');
    self::$appB = self::cachedCreate('appB', 'AppIdentity', self::$ca, 'appB-5678-5678-5678', 'http://app-b.com/callback');

    self::$malloryCa = self::cachedCreate('malloryCa', 'CaIdentity', "/O=test");
    self::$mallorySiteA = self::cachedCreate('mallorySiteA', 'SiteIdentity', self::$malloryCa, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    self::$malloryAppA = self::cachedCreate('malloryAppA', 'AppIdentity', self::$malloryCa, 'appA-1234-1234-1234', 'http://app-a.com/callback');
  }

  protected static function cachedCreate($name, $class) {
    $fullClass = 'Civi\Cxn\Rpc\\' . $class;
    $dir = dirname(__DIR__) . '/tmp';
    $file = "$dir/$name.json";
    if (file_exists($file)) {
      $identity = new $fullClass();
      $identity->fromArray(json_decode(file_get_contents($file), TRUE));
    }
    else {
      $args = func_get_args();
      array_shift($args);
      array_shift($args);
      $identity = call_user_func_array(array($fullClass, 'create'), $args);
      if (!is_dir($dir)) {
        mkdir($dir);
      }
      file_put_contents($file, json_encode($identity->toArray()));
    }

    return $identity;
  }

}
