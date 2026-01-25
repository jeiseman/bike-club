<?php
namespace BikeClub;

class RoadClosureWarning extends Pod {
    protected $pod_name = 'road_closureswarning';

    public function get_start_date($display = false) { return $this->get_field('start_date', $display); }
    public function get_end_date($display = false) { return $this->get_field('end_date', $display); }
    public function get_tour($display = false) { return $this->get_field('tour', $display); }
    public function add_to_tour($value) { return $this->add_to('tour', $value); }
    public function get_description($display = false) { return $this->get_field('description', $display); }
    public function get_closure_comments($display = false) { return $this->get_field('closure_comments', $display); }
    public function get_road_closure_location($display = false) { return $this->get_field('road_closure_location', $display); }
    public function get_post_modified($display = false) { return $this->get_field('post_modified', $display); }
}
