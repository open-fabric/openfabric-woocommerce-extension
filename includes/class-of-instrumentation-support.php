<?php
if (!defined('ABSPATH') || class_exists('OF_Instrumentation_Support')) {
  return;
}

class OF_Instrumentation_Support {
  private $source = 'sc_woocommerce';
  private $version;
  private $value;

  public function __construct($version) {
    $this->version = $version;
    $this->value = "src={$this->source}|ver={$this->version}";
  }

  public function apply($request) {
    $request['headers'] = array_merge($request['headers'], array(
      'X-Instrumentation' => $this->value
    ));
    return $request;
  }
}
