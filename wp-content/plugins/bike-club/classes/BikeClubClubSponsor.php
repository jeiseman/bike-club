<?php
class BikeClubClubSponsor extends BikeClubPod {
    protected $pod_name = 'club-sponsor';

    public function get_sponsor_town($display = false) {
        return $this->get('sponsor-town', $display);
    }
}
