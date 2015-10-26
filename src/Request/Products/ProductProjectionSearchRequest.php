<?php
/**
 * @author @ct-jensschulze <jens.schulze@commercetools.de>
 * @created: 02.02.15, 11:26
 */

namespace Commercetools\Core\Request\Products;

use Commercetools\Core\Request\ExpandTrait;
use Commercetools\Core\Request\PriceTrait;
use Commercetools\Core\Request\Query\Parameter;
use Commercetools\Core\Request\QueryRequestInterface;
use Commercetools\Core\Request\SortRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Commercetools\Core\Client;
use Commercetools\Core\Model\Common\Context;
use Commercetools\Core\Model\Product\ProductProjectionCollection;
use Commercetools\Core\Request\AbstractProjectionRequest;
use Commercetools\Core\Request\PageTrait;
use Commercetools\Core\Request\SortTrait;
use Commercetools\Core\Response\ApiResponseInterface;
use Commercetools\Core\Response\PagedSearchResponse;
use Commercetools\Core\Model\Product\Search\FilterInterface;

/**
 * @package Commercetools\Core\Request\Products
 * @apidoc http://dev.sphere.io/http-api-projects-products-search.html#product-projections-by-search
 * @method PagedSearchResponse executeWithClient(Client $client)
 * @method ProductProjectionCollection mapResponse(ApiResponseInterface $response)
 */
class ProductProjectionSearchRequest extends AbstractProjectionRequest implements SortRequestInterface
{
    const FACET = 'facet';
    const FILTER = 'filter';
    const FILTER_QUERY = 'filter.query';
    const FILTER_FACETS = 'filter.facets';

    use ExpandTrait;
    use PageTrait;
    use SortTrait;
    use PriceTrait;

    protected $resultClass = '\Commercetools\Core\Model\Product\ProductProjectionCollection';

    /**
     * @param Context $context
     */
    public function __construct(Context $context = null)
    {
        parent::__construct(ProductProjectionEndpoint::endpoint(), $context);
    }

    /**
     * @param Context $context
     * @return static
     */
    public static function of(Context $context = null)
    {
        return new static($context);
    }

    /**
     * @return string
     */
    protected function getProjectionAction()
    {
        return 'search';
    }

    /**
     * @param ResponseInterface $response
     * @return PagedSearchResponse
     * @internal
     */
    public function buildResponse(ResponseInterface $response)
    {
        return new PagedSearchResponse($response, $this, $this->getContext());
    }

    /**
     * @param array $result
     * @param Context $context
     * @return ProductProjectionCollection
     */
    public function mapResult(array $result, Context $context = null)
    {
        $data = [];
        if (!empty($result['results'])) {
            $data = $result['results'];
        }
        return ProductProjectionCollection::fromArray($data, $context);
    }

    /**
     * @param string $type
     * @param FilterInterface $filter
     * @param bool $replace
     * @return $this
     */
    protected function filter($type, FilterInterface $filter, $replace = false)
    {
        return $this->addParam($type, $filter, $replace);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function addFilter(FilterInterface $filter)
    {
        return $this->filter(static::FILTER, $filter);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function addFilterQuery(FilterInterface $filter)
    {
        return $this->filter(static::FILTER_QUERY, $filter);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function addFilterFacets(FilterInterface $filter)
    {
        return $this->filter(static::FILTER_FACETS, $filter);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function addFacet(FilterInterface $filter)
    {
        return $this->filter(static::FACET, $filter);
    }

    /**
     * @param $fuzzy
     * @return $this
     */
    public function fuzzy($fuzzy)
    {
        if (!is_null($fuzzy)) {
            $this->addParamObject(new Parameter('fuzzy', (bool)$fuzzy));
        }

        return $this;
    }
}
