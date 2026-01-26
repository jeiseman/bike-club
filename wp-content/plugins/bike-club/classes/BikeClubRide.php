<?php
class BikeClubRide extends BikeClubPod {
    protected $pod_name = 'ride';

    public function get_ride_status($display = false) {
        return $this->get('ride-status', $display);
    }
    public function set_ride_status($value) {
        return $this->set('ride-status', $value);
    }
}
