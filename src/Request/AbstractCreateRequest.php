<?php
/**
 * @author @ct-jensschulze <jens.schulze@commercetools.de>
 * @created: 26.01.15, 17:34
 */

namespace Sphere\Core\Request;


use Sphere\Core\Http\HttpMethod;
use Sphere\Core\Http\JsonEndpoint;
use Sphere\Core\Http\JsonRequest;
use Sphere\Core\Response\SingleResourceResponse;

/**
 * Class AbstractCreateRequest
 * @package Sphere\Core\Request
 */
abstract class AbstractCreateRequest extends AbstractApiRequest
{
    /**
     * @var \JsonSerializable
     */
    protected $object;

    /**
     * @param JsonEndpoint $endpoint
     * @param $object
     */
    public function __construct(JsonEndpoint $endpoint, \JsonSerializable $object)
    {
        parent::__construct($endpoint);
        $this->object = $object;
    }

    /**
     * @return \JsonSerializable
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param \JsonSerializable
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * @return JsonRequest
     * @internal
     */
    public function httpRequest()
    {
        return new JsonRequest(HttpMethod::POST, (string)$this->endpoint, $this->getObject());
    }

    /**
     * @param $response
     * @return SingleResourceResponse
     * @internal
     */
    public function buildResponse($response)
    {
        return new SingleResourceResponse($response, $this);
    }
}
