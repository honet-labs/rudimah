<?php

namespace App\Core\Applications;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class AppManifest implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $enabled,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
