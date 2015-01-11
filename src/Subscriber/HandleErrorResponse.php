<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace GuzzleHttp\Command\Guzzle\Subscriber;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Subscriber that can be used when filling the "errorResponses" property for commands. In order for the
 * exception to be properly triggered, all your exceptions must be instance of "GuzzleHttp\Command\Exception\CommandException".
 * If that's not the case, your exceptions will be wrapped around a CommandException
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
            throw new $bestException($event->getTransaction());
        }

        // If we reach here, no exception could be match from descriptor, and Guzzle exception will propagate
    }
}
