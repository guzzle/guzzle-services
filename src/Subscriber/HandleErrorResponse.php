<?php
namespace GuzzleHttp\Command\Guzzle\Subscriber;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Subscriber that reads the "errorResponses" from commands, and trigger appropriate exceptions
 *
 * In order for the exception to be properly triggered, all your exceptions must be instance
 * of "GuzzleHttp\Command\Exception\CommandException". If that's not the case, your exceptions will be wrapped
 * around a CommandException
 *
 * @TODO Refactor to middleware or transformer.
 */
class HandleErrorResponse implements SubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public function getEvents()
    {
        return ['process' => ['handleError']];
    }

    /**
     * @internal
     * @param ProcessEvent $event
     */
    public function handleError(ProcessEvent $event)
    {
        if (!($exception = $event->getException())) {
            return;
        }

        $name      = $event->getCommand()->getName();
        $operation = $event->getClient()->getDescription()->getOperation($name);
        $errors    = $operation->getErrorResponses();
        $response  = $event->getResponse();

        // We iterate through each errors in service description. If the descriptor contains both a phrase and
        // status code, there must be an exact match of both. Otherwise, a match of status code is enough
        $bestException = null;

        foreach ($errors as $error) {
            $code = (int) $error['code'];

            if ($response->getStatusCode() !== $code) {
                continue;
            }

            if (isset($error['phrase']) && !($error['phrase'] === $response->getReasonPhrase())) {
                continue;
            }

            $bestException = $error['class'];

            // If there is an exact match of phrase + code, then we cannot find a more specialized exception in
            // the array, so we can break early instead of iterating the remaining ones
            if (isset($error['phrase'])) {
                break;
            }
        }

        if (null !== $bestException) {
            throw new $bestException($response->getReasonPhrase(), $event->getTransaction(), $exception);
        }

        // If we reach here, no exception could be match from descriptor, and Guzzle exception will propagate
    }
}
