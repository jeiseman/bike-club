<?php
namespace BikeClub;

class County extends Pod {
    protected $pod_name = 'county';

    public function get_weather($display = false) { return $this->get_field('weather', $display); }
}
