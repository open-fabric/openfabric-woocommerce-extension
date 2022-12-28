<?php
if (!defined('ABSPATH') || class_exists('OF_Helpers')) {
  return;
}

class OF_Helpers {

  static function gen_uuid() {
    return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }

  static function apply_decorators($request, $decorators) {
    foreach ($decorators as $decorator) {
      $request = $decorator->apply($request);
    }
    return $request;
  }

  static function log($data, $key = 'debug') {
    error_log(print_r("################### $key #####################", TRUE));
    error_log(print_r($data, TRUE));
    error_log(print_r('########################################################', TRUE));
  }
}
