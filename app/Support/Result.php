<?php

namespace App\Support;

/**
 * Implementação do Padrão Result (Result Object / Railway-Oriented Programming)
 *
 * Encapsula o resultado de uma operação de serviço, carregando:
 *   - status de sucesso ou falha
 *   - dados retornados pela operação (somente em sucesso)
 *   - mensagem legível para o usuário ou para log
 *   - erros estruturados por campo (compatível com withErrors() do Laravel)
 *   - código de status HTTP sugerido para a camada de apresentação
 *
 * A classe é imutável: todos os wither methods retornam uma nova instância.
 * O construtor é privado para forçar o uso dos factory methods.
 */
class Result
{
    /*
    |--------------------------------------------------------------------------
    | Estado Interno
    |--------------------------------------------------------------------------
    */

    /**
     * @param  bool  $success  Indica se a operação foi bem-sucedida.
     * @param  mixed  $data  Dados retornados pela operação (qualquer tipo; null em falha).
     * @param  string|null  $message  Mensagem descritiva para o usuário ou para log.
     * @param  array<string, array<string>>  $errors  Erros por campo no formato ['campo' => ['mensagem']],
     *                                                compatível com o MessageBag do Laravel.
     * @param  int  $status  Código de status HTTP recomendado para a resposta.
     */
    private function __construct(
        private readonly bool $success,
        private readonly mixed $data = null,
        private readonly ?string $message = null,
        private readonly array $errors = [],
        private readonly int $status = 200
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Fábricas Estáticas
    |--------------------------------------------------------------------------
    */

    /**
     * Cria um resultado de sucesso.
     *
     * @param  mixed  $data  Dados retornados pela operação (model, collection, array, etc.).
     * @param  string|null  $message  Mensagem opcional de sucesso.
     * @param  int  $status  Código HTTP (padrão: 200 OK).
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): self
    {
        return new self(true, $data, $message, [], $status);
    }

    /**
     * Cria um resultado de falha.
     *
     * Esta é a factory de propósito geral para falhas. Cobre dois cenários:
     *
     * Cenário A: erro genérico sem campo específico
     * Use quando a falha não está ligada a nenhum input do formulário,
     * como erros de banco de dados, recursos não encontrados, etc.
     * Neste caso, omita o parâmetro $errors.
     *
     * Cenário B: múltiplos campos + mensagem geral (toast/flash)
     * Use quando múltiplos inputs precisam ser destacados E uma mensagem
     * geral deve ser exibida num toast ou banner de alerta.
     * getMessage() vai para o flash/toast; getErrors() vai para os inputs.
     * O status padrão é 400 (Bad Request). Use 422 (Unprocessable Entity)
     * quando se tratar de violação de regra de negócio com dados tecnicamente válidos.
     *
     * @param  string  $message  Mensagem para flash/toast ou para log.
     * @param  int  $status  Código HTTP recomendado (padrão: 400 Bad Request).
     * @param  array<string, array<string>>  $errors  Erros por campo no formato ['campo' => ['mensagem']].
     */
    public static function failure(string $message, int $status = 400, array $errors = []): self
    {
        return new self(false, null, $message, $errors, $status);
    }

    /**
     * Cria um resultado de falha associado a um campo específico do formulário.
     *
     * A mensagem é usada simultaneamente como mensagem genérica (getMessage())
     * e como erro do campo (getErrors()), eliminando duplicação no código chamador.
     *
     * O status padrão é 422 (Unprocessable Entity), pois esta factory é voltada
     * para violações de regra de negócio em dados sintaticamente válidos.
     *
     * @param  string  $field  Nome do campo do formulário (ex: 'email').
     * @param  string  $message  Mensagem exibida tanto no input quanto no flash de alerta.
     * @param  int  $status  Código HTTP recomendado (padrão: 422 Unprocessable Entity).
     */
    public static function fieldFailure(string $field, string $message, int $status = 422): self
    {
        return new self(false, null, $message, [$field => [$message]], $status);
    }

    /*
    |--------------------------------------------------------------------------
    | Verificações
    |--------------------------------------------------------------------------
    */

    /**
     * Indica se a operação foi bem-sucedida.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Indica se a operação falhou.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Indica se existem erros de campo estruturados.
     *
     * Preferível a verificar `getErrors()` diretamente, pois encapsula
     * a lógica de verificação independente da estrutura interna.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso aos Dados (Getters)
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna os dados da operação bem-sucedida.
     *
     * Em resultados de falha, retorna null.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Retorna a mensagem descritiva do resultado.
     *
     * Pode ser uma mensagem de sucesso, erro genérico ou erro de campo.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Retorna os erros estruturados por campo.
     *
     * O formato retornado é compatível com o MessageBag do Laravel,
     * podendo ser passado diretamente para withErrors() em um RedirectResponse.
     *
     * Exemplo: ['platelets' => ['Plaquetas abaixo do mínimo.']]
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna o código de status HTTP recomendado para a resposta.
     *
     * Relevante principalmente em controllers de API que retornam JSON.
     * Em controllers web com redirect(), este valor é ignorado pelo browser.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /*
    |--------------------------------------------------------------------------
    | Interface Fluida (Wither Methods)
    |--------------------------------------------------------------------------
    | Retornam uma nova instância para manter a imutabilidade.
    | Úteis para enriquecer um Result existente sem mutar o original.
    */

    /**
     * Retorna uma nova instância com a mensagem substituída.
     *
     * @param  string  $message  Nova mensagem.
     */
    public function message(string $message): self
    {
        return new self($this->success, $this->data, $message, $this->errors, $this->status);
    }

    /**
     * Retorna uma nova instância com os dados substituídos.
     *
     * @param  mixed  $data  Novos dados.
     */
    public function data(mixed $data): self
    {
        return new self($this->success, $data, $this->message, $this->errors, $this->status);
    }

    /**
     * Retorna uma nova instância com os erros de campo substituídos.
     *
     * @param  array<string, array<string>>  $errors  Erros no formato ['campo' => ['mensagem']].
     */
    public function errors(array $errors): self
    {
        return new self($this->success, $this->data, $this->message, $errors, $this->status);
    }

    /**
     * Retorna uma nova instância com o código de status HTTP substituído.
     *
     * @param  int  $status  Novo código HTTP.
     */
    public function status(int $status): self
    {
        return new self($this->success, $this->data, $this->message, $this->errors, $status);
    }

    /*
    |--------------------------------------------------------------------------
    | Manipulação Funcional (Callbacks)
    |--------------------------------------------------------------------------
    */

    /**
     * Executa um callback se o resultado for de sucesso.
     *
     * O callback recebe os dados da operação como único argumento.
     * Retorna a própria instância para permitir encadeamento com onFailure().
     *
     * @param  \Closure(mixed): void  $callback
     */
    public function onSuccess(\Closure $callback): self
    {
        if ($this->isSuccess()) {
            $callback($this->data);
        }

        return $this;
    }

    /**
     * Executa um callback se o resultado for de falha.
     *
     * O callback recebe a mensagem e os erros de campo como argumentos.
     * Retorna a própria instância para permitir encadeamento com onSuccess().
     *
     * @param  \Closure(string|null, array<string, array<string>>): void  $callback
     */
    public function onFailure(\Closure $callback): self
    {
        if ($this->isFailure()) {
            $callback($this->message, $this->errors);
        }

        return $this;
    }

    /**
     * Encadeia uma nova operação se o resultado atual for de sucesso.
     * Se for falha, ignora o callback e passa o erro adiante (Trilho Vermelho).
     *
     * @param  \Closure(mixed): self  $callback
     */
    public function bind(\Closure $callback): self
    {
        if ($this->isFailure()) {
            return $this;
        }

        return $callback($this->data);
    }
}
