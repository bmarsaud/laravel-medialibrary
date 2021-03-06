<?php

namespace Spatie\MediaLibrary\Conversions\ImageGenerators;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImageGeneratorFactory
{
    public static function getImageGenerators(): Collection
    {
        return collect(config('media-library.image_generators'))
            ->map(function (string $imageGeneratorClassName) {
                return app($imageGeneratorClassName);
            });
    }

    public static function forExtension(?string $extension): ?ImageGenerator
    {
        return static::getImageGenerators()
            ->first(function (ImageGenerator $imageGenerator) use ($extension) {
                return $imageGenerator->canHandleExtension(strtolower($extension));
            });
    }

    public static function forMimeType(?string $mimeType): ?ImageGenerator
    {
        return static::getImageGenerators()
            ->first(function (ImageGenerator $imageGenerator) use ($mimeType) {
                return $imageGenerator->canHandleMime($mimeType);
            });
    }

    public static function forMedia(Media $media): ?ImageGenerator
    {
        return static::getImageGenerators()
            ->first(function (ImageGenerator $imageGenerator) use ($media) {
                return $imageGenerator->canConvert($media);
            });
    }
}
