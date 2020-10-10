<?php

namespace Spatie\MediaLibrary\Conversions\Events;

use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ConversionWillStart
{
    use SerializesModels;

    /**
     * @var \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    public $media;

    /**
     * @var \Spatie\MediaLibrary\Conversions\Conversion
     */
    public $conversion;

    /**
     * @var string
     */
    public $copiedOriginalFile;

    public function __construct(Media $media, Conversion $conversion, string $copiedOriginalFile)
    {
        $this->media = $media;

        $this->conversion = $conversion;

        $this->copiedOriginalFile = $copiedOriginalFile;
    }
}
