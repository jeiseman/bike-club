<?php
namespace BikeClub;

class StartPoint extends Pod {
    protected $pod_name = 'start_point';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_post_name($display = false) { return $this->get_field('post_name', $display); }

    public function get_start_county($display = false) { return $this->get_field('start-county', $display); }
    public function get_state($display = false) { return $this->get_field('state', $display); }
    public function get_longitude($display = false) { return $this->get_field('longitude', $display); }
    public function get_latitude($display = false) { return $this->get_field('latitude', $display); }
    public function get_directions($display = false) { return $this->get_field('directions', $display); }
    public function get_active($display = false) { return $this->get_field('active', $display); }
}
