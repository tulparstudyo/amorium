<?php namespace AmoriumCms\Shop\Classes\Import;

/**
 * RemoteResponse is the status, body and media type of one download.
 */
class RemoteResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public string $mediaType
    ) {
    }

    /**
     * successful
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
