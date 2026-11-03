<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LaudoAgronomicoViewModel implements JsonSerializable
{
    /**
     * @param  list<string>  $referencias_utilizadas
     */
    public function __construct(
        public string $resumo_executivo,
        public DiagnosticoFisicoQuimicoViewModel $diagnostico_fisico_quimico,
        public PlanoManejoViewModel $plano_manejo_conservacionista,
        public string $relacao_condicionantes_ambientais,
        public array $referencias_utilizadas
    ) {}

    /**
     * @param  array{
     *   resumo_executivo?: string,
     *   diagnostico_fisico_quimico?: array{analise_ph_nutrientes?: string, status_materia_organica?: string, potencial_fbn?: string},
     *   plano_manejo_conservacionista?: array{manejo_bacia_solo?: list<string>, arranjo_rotacao_culturas?: list<array{etapa?: string, especies?: string, objetivo_agronomico?: string}>},
     *   relacao_condicionantes_ambientais?: string,
     *   referencias_utilizadas?: list<string>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            resumo_executivo: (string) ($data['resumo_executivo'] ?? ''),
            diagnostico_fisico_quimico: DiagnosticoFisicoQuimicoViewModel::fromArray(
                $data['diagnostico_fisico_quimico'] ?? []
            ),
            plano_manejo_conservacionista: PlanoManejoViewModel::fromArray(
                $data['plano_manejo_conservacionista'] ?? []
            ),
            relacao_condicionantes_ambientais: (string) ($data['relacao_condicionantes_ambientais'] ?? ''),
            referencias_utilizadas: $data['referencias_utilizadas'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'resumo_executivo' => $this->resumo_executivo,
            'diagnostico_fisico_quimico' => $this->diagnostico_fisico_quimico->jsonSerialize(),
            'plano_manejo_conservacionista' => $this->plano_manejo_conservacionista->jsonSerialize(),
            'relacao_condicionantes_ambientais' => $this->relacao_condicionantes_ambientais,
            'referencias_utilizadas' => $this->referencias_utilizadas,
        ];
    }
}
