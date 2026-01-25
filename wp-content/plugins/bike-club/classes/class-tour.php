<?php
namespace BikeClub;

class Tour extends Pod {
    protected $pod_name = 'tour';

    public function get_tour_number($display = false) { return $this->get_field('tour_number', $display); }
    public function set_tour_number($value) { return $this->set_field('tour_number', $value); }

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }

    public function get_start_point($display = false) { return $this->get_field('start_point', $display); }

    public function get_miles($display = false) { return $this->get_field('miles', $display); }

    public function get_climb($display = false) { return $this->get_field('climb', $display); }

    public function get_road_closures($display = false) { return $this->get_field('road_closures', $display); }

    public function get_tour_type($display = false) { return $this->get_field('tour_type', $display); }

    public function get_cue_sheet_number($display = false) { return $this->get_field('cue_sheet_number', $display); }
    public function set_cue_sheet_number($value) { return $this->set_field('cue_sheet_number', $value); }

    public function get_tour_map($display = false) { return $this->get_field('tour_map', $display); }
    public function set_tour_map($value) { return $this->set_field('tour_map', $value); }

    public function get_tour_description($display = false) { return $this->get_field('tour_description', $display); }

    public function get_vimeo($display = false) { return $this->get_field('vimeo', $display); }

    public function get_tour_comments($display = false) { return $this->get_field('tour_comments', $display); }

    public function get_tour_terrain($display = false) { return $this->get_field('tour-terrain', $display); }

    public function get_cue_sheet($display = false) { return $this->get_field('cue_sheet', $display); }

    public function get_active($display = false) { return $this->get_field('active', $display); }

    public function get_creator($display = false) { return $this->get_field('creator', $display); }
}
