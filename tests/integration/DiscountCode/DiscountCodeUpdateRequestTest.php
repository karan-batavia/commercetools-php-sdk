<?php
/**
 * @author @jenschude <jens.schulze@commercetools.de>
 */

namespace Commercetools\Core\IntegrationTests\DiscountCode;

use Commercetools\Core\Builder\Request\RequestBuilder;
use Commercetools\Core\IntegrationTests\ApiTestCase;
use Commercetools\Core\IntegrationTests\CartDiscount\CartDiscountFixture;
use Commercetools\Core\Model\CartDiscount\AbsoluteCartDiscountValue;
use Commercetools\Core\Model\CartDiscount\CartDiscount;
use Commercetools\Core\Model\CartDiscount\CartDiscountDraft;
use Commercetools\Core\Model\CartDiscount\CartDiscountReference;
use Commercetools\Core\Model\CartDiscount\CartDiscountReferenceCollection;
use Commercetools\Core\Model\CartDiscount\CartDiscountTarget;
use Commercetools\Core\Model\CartDiscount\CartDiscountValue;
use Commercetools\Core\Model\Common\Money;
use Commercetools\Core\Model\Common\MoneyCollection;
use Commercetools\Core\Model\CustomField\CustomFieldObjectDraft;
use Commercetools\Core\Model\DiscountCode\DiscountCode;
use Commercetools\Core\Model\DiscountCode\DiscountCodeDraft;
use Commercetools\Core\Model\Common\LocalizedString;
use Commercetools\Core\Model\Type\Type;
use Commercetools\Core\Request\CartDiscounts\CartDiscountCreateRequest;
use Commercetools\Core\Request\CartDiscounts\CartDiscountDeleteRequest;
use Commercetools\Core\Request\CustomField\Command\SetCustomFieldAction;
use Commercetools\Core\Request\CustomField\Command\SetCustomTypeAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeChangeCartDiscountsAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeChangeGroupsAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeChangeIsActiveAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetCartPredicateAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetDescriptionAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetMaxApplicationsAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetMaxApplicationsPerCustomerAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetNameAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetValidFromAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetValidFromAndUntilAction;
use Commercetools\Core\Request\DiscountCodes\Command\DiscountCodeSetValidUntilAction;
use Commercetools\Core\Request\DiscountCodes\DiscountCodeCreateRequest;
use Commercetools\Core\Request\DiscountCodes\DiscountCodeDeleteRequest;
use Commercetools\Core\Request\DiscountCodes\DiscountCodeUpdateRequest;

class DiscountCodeUpdateRequestTest extends ApiTestCase
{
    private $discountDeleteRequests = [];

    protected function cleanup()
    {
        parent::cleanup();
        $this->deleteCartDiscount();
    }

    protected function createCartDiscount($draft)
    {
        $request = CartDiscountCreateRequest::ofDraft($draft);
        $response = $request->executeWithClient($this->getClient());
        $cartDiscount = $request->mapResponse($response);

        $this->discountDeleteRequests[] = $this->discountDeleteRequest = CartDiscountDeleteRequest::ofIdAndVersion(
            $cartDiscount->getId(),
            $cartDiscount->getVersion()
        );

        return $cartDiscount;
    }

    protected function deleteCartDiscount()
    {
        if (count($this->discountDeleteRequests) > 0) {
            foreach ($this->discountDeleteRequests as $request) {
                $this->getClient()->addBatchRequest($request);
            }
            $this->getClient()->executeBatch();
            $this->discountDeleteRequests = [];
            $this->cartDiscount = null;
        }
    }

    /**
     * @param $code
     * @return DiscountCodeDraft
     */
    protected function getDraft($code)
    {
        return $this->getDiscountCodeDraft($code)->setIsActive(false);
    }

    protected function createDiscountCode(DiscountCodeDraft $draft)
    {
        $request = DiscountCodeCreateRequest::ofDraft($draft);
        $response = $request->executeWithClient($this->getClient());
        $discountCode = $request->mapResponse($response);
        $this->cleanupRequests[] = $this->deleteRequest = DiscountCodeDeleteRequest::ofIdAndVersion(
            $discountCode->getId(),
            $discountCode->getVersion()
        );

        return $discountCode;
    }

