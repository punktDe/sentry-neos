<?php
namespace PunktDe\Sentry\Neos\Aspect;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Exception as FlowException;
use Neos\Utility\Arrays;

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
     * @var string
     */
    protected $renderingOptions;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="error.exceptionHandler")
     * @var array
     */
    protected $options;

    /**
     * Forward all exceptions that are handled in Fusion rendering exception handlers to Sentry
     * Skipped as just passing throuh to be handled somewhere else:
     * ContextDependentHandler, ThrowingHandler, NodeWrappingHandler, BubblingHandler
     *
     * @Flow\After("method(Neos\Fusion\Core\ExceptionHandlers\HtmlMessageHandler->handle())")
     * @Flow\After("method(Neos\Fusion\Core\ExceptionHandlers\PlainTextHandler->handle())")
     * @Flow\After("method(Neos\Fusion\Core\ExceptionHandlers\XmlCommentHandler->handle())")
     * @param JoinPointInterface $joinPoint
     */
    public function captureException(JoinPointInterface $joinPoint): void
    {
        $exception = null;
        $fusionPath = 'not-set';

        if ($joinPoint->isMethodArgument('exception')) {
            $exception = $joinPoint->getMethodArgument('exception');
        }

        $this->renderingOptions = $this->resolveCustomRenderingOptions($exception);

        if (isset($this->renderingOptions['logException']) && !$this->renderingOptions['logException']){
            return;
        }


        if ($joinPoint->isMethodArgument('fusionPath')) {
            $fusionPath = $joinPoint->getMethodArgument('fusionPath');
        }

        $this->errorHandler->handleException($exception, ['fusionPath' => $fusionPath]);
    }
    
    /**
     * @param \Throwable $exception
     * @return string name of the resolved renderingGroup or NULL if no group could be resolved
     */
    protected function resolveRenderingGroup(\Throwable $exception)
    {
        if (!isset($this->options['renderingGroups'])) {
            return null;
        }
        foreach ($this->options['renderingGroups'] as $renderingGroupName => $renderingGroupSettings) {
            if (isset($renderingGroupSettings['matchingExceptionClassNames'])) {
                foreach ($renderingGroupSettings['matchingExceptionClassNames'] as $exceptionClassName) {
                    if ($exception instanceof $exceptionClassName) {
                        return $renderingGroupName;
                    }
                }
            }
            if (isset($renderingGroupSettings['matchingStatusCodes']) && $exception instanceof FlowException) {
                if (in_array($exception->getStatusCode(), $renderingGroupSettings['matchingStatusCodes'])) {
                    return $renderingGroupName;
                }
            }
        }
        return null;
    }

    /**
     * Checks if custom rendering rules apply to the given $exception and returns those.
     *
     * @param \Throwable $exception
     * @return array the custom rendering options, or NULL if no custom rendering is defined for this exception
     */
    protected function resolveCustomRenderingOptions(\Throwable $exception): array
    {
        $renderingOptions = [];
        if (isset($this->options['defaultRenderingOptions'])) {
            $renderingOptions = $this->options['defaultRenderingOptions'];
        }
        $renderingGroup = $this->resolveRenderingGroup($exception);
        if ($renderingGroup !== null) {
            $renderingOptions = Arrays::arrayMergeRecursiveOverrule($renderingOptions, $this->options['renderingGroups'][$renderingGroup]['options']);
            $renderingOptions['renderingGroup'] = $renderingGroup;
        }
        return $renderingOptions;
    }
}
