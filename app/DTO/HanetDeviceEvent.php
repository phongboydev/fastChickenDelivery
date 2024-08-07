<?php

namespace App\DTO;

class HanetDeviceEvent extends HanetEvent {

  public $device_id;
  public $device_name;
  public $place_id;
  public $place_name;


  public function __construct($args)
  {

    HanetEvent::__construct($args);

    $this->device_id = $args['deviceID'];
    $this->device_name = $args['deviceName'];
    $this->place_id = $args['placeID'];
    $this->place_name = $args['placeName'];
  }

}