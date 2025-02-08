<?php

namespace LaravelArchitect\ValueObject;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class Preset
{
    public string $name;

    public function __construct(\stdClass $data)
    {
        $this->name = $data->name;
    }

    public static function makeFromJson(string $json): self
    {
        return new self(json_decode($json, false));
    }

    public static function makeFromFile(SplFileInfo $file): self
    {
        return self::makeFromJson($file->getContents());
    }
}
