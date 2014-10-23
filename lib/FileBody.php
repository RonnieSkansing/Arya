<?php

namespace Arya;

class FileBody implements Body {

    private $path;
    private $options = [];

    /**
     * @param string $path
     * @throws \RuntimeException
     */
    public function __construct($path) {
        if (!is_string($path)) {
            throw new \RuntimeException(
                sprintf('FileBody path must be a string filesystem path; %s specified', gettype($path))
            );
        } elseif (!is_readable($path)) {
            throw new \RuntimeException(
                sprintf('FileBody path is not readable: %s', $path)
            );
        } elseif (!is_file($path)) {
            throw new \RuntimeException(
                sprintf('FileBody path is not a file: %s', $path)
            );
        } else {
            $this->path = $path;
        }
    }

    /**
     * @return void
     */
    public function __invoke() {
        $this->send();
    }

    /**
     * @return void
     */
    public function send() {
        if (@readfile($this->path) === FALSE) {
            $lastError = error_get_last();
            extract($lastError);
            throw new \RuntimeException(
                sprintf("%s in %s on line %d", $message, $file, $line)
            );
        }
    }

    /**
     * @TODO Parse content-type from file extension
     * @TODO Add caching headers
     */
    public function getHeaders() {
        return [
            'Content-Length' => filesize($this->path)
        ];
    }

    /**
     * @TODO Add ETag options
     */
    public function setOptions() {}

    /**
    * @return string Returns the path
    */
    public function getPath() {
        return $this->path;
    }

}
