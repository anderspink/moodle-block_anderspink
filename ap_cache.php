<?php

class ap_cache
{
    /**
     * @var cache_application|cache_session|cache_store
     */
    protected $cache;

    /**
     * @var bool
     */
    protected $use_cache;

    /**
     * @var
     */
    protected $cachefolder;

    /**
     * ap_cache constructor.
     * @throws dml_exception
     */
    public function __construct()
    {
        $this->use_cache = intval(get_config('', 'version')) >= 2012120301;
        $this->cache = null;
        $this->cachefolder = 'block_anderspink';

        if ($this->use_cache) {
            $this->cache = cache::make('block_anderspink', 'apdata');
        } else {
            make_temp_directory($this->cachefolder);
        }
    }

    /**
     * @param $key
     * @return false|mixed|string|null
     * @throws coding_exception
     */
    public function get($key)
    {
        if ($this->use_cache) {
            $value = $this->cache->get($key);
        } else {
            $value = @file_get_contents($this->path($key));
        }

        return $value === false ? null : $value;
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        if ($this->use_cache) {
            $this->cache->set($key, $value);
        } else {
            file_put_contents($this->path($key), $value);
        }
    }

    /**
     * @param $key
     * @return string
     */
    private function path($key)
    {
        global $CFG;
        return $CFG->tempdir . '/' . $this->cachefolder . '/' . $key;
    }
}
