<?php
namespace BikeClub;

class LocationDateBlock extends Pod {
    protected $pod_name = 'locationdateblock';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_post_name($display = false) { return $this->get_field('post_name', $display); }
    public function get_start_date($display = false) { return $this->get_field('start_date', $display); }
    public function get_end_date($display = false) { return $this->get_field('end_date', $display); }
    public function get_days_of_week($display = false) { return $this->get_field('days_of_week', $display); }
    public function get_start_location($display = false) { return $this->get_field('start_location', $display); }
    public function get_post_content($display = false) { return $this->get_field('post_content', $display); }
}
