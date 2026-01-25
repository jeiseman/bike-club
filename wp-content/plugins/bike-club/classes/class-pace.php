<?php
namespace BikeClub;

class Pace extends Pod {
    protected $pod_name = 'pace';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
    public function get_index($display = false) { return $this->get_field('index', $display); }
    public function get_minspeed($display = false) { return $this->get_field('minspeed', $display); }
}
