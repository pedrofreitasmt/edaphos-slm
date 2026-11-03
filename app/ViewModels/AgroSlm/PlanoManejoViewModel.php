<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanoManejoViewModel implements JsonSerializable
{
    /**
     * @param  list<string>  $manejo_bacia_solo
     * @param  list<RotacaoEtapaViewModel>  $arranjo_rotacao_culturas
     */
    public function __construct(
        public array $manejo_bacia_solo,
        public array $arranjo_rotacao_culturas
    ) {}

    /**
     * @param  array{
     *   manejo_bacia_solo?: list<string>,
     *   arranjo_rotacao_culturas?: list<array{etapa?: string, especies?: string, objetivo_agronomico?: string}>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $etapas = array_map(
            fn (array $item): RotacaoEtapaViewModel => RotacaoEtapaViewModel::fromArray($item),
            $data['arranjo_rotacao_culturas'] ?? []
        );

        return new self(
            manejo_bacia_solo: $data['manejo_bacia_solo'] ?? [],
            arranjo_rotacao_culturas: $etapas
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'manejo_bacia_solo' => $this->manejo_bacia_solo,
            'arranjo_rotacao_culturas' => array_map(
                fn (RotacaoEtapaViewModel $item): array => $item->jsonSerialize(),
                $this->arranjo_rotacao_culturas
            ),
        ];
    }
}
