<?php

namespace Spatie\MediaLibrary\MediaCollections\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\RegisteredResponsiveImages;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class CleanCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'media-library:clean {modelType?} {collectionName?} {disk?}
    {--dry-run : List files that will be removed without removing them},
    {--force : Force the operation to run when in production},
    {--rate-limit= : Limit the number of requests per second }';

    protected $description = 'Clean deprecated conversions and files without related model.';

    /**
     * @var \Spatie\MediaLibrary\MediaCollections\MediaRepository
     */
    protected $mediaRepository;

    /**
     * @var \Spatie\MediaLibrary\Conversions\FileManipulator
     */
    protected $fileManipulator;

    /**
     * @var \Illuminate\Contracts\Filesystem\Factory
     */
    protected $fileSystem;

    /**
     * @var \Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator
     */
    protected $basePathGenerator;

    /**
     * @var bool
     */
    protected $isDryRun = false;

    /**
     * @var int
     */
    protected $rateLimit = 0;

    public function handle(
        MediaRepository $mediaRepository,
        FileManipulator $fileManipulator,
        Factory $fileSystem,
        DefaultPathGenerator $basePathGenerator
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->fileManipulator = $fileManipulator;
        $this->fileSystem = $fileSystem;
        $this->basePathGenerator = $basePathGenerator;

        if (! $this->confirmToProceed()) {
            return;
        }

        $this->isDryRun = $this->option('dry-run');
        $this->rateLimit = (int) $this->option('rate-limit');

        $this->deleteFilesGeneratedForDeprecatedConversions();

        $this->deleteOrphanedDirectories();

        $this->info('All done!');
    }

    public function getMediaItems(): Collection
    {
        $modelType = $this->argument('modelType');
        $collectionName = $this->argument('collectionName');

        if (! is_null($modelType) && ! is_null($collectionName)) {
            return $this->mediaRepository->getByModelTypeAndCollectionName(
                $modelType,
                $collectionName
            );
        }

        if (! is_null($modelType)) {
            return $this->mediaRepository->getByModelType($modelType);
        }

        if (! is_null($collectionName)) {
            return $this->mediaRepository->getByCollectionName($collectionName);
        }

        return $this->mediaRepository->all();
    }

    protected function deleteFilesGeneratedForDeprecatedConversions(): void
    {
        $this->getMediaItems()->each(function (Media $media) {
            $this->deleteConversionFilesForDeprecatedConversions($media);

            if ($media->responsive_images) {
                $this->deleteResponsiveImagesForDeprecatedConversions($media);
            }

            if ($this->rateLimit) {
                usleep((1 / $this->rateLimit) * 1000000 * 2);
            }
        });
    }

    protected function deleteConversionFilesForDeprecatedConversions(Media $media): void
    {
        $conversionFilePaths = ConversionCollection::createForMedia($media)->getConversionsFiles($media->collection_name);

        $conversionPath = $this->basePathGenerator->getPathForConversions($media);
        $currentFilePaths = $this->fileSystem->disk($media->disk)->files($conversionPath);

        collect($currentFilePaths)
            ->reject(function (string $currentFilePath) use ($conversionFilePaths) {
                return $conversionFilePaths->contains(basename($currentFilePath));
            })
            ->each(function (string $currentFilePath) use ($media) {
                if (! $this->isDryRun) {
                    $this->fileSystem->disk($media->disk)->delete($currentFilePath);

                    $this->markConversionAsRemoved($media, $currentFilePath);
                }

                $this->info("Deprecated conversion file `{$currentFilePath}` ".($this->isDryRun ? 'found' : 'has been removed'));
            });
    }

    protected function deleteResponsiveImagesForDeprecatedConversions(Media $media): void
    {
        $conversionNames = ConversionCollection::createForMedia($media)
            ->map(function (Conversion $conversion) {
                return $conversion->getName();
            })
            ->push('media_library_original');

        $responsiveImagesGeneratedFor = array_keys($media->responsive_images);

        collect($responsiveImagesGeneratedFor)
            ->map(function (string $generatedFor) use ($media) {
                return $media->responsiveImages($generatedFor);
            })
            ->reject(function (RegisteredResponsiveImages $responsiveImages) use ($conversionNames) {
                return $conversionNames->contains($responsiveImages->generatedFor);
            })
            ->each(function (RegisteredResponsiveImages $responsiveImages) {
                if (! $this->isDryRun) {
                    $responsiveImages->delete();
                }
            });
    }

    protected function deleteOrphanedDirectories(): void
    {
        $diskName = $this->argument('disk') ?: config('media-library.disk_name');

        if (is_null(config("filesystems.disks.{$diskName}"))) {
            throw FileCannotBeAdded::diskDoesNotExist($diskName);
        }
        $mediaClass = config('media-library.media_model');
        $mediaInstance = new $mediaClass();
        $keyName = $mediaInstance->getKeyName();

        $mediaIds = collect($this->mediaRepository->all()->pluck($keyName)->toArray());

        collect($this->fileSystem->disk($diskName)->directories())
            ->filter(function (string $directory) {
                return is_numeric($directory);
            })
            ->reject(function (string $directory) use ($mediaIds) {
                return $mediaIds->contains((int) $directory);
            })->each(function (string $directory) use ($diskName) {
                if (! $this->isDryRun) {
                    $this->fileSystem->disk($diskName)->deleteDirectory($directory);
                }

                if ($this->rateLimit) {
                    usleep((1 / $this->rateLimit) * 1000000);
                }

                $this->info("Orphaned media directory `{$directory}` ".($this->isDryRun ? 'found' : 'has been removed'));
            });
    }

    protected function markConversionAsRemoved(Media $media, string $conversionPath): void
    {
        $conversionFile = pathinfo($conversionPath, PATHINFO_FILENAME);

        $generatedConversionName = null;

        $media->getGeneratedConversions()
            ->filter(function (bool $isGenerated, string $generatedConversionName) use ($conversionFile) {
                return Str::contains($conversionFile, $generatedConversionName);
            })
            ->each(function (bool $isGenerated, string $generatedConversionName) use ($media) {
                $media->markAsConversionGenerated($generatedConversionName, false);
            });

        $media->save();
    }
}
