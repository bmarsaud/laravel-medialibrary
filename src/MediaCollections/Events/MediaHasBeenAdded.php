<?php

namespace Spatie\MediaLibrary\MediaCollections\Events;

use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaHasBeenAdded
{
    use SerializesModels;

    /**
     * @var \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    public $media;

    public function __construct(Media $media)
    {
        $this->media = $media;
    }
}
