<?php

namespace App\DTO;

class HanetEvent {

  public $action_type;
  public $data_type;
  public $id;
  public $hash;
  public $keycode;
  public $date;
  public $time;

  public function __construct($args)
  {
     $this->action_type = $args['action_type'];
     $this->data_type = $args['data_type'];
     $this->id = $args['id'];
     $this->hash = $args['hash'];
     $this->keycode = $args['keycode'];
     $this->date = $args['date'];
     $this->time = $args['time'];
  }
}