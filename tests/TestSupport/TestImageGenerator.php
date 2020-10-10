<?php

namespace Spatie\MediaLibrary\Tests\TestSupport;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGenerator;

class TestImageGenerator extends ImageGenerator
{
    /**
     * @var \Illuminate\Support\Collection
     */
    public $supportedMimetypes;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $supportedExtensions;

    /**
     * @var bool
     */
    public $shouldMatchBothExtensionsAndMimeTypes = false;

    public function __construct()
    {
        $this->supportedExtensions = new Collection();

        $this->supportedMimetypes = new Collection();
    }

    public function supportedExtensions(): Collection
    {
        return $this->supportedExtensions;
    }

    public function supportedMimeTypes(): Collection
    {
        return $this->supportedMimetypes;
    }

    public function shouldMatchBothExtensionsAndMimeTypes(): bool
    {
        return $this->shouldMatchBothExtensionsAndMimeTypes;
    }

    public function convert(string $path, Conversion $conversion = null): string
    {
        return $path;
    }

    public function requirementsAreInstalled(): bool
    {
        return true;
    }
}