    public function testCustomTypeCreate()
    {
        $client = $this->getApiClient();
        $type = $this->getType($this->getTestRun() . '-discount-type', 'discount-code');

        DiscountCodeFixture::withDraftDiscountCode(
            $client,
            function (DiscountCodeDraft $draft) use ($type) {
                return $draft->setCustom(CustomFieldObjectDraft::ofTypeKey($type->getKey()));
            },
            function (DiscountCode $discountCode) use ($client, $type) {
                $this->assertSame($type->getId(), $discountCode->getCustom()->getType()->getId());
            }
        );
    }

    public function testCustomTypeUpdate()
    {
        $client = $this->getApiClient();
        // ToDo: use TypeFixture when available
        $type = $this->getType($this->getTestRun() . '-discount-type', 'discount-code');

        DiscountCodeFixture::withUpdateableDraftDiscountCode(
            $client,
            function (DiscountCodeDraft $draft) use ($type) {
                return $draft->setCustom(CustomFieldObjectDraft::ofTypeKey($type->getKey()));
            },
            function (DiscountCode $discountCode) use ($client, $type) {
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(SetCustomTypeAction::ofTypeKey($type->getKey()));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertSame($type->getId(), $result->getCustom()->getType()->getId());

                return $result;
            }
        );
    }

    public function testCustomFieldUpdate()
    {
        // ToDo: use TypeFixture when available
        $client = $this->getApiClient();
        $type = $this->getType($this->getTestRun() . '-discount-type', 'discount-code');

        DiscountCodeFixture::withUpdateableDraftDiscountCode(
            $client,
            function (DiscountCodeDraft $draft) use ($type) {
                return $draft->setCustom(CustomFieldObjectDraft::ofTypeKey($type->getKey()));
            },
            function (DiscountCode $discountCode) use ($client, $type) {
                $newValue = $this->getTestRun() . '-value';
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(SetCustomFieldAction::ofName('testField')->setValue($newValue));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertSame($newValue, $result->getCustom()->getFields()->getTestField());

                return $result;
            }
        );
    }


    public function testChangeCartDiscountAction()
    {
        $client = $this->getApiClient();

        CartDiscountFixture::withDraftCartDiscount(
            $client,
            function (CartDiscountDraft $cartDiscountDraft) {
                return $cartDiscountDraft
                    ->setName(LocalizedString::ofLangAndText('en', 'new-cart-discount'))
                    ->setRequiresDiscountCode(true);
            },
            function (CartDiscount $cartDiscount) use ($client) {
                DiscountCodeFixture::withUpdateableDiscountCode(
                    $client,
                    function (DiscountCode $discountCode) use ($client, $cartDiscount) {
                        $cartDiscountReference = CartDiscountReference::ofId($cartDiscount->getId());
                        $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                            ->addAction(
                                DiscountCodeChangeCartDiscountsAction::ofCartDiscountReference($cartDiscountReference)
                            );
                        $response = $this->execute($client, $request);
                        $result = $request->mapFromResponse($response);

                        $this->assertInstanceOf(DiscountCode::class, $result);
                        $this->assertSame($cartDiscount->getId(), $result->getCartDiscounts()->current()->getId());
                        $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                        return $result;
                    }
                );
            }
        );
    }

