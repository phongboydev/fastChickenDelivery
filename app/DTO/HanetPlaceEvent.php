<?php

namespace App\DTO;

class HanetPlaceEvent extends HanetEvent {

  public $place_id;
  public $place_name;


  public function __construct($args)
  {

    HanetEvent::__construct($args);

    $this->place_id = $args['placeID'];
    $this->place_name = $args['placeName'];
  }

}