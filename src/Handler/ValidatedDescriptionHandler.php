<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\Handler;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\NonSerializableTrait;
use GuzzleHttp\Command\Guzzle\SchemaValidator;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\DiagnosticValue;

/**
 * Handler used to validate command input against a service description.
 *
 * @final
 *
 * @author Stefano Kowalke <info@arroba-it.de>
 */
class ValidatedDescriptionHandler
{
    use NonSerializableTrait;

    private SchemaValidator $validator;

    private DescriptionInterface $description;

    /**
     * ValidatedDescriptionHandler constructor.
     */
    public function __construct(DescriptionInterface $description, ?SchemaValidator $schemaValidator = null)
    {
        $this->description = $description;
        $this->validator = $schemaValidator ?: new SchemaValidator();
    }

    /**
     * @param callable(CommandInterface): PromiseInterface<ResultInterface, mixed> $handler
     *
     * @return \Closure(CommandInterface): PromiseInterface<ResultInterface, mixed>
     */
    public function __invoke(callable $handler): \Closure
    {
        return function (
            #[\SensitiveParameter]
            CommandInterface $command
        ) use ($handler): PromiseInterface {
            $errors = [];
            $operation = $this->description->getOperation($command->getName());

            foreach ($operation->getParams() as $name => $schema) {
                $value = $command[$name];

                if ($value) {
                    $value = $schema->filter($value);
                }

                if (!$this->validator->validate($schema, $value)) {
                    $errors = array_merge($errors, $this->validator->getErrors());
                } elseif ($value !== $command[$name]) {
                    // Update the config value if it changed and no validation errors were encountered.
                    // This happen when the user extending an operation
                    // See https://github.com/guzzle/guzzle-services/issues/145
                    $command[$name] = $value;
                }
            }

            if ($params = $operation->getAdditionalParameters()) {
                foreach ($command->toArray() as $name => $value) {
                    // It's only additional if it isn't defined in the schema
                    if (!$operation->hasParam($name)) {
                        // Always set the name so that error messages are useful
                        $params->setName($name);
                        if (!$this->validator->validate($params, $value)) {
                            $errors = array_merge($errors, $this->validator->getErrors());
                        } elseif ($value !== $command[$name]) {
                            $command[$name] = $value;
                        }
                    }
                }
            }

            if ($errors) {
                $diagnosticErrors = array_map(static function (string $error): string {
                    return DiagnosticValue::escape($error);
                }, $errors);

                throw new CommandException(\sprintf('Validation errors: %s', implode('; ', $diagnosticErrors)), $command);
            }

            return $handler($command);
        };
    }
}
