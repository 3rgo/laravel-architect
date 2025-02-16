<?php

namespace LaravelArchitect\ValueObject;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class Preset
{
    public string $name;
    protected ?string $laravelVersion = null;
    protected array $laravelOptions;

    public function __construct(?\stdClass $data = null)
    {
        if ($data) {
            $this->name = $data->name;
            $this->laravelVersion = $data->laravelVersion;
            $this->laravelOptions = $data->laravelOptions;
        }
    }

    /**
     * Make a preset from a JSON string.
     *
     * @param string $json
     * @return self
     */
    public static function makeFromJson(string $json): self
    {
        return new self(json_decode($json, false));
    }

    /**
     * Make a preset from a file.
     *
     * @param SplFileInfo $file
     * @return self
     */
    public static function makeFromFile(SplFileInfo $file): self
    {
        return self::makeFromJson($file->getContents());
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setLaravelOptions(array $options): void
    {
        $this->laravelOptions = $options;
    }

    public function setLaravelVersion(?string $version): void
    {
        $this->laravelVersion = $version;
    }

    public function getPresetData(): array
    {
        return [
            'name'           => $this->name ?? null,
            'laravelVersion' => $this->laravelVersion,
            'laravelOptions' => $this->laravelOptions,
        ];
    }
}
