<?php
class BikeClubTour extends BikeClubPod {
    protected $pod_name = 'tour';

    public function get_tour_terrain($display = false) {
        return $this->get('tour-terrain', $display);
    }
    public function set_tour_terrain($value) {
        return $this->set('tour-terrain', $value);
    }
}
