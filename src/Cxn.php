<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\CxnException;

class Cxn {

  public static function validate($cxn) {
    $errors = self::getValidationMessages($cxn);
    if (!empty($errors)) {
      throw new CxnException("Invalid Cxn: " . implode(', ', array_keys($errors)));
    }
  }

  /**
   * @param array $cxn
   * @return array
   *   List of errors. Empty error if OK.
   */
  public static function getValidationMessages($cxn) {
    $errors = array();

    if (!is_array($cxn)) {
      $errors['appMeta'] = 'Not an array';
    }

    foreach (array('cxnId', 'secret', 'appId') as $key) {
      if (empty($cxn[$key])) {
        $errors[$key] = 'Required field';
      }
    }

    foreach (array('appUrl', 'siteUrl') as $key) {
      if (empty($cxn[$key])) {
        $errors[$key] = 'Required field';
      }
      elseif (!filter_var($cxn[$key], FILTER_VALIDATE_URL)) {
        $errors[$key] = 'Malformed URL';
      }
    }

    if (!isset($cxn['perm']) || !is_array($cxn['perm'])) {
      $errors['perm'] = 'Missing permisisons';
    }

    return $errors;
  }
}
