<?php

namespace App\DTO;

class HanetCheckinEvent extends HanetEvent
{

    public $device_id;
    public $device_name;
    public $place_id;
    public $place_name;
    public $date;
    public $alias_id;
    public $detected_image_url;
    public $person_id;
    public $person_name;
    public $person_type;
    public $hash;
    public $timesheet_hanet_tmp_id;


    public function __construct($args)
    {
        parent::__construct($args);

        $this->device_id = $args['deviceID'];
        $this->device_name = $args['deviceName'];
        $this->place_id = $args['placeID'];
        $this->place_name = $args['placeName'];
        $this->date = $args['date'];
        $this->alias_id = $args['aliasID'];
        $this->detected_image_url = $args['detected_image_url'];
        $this->person_id = $args['personID'];
        $this->person_name = $args['personName'];
        $this->person_type = $args['personType'];
        $this->hash = $args['hash'];
        $this->timesheet_hanet_tmp_id = (!empty($args['timesheet_hanet_tmp_id'])) ? $args['timesheet_hanet_tmp_id'] : '';
    }

}
