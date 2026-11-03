<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OllamaHealthViewModel implements JsonSerializable
{
    /**
     * @param  list<string>  $available_models
     */
    public function __construct(
        public bool $online,
        public string $base_url,
        public string $configured_model,
        public bool $model_installed,
        public array $available_models,
        public string $message
    ) {}

    /**
     * @param  array{
     *   online: bool,
     *   base_url: string,
     *   configured_model: string,
     *   model_installed: bool,
     *   available_models: list<string>,
     *   message: string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            online: (bool) $data['online'],
            base_url: (string) $data['base_url'],
            configured_model: (string) $data['configured_model'],
            model_installed: (bool) $data['model_installed'],
            available_models: $data['available_models'],
            message: (string) $data['message']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'online' => $this->online,
            'base_url' => $this->base_url,
            'configured_model' => $this->configured_model,
            'model_installed' => $this->model_installed,
            'available_models' => $this->available_models,
            'message' => $this->message,
        ];
    }
}
