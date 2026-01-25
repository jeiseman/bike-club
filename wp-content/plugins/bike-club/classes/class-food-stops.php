<?php
namespace BikeClub;

class FoodStops extends Pod {
    protected $pod_name = 'food_stops';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_map($display = false) { return $this->get_field('map', $display); }
    public function get_post_name($display = false) { return $this->get_field('post_name', $display); }
    public function get_town($display = false) { return $this->get_field('town', $display); }
    public function get_open($display = false) { return $this->get_field('open', $display); }
    public function get_hours($display = false) { return $this->get_field('hours', $display); }
    public function get_notes($display = false) { return $this->get_field('notes', $display); }
    public function get_phone($display = false) { return $this->get_field('phone', $display); }
    public function get_indoor_seating($display = false) { return $this->get_field('indoor_seating', $display); }
    public function get_address($display = false) { return $this->get_field('address', $display); }
}
