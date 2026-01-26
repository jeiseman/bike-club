<?php
class BikeClubStartPoint extends BikeClubPod {
    protected $pod_name = 'start_point';

    public function get_start_county($display = false) {
        return $this->get('start-county', $display);
    }
    public function set_start_county($value) {
        return $this->set('start-county', $value);
    }
}
