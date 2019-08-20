<?php namespace GuzzleHttp\Command\Guzzle\Handler;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\SchemaValidator;

/**
 * Handler used to validate command input against a service description.
 *
 * @author Stefano Kowalke <info@arroba-it.de>
 */
class ValidatedDescriptionHandler
{
    /** @var SchemaValidator $validator */
    private $validator;

    /** @var DescriptionInterface $description */
    private $description;

    /**
     * ValidatedDescriptionHandler constructor.
     *
     * @param DescriptionInterface $description
     * @param SchemaValidator|null $schemaValidator
     */
    public function __construct(DescriptionInterface $description, SchemaValidator $schemaValidator = null)
    {
        $this->description = $description;
        $this->validator = $schemaValidator ?: new SchemaValidator();
    }

    /**
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (CommandInterface $command) use ($handler) {
            $errors = [];
            $operation = $this->description->getOperation($command->getName());

            foreach ($operation->getParams() as $name => $schema) {
                $value = $command[$name];

                $preValidationValue = $schema->filter(
                    $value,
                    Parameter::FILTER_STAGE_BEFORE_VALIDATION
                );

                if (!$this->validator->validate($schema, $preValidationValue)) {
                    $errors =
                        array_merge($errors, $this->validator->getErrors());
                } else {
                    $postValidationValue = $schema->filter(
                        $preValidationValue,
                        Parameter::FILTER_STAGE_AFTER_VALIDATION
                    );

                    if ($postValidationValue !== $command[$name]) {
                        // Update the parameter value if it has changed and no
                        // validation errors were encountered. This ensures the
                        // parameter has a value even when the user is extending
                        // an operation.
                        //
                        // See:
                        // https://github.com/guzzle/guzzle-services/issues/145
                        $command[$name] = $postValidationValue;
                    }
                }
            }

            if ($params = $operation->getAdditionalParameters()) {
                foreach ($command->toArray() as $name => $value) {
                    // It's only additional if it isn't defined in the schema
                    if (! $operation->hasParam($name)) {
                        // Always set the name so that error messages are useful
                        $params->setName($name);
                        if (! $this->validator->validate($params, $value)) {
                            $errors = array_merge($errors, $this->validator->getErrors());
                        } elseif ($value !== $command[$name]) {
                            $command[$name] = $value;
                        }
                    }
                }
            }

            if ($errors) {
                throw new CommandException('Validation errors: ' . implode("\n", $errors), $command);
            }

            return $handler($command);
        };
    }
}
