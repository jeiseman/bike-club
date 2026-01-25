<?php
namespace BikeClub;

abstract class Pod {
    protected $pod;
    protected $pod_name;

    public function __construct($id = null) {
        if (!empty($this->pod_name)) {
            $this->pod = pods($this->pod_name, $id);
        }
    }

    /**
     * Get a field value.
     *
     * @param string $name Field name.
     * @param boolean $display Whether to return the display value (formatted) or raw value.
     * @return mixed
     */
    protected function get_field($name, $display = false) {
        if ($display) {
            return $this->pod->display($name);
        }
        return $this->pod->field($name);
    }

    /**
     * Set a field value.
     *
     * @param string $name Field name.
     * @param mixed $value Value to set.
     * @return int|false The item ID or false on failure.
     */
    protected function set_field($name, $value) {
        return $this->pod->save($name, $value);
    }

    /**
     * Save the pod item.
     *
     * @param string|array $data Field name or data array.
     * @param mixed $value Value if $data is a string.
     * @return int|false
     */
    public function save($data = null, $value = null) {
        return $this->pod->save($data, $value);
    }

    public function add($data = []) {
        return $this->pod->add($data);
    }

    public function add_to($field, $value) {
        return $this->pod->add_to($field, $value);
    }

    public function remove_from($field, $value) {
        return $this->pod->remove_from($field, $value);
    }

    public function id() {
        return $this->pod->id();
    }

    public function total() {
        return $this->pod->total();
    }

    public function fetch() {
        return $this->pod->fetch();
    }

    public function find($params) {
        return $this->pod->find($params);
    }

    public function exists() {
        return $this->pod->exists();
    }

    public function form($fields = null, $label = null, $thank_you = null) {
        return $this->pod->form($fields, $label, $thank_you);
    }

    // Proxy other methods to pod object
    public function __call($name, $arguments) {
        if (method_exists($this->pod, $name)) {
            return call_user_func_array([$this->pod, $name], $arguments);
        }
        throw new \Exception("Method $name does not exist on Pod object.");
    }
}
