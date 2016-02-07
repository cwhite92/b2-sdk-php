<?php

namespace ChrisWhite\B2;

class File
{
    protected $id;
    protected $path;
    protected $hash;
    protected $size;
    protected $type;
    protected $info;

    /**
     * File constructor.
     *
     * @param $id
     * @param $path
     * @param $hash
     * @param $size
     * @param $type
     * @param $info
     */
    public function __construct($id, $path, $hash = null, $size = null, $type = null, $info = null)
    {
        $this->id = $id;
        $this->path = $path;
        $this->hash = $hash;
        $this->size = $size;
        $this->type = $type;
        $this->info = $info;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

}