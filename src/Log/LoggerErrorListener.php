<?php

namespace RcmErrorHandler\Log;

use RcmErrorHandler\EventManager\HandlerListenerBase;
use RcmErrorHandler\Format\FormatBase;
use RcmErrorHandler\Model\Config;
use RcmErrorHandler\Model\GenericErrorInterface;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class LoggerErrorListener
 *
 * LoggerErrorListener
 *
 * PHP version 5
 *
 * @category  Reliv
 * @package   RcmErrorHandler\Log
 * @author    James Jervis <jjervis@relivinc.com>
 * @copyright 2014 Reliv International
 * @license   License.txt New BSD License
 * @version   Release: <package_version>
 * @link      https://github.com/reliv
 */
class LoggerErrorListener extends HandlerListenerBase
{
    /**
     * @var array Error numbers to method
     */
    protected $loggerMethodMap = [
            Logger::EMERG => 'emerg',
            Logger::ALERT => 'alert',
            Logger::CRIT => 'crit',
            Logger::ERR => 'err',
            Logger::WARN => 'warn',
            Logger::NOTICE => 'notice',
            Logger::INFO => 'info',
            Logger::DEBUG => 'debug',
        ];

    /**
     * @var \RcmErrorHandler\Model\Config
     */
    public $options;

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * @param \RcmErrorHandler\Model\Config $options
     */
    public function __construct(
        Config $options,
        ServiceLocatorInterface $serviceLocator
    ) {
        $this->options = $options;
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * log
     *
     * @param GenericErrorInterface $error
     *
     * @return void
     */
    protected function doLog(GenericErrorInterface $error)
    {
        $loggerConfig = $this->options->get('loggers');

        $serviceLocator = $this->serviceLocator;

        $extra = $this->getExtras($error);

        $method = $this->getMethodFromErrorNumber($error->getSeverity());

        $message = $this->prepareSummary($error);

        foreach ($loggerConfig as $serviceName) {
            if ($serviceLocator->has($serviceName)) {
                /** @var \Zend\Log\LoggerInterface $logger */
                $logger = $serviceLocator->get($serviceName);
                $logger->$method($message, $extra);
            }
        }
    }

    /**
     * getExtras
     *
     * @param GenericErrorInterface $error
     *
     * @return array
     */
    protected function getExtras(GenericErrorInterface $error)
    {

        $formatter = new FormatBase();

        $extras = [
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'message' => $error->getMessage(),
        ];

        if ($this->options->get('includeStacktrace', false) == true) {
            $extras['trace'] = $formatter->getTraceString($error);
        }

        return $extras;
    }

    /**
     * update
     *
     * @param \Zend\EventManager\Event $event
     *
     * @return void
     */
    public function update(\Zend\EventManager\Event $event)
    {
        /** @var \RcmErrorHandler\Handler\Handler $handler */
        // $handler = $event->getParam('handler');

        /** @var \RcmErrorHandler\Model\GenericErrorInterface $error */
        $error = $event->getParam('error');

        $firstError = $error->getFirst();

        /** @var \RcmErrorHandler\Model\Config $config */
        // $config = $event->getParam('config');

        $this->doLog($firstError);
    }

    /**
     * prepareSummary
     *
     * @param GenericErrorInterface $error
     *
     * @return string
     */
    protected function prepareSummary(GenericErrorInterface $error)
    {
        return $error->getType() . ' - ' .
        $error->getMessage() . ' - ' .
        $this->buildRelativePath($error->getFile());
    }

    /**
     * buildRelativePath
     *
     * @param $absoluteDir
     *
     * @return mixed
     */
    protected function buildRelativePath($absoluteDir)
    {
        $relativeDir = $absoluteDir;

        $appDir = exec('pwd'); // or getcwd() could work if no symlinks are used

        $dirLength = strlen($appDir);

        if (substr($absoluteDir, 0, $dirLength) == $appDir) {
            $relativeDir = substr_replace($absoluteDir, '', 0, $dirLength);
        }

        return $relativeDir;
    }

    /**
     * getMethodFromErrorNumber
     *
     * @param $errno
     *
     * @return string
     */
    protected function getMethodFromErrorNumber($errno)
    {
        $priority = Logger::INFO;

        if (isset(Logger::$errorPriorityMap[$errno])) {
            $priority = Logger::$errorPriorityMap[$errno];
        }

        $method = 'info';

        if (isset($this->loggerMethodMap[$priority])) {
            $method = $this->loggerMethodMap[$priority];
        }

        return $method;
    }
}
