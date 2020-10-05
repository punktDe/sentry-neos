<?php
namespace PunktDe\Sentry\Neos\Aspect;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Fusion\Core\ExceptionHandlers\AbsorbingHandler;

/**
 * @Flow\Aspect
 */
class FusionHandlerAspect
{
    /**
     * @Flow\Inject
     * @var \PunktDe\Sentry\Flow\SentryClient
     */
    protected $errorHandler;

    /**
     * Forward all exceptions that are handled in Fusion rendering exception handlers to Sentry
     *
     * @Flow\After("within(Neos\Fusion\Core\ExceptionHandlers\AbstractRenderingExceptionHandler) && method(.*->handle())")
     * @param JoinPointInterface $joinPoint
     */
    public function captureException(JoinPointInterface $joinPoint): void
    {
        if ($joinPoint->getProxy() instanceof AbsorbingHandler) {
            return;
        }

        $exception = null;
        $fusionPath = 'not-set';

        if ($joinPoint->isMethodArgument('exception')) {
            $exception = $joinPoint->getMethodArgument('exception');
        }

        if ($joinPoint->isMethodArgument('fusionPath')) {
            $fusionPath = $joinPoint->getMethodArgument('fusionPath');
        }

        $this->errorHandler->handleException($exception, ['fusionPath' => $fusionPath]);
    }
}
