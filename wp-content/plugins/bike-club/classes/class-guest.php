<?php
namespace BikeClub;

class Guest extends Pod {
    protected $pod_name = 'guest';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_guests_name($display = false) { return $this->get_field('guests_name', $display); }
    public function get_email($display = false) { return $this->get_field('email', $display); }
    public function get_car_license_plate($display = false) { return $this->get_field('car_license_plate', $display); }
    public function get_emergency_number($display = false) { return $this->get_field('emergency_number', $display); }
    public function get_cell_phone($display = false) { return $this->get_field('cell_phone', $display); }
    public function get_ride_id($display = false) { return $this->get_field('ride_id', $display); }
}
