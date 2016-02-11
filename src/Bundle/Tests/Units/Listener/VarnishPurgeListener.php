<?php
namespace M6Web\Bundle\CacheExtraBundle\Listener\Tests\Units;

use \mageekguy\atoum;
use M6Web\Bundle\CacheExtraBundle\Listener;

/**
 * classe testant le VarnishPurgeListener
 */
class VarnishPurgeListener extends atoum\test
{
    public $logs      = [];
    public $purgeUrls = [];

    /**
     * @dataProvider onKernelRequestDataProvider
     */
    public function testOnKernelRequest($url, $param, $allowed, $expResult, $purgedUrls, $lastLogs)
    {
        $this->logs = [];
        $this->purgeUrls = [];

        $request              = $this->getRequestMock($url);
        $varnishPurgeListener = $this->getInstance($param, $allowed, $request);

        $this
            ->boolean($varnishPurgeListener->onKernelRequest())
                ->isIdenticalTo($expResult)
            ->array($this->purgeUrls)
                ->strictlyContainsValues($purgedUrls)
                ->size
                    ->isIdenticalTo(count($purgedUrls))
            ->array($this->logs)
                ->strictlyContainsValues($lastLogs)
                ->size
                    ->isIdenticalTo(count($lastLogs))
        ;
    }

    /**
     * @return array
     */
    public function onKernelRequestDataProvider()
    {
        return [
            [
                '/test/url.php',
                'delete',
                false,
                true,
                [],
                []
            ],
            [
                '/test/url.php?delete=1',
                'delete',
                true,
                true,
                [
                    '/test/url.php',
                ],
                [
                    'VARNISH PURGE : /test/url.php'
                ]
            ],
            [
                '/test/url.php?delete=1&blabla',
                'delete',
                true,
                true,
                [
                    '/test/url.php?blabla',
                ],
                [
                    'VARNISH PURGE : /test/url.php?blabla'
                ]
            ],
        ];
    }

    protected function getInstance($paramName, $allowed, $request)
    {
        $cacheResetter = $this->getCacheResetterMock($paramName, $allowed, $request);

        return new Listener\VarnishPurgeListener($cacheResetter, $this->getPurgeHelperMock(), $this->getLoggerMock());
    }

    protected function getCacheResetterMock($paramName, $allowed, $request)
    {
        $this->getMockGenerator()->orphanize('__construct');

        $cacheResetter = new \mock\M6Web\Bundle\CacheExtraBundle\Resetter\CacheResetter();
        $cacheResetter->getMockController()->getRequest       = $request;
        $cacheResetter->getMockController()->shouldResetCache = $allowed;
        $cacheResetter->getMockController()->getParamName     = $paramName;

        return $cacheResetter;
    }

    public function getRequestMock($url)
    {
        $this->getMockGenerator()->orphanize('__construct');

        $request = new \mock\Symfony\Component\HttpFoundation\Request();
        $request->getMockController()->getRequestUri = $url;

        return $request;
    }

    protected function getPurgeHelperMock()
    {
        $purgeHelper = new \mock\M6Web\Bundle\VarnishBundle\Helper\PurgeHelper();
        $that = $this;

        $purgeHelper->getMockController()->purgeUrl = function ($url, $request) use ($that) {
            $that->purgeUrls[] = $url;
        };

        return $purgeHelper;
    }

    protected function getLoggerMock()
    {
        $logger = new \mock\M6Web\Bundle\CacheExtraBundle\Tests\TestLoggerInterface();
        $that = $this;

        $logger->getMockController()->log = function ($message, $type) use ($that) {
            $that->logs[] = $message;
        };

        return $logger;
    }
}
