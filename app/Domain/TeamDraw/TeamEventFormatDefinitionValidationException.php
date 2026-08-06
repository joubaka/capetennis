<?php

namespace App\Domain\TeamDraw;

class TeamEventFormatDefinitionValidationException extends \InvalidArgumentException
{
    /** @var array<string,array<int,string>> */
    private array $errors;

    /**
     * @param array<string,array<int,string>> $errors
     */
    public function __construct(array $errors, string $message = 'Invalid team event format definition.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function single(string $field, string $message): self
    {
        return new self([$field => [$message]], $message);
    }
}
