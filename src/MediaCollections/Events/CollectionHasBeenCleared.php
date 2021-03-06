<?php

namespace Spatie\MediaLibrary\MediaCollections\Events;

use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\HasMedia;

class CollectionHasBeenCleared
{
    use SerializesModels;

    /**
     * @var \Spatie\MediaLibrary\HasMedia
     */
    public $model;

    /**
     * @var string
     */
    public $collectionName;

    public function __construct(HasMedia $model, string $collectionName)
    {
        $this->model = $model;

        $this->collectionName = $collectionName;
    }
}