    public function testChangeCartDiscountsAction()
    {
        $client = $this->getApiClient();

        CartDiscountFixture::withDraftCartDiscount(
            $client,
            function (CartDiscountDraft $cartDiscountDraft) {
                return $cartDiscountDraft
                    ->setName(LocalizedString::ofLangAndText('en', 'new-cart-discount'))
                    ->setRequiresDiscountCode(true);
            },
            function (CartDiscount $cartDiscount) use ($client) {
                DiscountCodeFixture::withUpdateableDiscountCode(
                    $client,
                    function (DiscountCode $discountCode) use ($client, $cartDiscount) {
                        $discounts = $discountCode->getCartDiscounts()->add($cartDiscount->getReference());
                        $expectedIds = [];
                        foreach ($discounts as $discount) {
                            $expectedIds[] = $discount->getId();
                        }
                        $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                            ->addAction(
                                DiscountCodeChangeCartDiscountsAction::ofCartDiscountReferences($discounts)
                            );
                        $response = $this->execute($client, $request);
                        $result = $request->mapFromResponse($response);

                        $this->assertInstanceOf(DiscountCode::class, $result);
                        $this->assertCount(2, $result->getCartDiscounts());
                        $ids = [];
                        foreach ($result->getCartDiscounts() as $discount) {
                            $ids[] = $discount->getId();
                        }
                        $this->assertSame($expectedIds, $ids);
                        $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                        return $result;
                    }
                );
            }
        );
    }

    public function testSetDescription()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $description = 'new description';
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetDescriptionAction::of()->setDescription(
                        LocalizedString::ofLangAndText('en', $description)
                    ));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($description, $result->getDescription()->en);
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testChangeIsActive()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $isActive = true;
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeChangeIsActiveAction::of()->setIsActive($isActive));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($isActive, $result->getIsActive());
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testChangeGroups()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDraftDiscountCode(
            $client,
            function (DiscountCodeDraft $draft) {
                return $draft->setGroups(['test']);
            },
            function (DiscountCode $discountCode) use ($client) {
                $this->assertSame('test', current($discountCode->getGroups()));

                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeChangeGroupsAction::of()->setGroups(['test2']));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame('test2', current($result->getGroups()));
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetCartPredicate()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $cartPredicate = '2=2';
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetCartPredicateAction::of()->setCartPredicate($cartPredicate));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($cartPredicate, $result->getCartPredicate());
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetMaxApplications()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $maxApplications = mt_rand(1, 10);
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetMaxApplicationsAction::of()->setMaxApplications($maxApplications));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($maxApplications, $result->getMaxApplications());
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetMaxApplicationsPerCustomer()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $maxApplicationsPerCustomer = mt_rand(1, 10);
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetMaxApplicationsPerCustomerAction::of()
                        ->setMaxApplicationsPerCustomer($maxApplicationsPerCustomer));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($maxApplicationsPerCustomer, $result->getMaxApplicationsPerCustomer());
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetName()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $name = LocalizedString::ofLangAndText('en', $this->getTestRun() . '-new-name');
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetNameAction::of()->setName($name));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $this->assertSame($name->en, $result->getName()->en);
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetValidFrom()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $validFrom = new \DateTime();
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetValidFromAction::of()->setValidFrom($validFrom));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $validFrom->setTimezone(new \DateTimeZone('UTC'));
                $this->assertSame($validFrom->format('c'), $result->getValidFrom()->format('c'));
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testValidUntilFrom()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $validUntil = new \DateTime();
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(DiscountCodeSetValidUntilAction::of()->setValidUntil($validUntil));
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $validUntil->setTimezone(new \DateTimeZone('UTC'));
                $this->assertSame($validUntil->format('c'), $result->getValidUntil()->format('c'));
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }

    public function testSetValidFromAndUntil()
    {
        $client = $this->getApiClient();

        DiscountCodeFixture::withUpdateableDiscountCode(
            $client,
            function (DiscountCode $discountCode) use ($client) {
                $validFrom = new \DateTime();
                $validUntil = new \DateTime('+1 second');
                $request = RequestBuilder::of()->discountCodes()->update($discountCode)
                    ->addAction(
                        DiscountCodeSetValidFromAndUntilAction::of()
                            ->setValidFrom($validFrom)
                            ->setValidUntil($validUntil)
                    );
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertInstanceOf(DiscountCode::class, $result);
                $validUntil->setTimezone(new \DateTimeZone('UTC'));
                $validFrom->setTimezone(new \DateTimeZone('UTC'));
                $this->assertSame($validUntil->format('c'), $result->getValidUntil()->format('c'));
                $this->assertSame($validFrom->format('c'), $result->getValidFrom()->format('c'));
                $this->assertNotSame($discountCode->getVersion(), $result->getVersion());

                return $result;
            }
        );
    }
}
