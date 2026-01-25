<?php
namespace BikeClub;

class Terrain extends Pod {
    protected $pod_name = 'terrain';

    public function get_post_title($display = false) { return $this->get_field('post_title', $display); }
}
