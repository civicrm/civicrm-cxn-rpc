<?php
namespace Civi\Cxn\Rpc;

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

    self::$ca = CaIdentity::create("/O=test");
    self::$siteA = SiteIdentity::create(self::$ca, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    self::$siteB = SiteIdentity::create(self::$ca, 'siteB-fdsa-fdsa-fdsa', 'http://site-b.org/callback');
    self::$siteBadUrl = SiteIdentity::create(self::$ca, 'siteC-fdsa-fdsa-fdsa', 'htt://site-bad-url.org/callback');
    self::$siteBadId = SiteIdentity::create(self::$ca, 'me', 'http://site-bad-id.org/callback');

    Time::setTime('-2 year');
    self::$siteBadOld = SiteIdentity::create(self::$ca, 'site-old-jkl-jkl-jkl', 'http://site-bad-old.org/callback');
    Time::resetTime();

    self::$appA = AppIdentity::create(self::$ca, 'appA-1234-1234-1234', 'http://app-a.com/callback');
    self::$appB = AppIdentity::create(self::$ca, 'appB-5678-5678-5678', 'http://app-b.com/callback');

    self::$malloryCa = CaIdentity::create("/O=test");
    self::$mallorySiteA = SiteIdentity::create(self::$malloryCa, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    self::$malloryAppA = AppIdentity::create(self::$malloryCa, 'appA-1234-1234-1234', 'http://app-a.com/callback');

    $end = time();
  }

}
