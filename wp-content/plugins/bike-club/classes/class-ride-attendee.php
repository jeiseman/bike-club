<?php
namespace BikeClub;

class RideAttendee extends Pod {
    protected $pod_name = 'ride-attendee';

    public function get_ride_attendee_status($display = false) { return $this->get_field('ride_attendee_status', $display); }
    public function set_ride_attendee_status($value) { return $this->set_field('ride_attendee_status', $value); }

    public function get_wait_list_number($display = false) { return $this->get_field('wait_list_number', $display); }
    public function set_wait_list_number($value) { return $this->set_field('wait_list_number', $value); }

    public function get_rider($display = false) { return $this->get_field('rider', $display); }
    public function add_to_rider($value) { return $this->add_to('rider', $value); }

    public function get_car_license($display = false) { return $this->get_field('car_license', $display); }
    public function set_car_license($value) { return $this->set_field('car_license', $value); }

    public function get_emergency_phone($display = false) { return $this->get_field('emergency_phone', $display); }
    public function set_emergency_phone($value) { return $this->set_field('emergency_phone', $value); }

    public function get_cell_phone($display = false) { return $this->get_field('cell_phone', $display); }
    public function set_cell_phone($value) { return $this->set_field('cell_phone', $value); }

    public function get_ride($display = false) { return $this->get_field('ride', $display); }

    public function get_title($display = false) { return $this->get_field('title', $display); }
}
