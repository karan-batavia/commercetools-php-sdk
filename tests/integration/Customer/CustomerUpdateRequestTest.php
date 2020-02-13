<?php
/**
 * @author @jenschude <jens.schulze@commercetools.de>
 */


namespace Commercetools\Core\IntegrationTests\Customer;

use Commercetools\Core\Builder\Request\RequestBuilder;
use Commercetools\Core\IntegrationTests\ApiTestCase;
use Commercetools\Core\IntegrationTests\Store\StoreFixture;
use Commercetools\Core\Model\Common\Address;
use Commercetools\Core\Model\Common\AddressCollection;
use Commercetools\Core\Model\Common\CreatedBy;
use Commercetools\Core\Model\Common\LastModifiedBy;
use Commercetools\Core\Model\Customer\Customer;
use Commercetools\Core\Model\Customer\CustomerDraft;
use Commercetools\Core\Model\CustomField\CustomFieldObjectDraft;
use Commercetools\Core\Model\CustomField\FieldContainer;
use Commercetools\Core\Model\Store\Store;
use Commercetools\Core\Model\Store\StoreReference;
use Commercetools\Core\Model\Store\StoreReferenceCollection;
use Commercetools\Core\Request\Customers\Command\CustomerAddAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerAddBillingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerAddShippingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerAddStoreAction;
use Commercetools\Core\Request\Customers\Command\CustomerChangeAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerChangeEmailAction;
use Commercetools\Core\Request\Customers\Command\CustomerRemoveAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerRemoveBillingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerRemoveShippingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerRemoveStoreAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetCompanyNameAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetCustomerGroupAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetCustomerNumberAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetDateOfBirthAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetDefaultBillingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetDefaultShippingAddressAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetExternalIdAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetFirstNameAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetKeyAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetLastNameAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetLocaleAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetMiddleNameAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetSalutationAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetStoresAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetTitleAction;
use Commercetools\Core\Request\Customers\Command\CustomerSetVatIdAction;
use Commercetools\Core\Request\Customers\CustomerCreateRequest;
use Commercetools\Core\Request\Customers\CustomerDeleteRequest;
use Commercetools\Core\Request\Customers\CustomerUpdateByKeyRequest;
use Commercetools\Core\Request\Customers\CustomerUpdateRequest;
use Commercetools\Core\Request\CustomField\Command\SetCustomFieldAction;
use Commercetools\Core\Request\CustomField\Command\SetCustomTypeAction;
use Commercetools\Core\Request\InStores\InStoreRequestDecorator;
use function GuzzleHttp\Psr7\str;

class CustomerUpdateRequestTest extends ApiTestCase
{
    /**
     * @return CustomerDraft
     */
    protected function getDraft($name)
    {
        $draft = CustomerDraft::ofEmailNameAndPassword(
            'test-' . $this->getTestRun() . '-email',
            'test-' . $this->getTestRun() . '-' . $name,
            'test-' . $this->getTestRun() . '-lastName',
            'test-' . $this->getTestRun() . '-password'
        );

        return $draft;
    }

    protected function createCustomer(CustomerDraft $draft)
    {
        $request = CustomerCreateRequest::ofDraft($draft);
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);

        $this->cleanupRequests[] = $this->deleteRequest = CustomerDeleteRequest::ofIdAndVersion(
            $result->getCustomer()->getId(),
            $result->getCustomer()->getVersion()
        );
        return $result->getCustomer();
    }

    protected function createStoreCustomer($storeKey, CustomerDraft $draft)
    {
        $request = InStoreRequestDecorator::ofStoreKeyAndRequest(
            $storeKey,
            CustomerCreateRequest::ofDraft($draft)
        );
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);

        $this->deleteRequest = CustomerDeleteRequest::ofIdAndVersion(
            $result->getCustomer()->getId(),
            $result->getCustomer()->getVersion()
        );
        $this->cleanupRequests[] = InStoreRequestDecorator::ofStoreKeyAndRequest($storeKey, $this->deleteRequest);

        return $result->getCustomer();
    }

    public function testUpdateByKey()
    {
        $draft = $this->getDraft('update-by-key');
        $draft->setKey('test-'. $this->getTestRun());
        $customer = $this->createCustomer($draft);

        $firstName = 'test-' . $this->getTestRun() . '-new firstName';
        $request = CustomerUpdateByKeyRequest::ofKeyAndVersion($customer->getKey(), $customer->getVersion())
            ->addAction(
                CustomerSetFirstNameAction::of()->setFirstName($firstName)
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);
        $this->deleteRequest->setVersion($result->getVersion());

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame($firstName, $result->getFirstName());
    }

    public function testSetKey()
    {
        $draft = $this->getDraft('set-key');
        $customer = $this->createCustomer($draft);

        $key = 'new-' . $this->getTestRun();
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerSetKeyAction::of()->setKey($key)
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);
        $this->deleteRequest->setVersion($result->getVersion());

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame($key, $result->getKey());
        $this->assertNotSame($customer->getVersion(), $result->getVersion());
    }

    public function testCustomerEmail()
    {
        $draft = $this->getDraft('email');
        $customer = $this->createCustomer($draft);

        $email = 'new-' . $this->getTestRun() . '@example.com';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerChangeEmailAction::ofEmail($email))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($email, $customer->getEmail());
    }

    public function testSalutation()
    {
        $draft = $this->getDraft('salutation');
        $customer = $this->createCustomer($draft);

        $salutation = 'new-salutation';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetSalutationAction::of()->setSalutation($salutation))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($salutation, $customer->getSalutation());
    }

    public function testNoopCustomerEmail()
    {
        $draft = $this->getDraft('email');
        $customer = $this->createCustomer($draft);
        $version = $customer->getVersion();
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerChangeEmailAction::ofEmail($draft->getEmail()))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($version, $customer->getVersion());
        $this->assertSame($draft->getEmail(), $customer->getEmail());
    }

    public function testFirstName()
    {
        $draft = $this->getDraft('firstName');
        $customer = $this->createCustomer($draft);

        $firstName = 'new-' . $this->getTestRun() . '-firstName';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetFirstNameAction::of()->setFirstName($firstName))
            ->addAction(CustomerSetFirstNameAction::of()->setFirstName($firstName))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($firstName, $customer->getFirstName());
    }

    public function testLastName()
    {
        $draft = $this->getDraft('lastName');
        $customer = $this->createCustomer($draft);

        $lastName = 'new-' . $this->getTestRun() . '-lastName';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetLastNameAction::of()->setLastName($lastName))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($lastName, $customer->getLastName());
    }

    public function testMiddleName()
    {
        $draft = $this->getDraft('middleName');
        $customer = $this->createCustomer($draft);

        $middleName = 'new-' . $this->getTestRun() . '-middleName';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetMiddleNameAction::of()->setMiddleName($middleName))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($middleName, $customer->getMiddleName());
    }

    public function testTitle()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $title = 'new-' . $this->getTestRun() . '-title';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetTitleAction::of()->setTitle($title))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($title, $customer->getTitle());
    }

    public function testAddress()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName('new-' . $this->getTestRun() . '-firstName');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());
        $this->assertSame($address->getFirstName(), $customer->getAddresses()->current()->getFirstName());

        $address = Address::of()
            ->setCountry('DE')
            ->setLastName('new-' . $this->getTestRun() . '-lastName');
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerChangeAddressAction::ofAddressIdAndAddress(
                    $customer->getAddresses()->current()->getId(),
                    $address
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertNull($customer->getAddresses()->current()->getFirstName());
        $this->assertSame($address->getLastName(), $customer->getAddresses()->current()->getLastName());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerRemoveAddressAction::ofAddressId($customer->getAddresses()->current()->getId()))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());
        $this->assertCount(0, $customer->getAddresses());
    }

    public function testAddressExternalId()
    {
        $draft = $this->getDraft('external-address-id');
        $customer = $this->createCustomer($draft);

        $externalId = uniqid();
        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName($this->getTestRun() . '-firstName')
            ->setExternalId($externalId);

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());
        $this->assertSame($externalId, $customer->getAddresses()->current()->getExternalId());
    }

    public function testDefaultShippingAddress()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName('new-' . $this->getTestRun() . '-firstName');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerSetDefaultShippingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($address->getFirstName(), $customer->getDefaultShippingAddress()->getFirstName());
    }

    public function testShippingBillingAddressCreate()
    {
        $draft = $this->getDraft('title');
        $draft->setAddresses(AddressCollection::of()->add(Address::of()->setCountry('DE')));
        $draft->setShippingAddresses([0]);
        $draft->setBillingAddresses([0]);
        $customer = $this->createCustomer($draft);

        $this->assertArrayHasKey(0, $customer->getShippingAddressIds());
        $this->assertArrayHasKey(0, $customer->getBillingAddressIds());
    }

    public function testAddShippingAddress()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName('new-' . $this->getTestRun() . '-firstName');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerAddShippingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());
        $this->assertSame(
            $address->getFirstName(),
            $customer->getAddresses()->getById(current($customer->getShippingAddressIds()))->getFirstName()
        );

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerRemoveShippingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertEmpty($customer->getShippingAddressIds());
    }

    public function testAddBillingAddress()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName('new-' . $this->getTestRun() . '-firstName');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerAddBillingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame(
            $address->getFirstName(),
            $customer->getAddresses()->getById(current($customer->getBillingAddressIds()))->getFirstName()
        );

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerRemoveBillingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertEmpty($customer->getBillingAddressIds());
    }

    public function testDefaultBillingAddress()
    {
        $draft = $this->getDraft('title');
        $customer = $this->createCustomer($draft);

        $address = Address::of()
            ->setCountry('DE')
            ->setFirstName('new-' . $this->getTestRun() . '-firstName');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerAddAddressAction::ofAddress($address))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertCount(1, $customer->getAddresses());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerSetDefaultBillingAddressAction::of()->setAddressId(
                    $customer->getAddresses()->current()->getId()
                )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($address->getFirstName(), $customer->getDefaultBillingAddress()->getFirstName());
    }

    public function testCustomerGroup()
    {
        $draft = $this->getDraft('customer-group');
        $customer = $this->createCustomer($draft);

        $customerGroup = $this->getCustomerGroup();

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetCustomerGroupAction::of()->setCustomerGroup($customerGroup->getReference()))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($customerGroup->getId(), $customer->getCustomerGroup()->getId());
    }

    public function testCustomerNumber()
    {
        $draft = $this->getDraft('customer-number');
        $customer = $this->createCustomer($draft);

        $customerNumber = 'new-' . $this->getTestRun() . '-customerNumber';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetCustomerNumberAction::of()->setCustomerNumber($customerNumber))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($customerNumber, $customer->getCustomerNumber());
    }

    public function testExternalId()
    {
        $draft = $this->getDraft('external-id');
        $customer = $this->createCustomer($draft);

        $externalId = 'new-' . $this->getTestRun() . '-externalId';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetExternalIdAction::of()->setExternalId($externalId))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($externalId, $customer->getExternalId());
    }

    public function testCompanyName()
    {
        $draft = $this->getDraft('company-name');
        $customer = $this->createCustomer($draft);

        $companyName = 'new-' . $this->getTestRun() . '-companyName';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetCompanyNameAction::of()->setCompanyName($companyName))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($companyName, $customer->getCompanyName());

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetCompanyNameAction::of()->setCompanyName($companyName))
        ;
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);

        $this->assertSame($customer->getVersion(), $result->getVersion());
        $this->assertSame($customer->getCompanyName(), $result->getCompanyName());
    }

    public function testDateOfBirth()
    {
        $draft = $this->getDraft('date-of-birth');
        $draft->setDateOfBirth(new \DateTime('yesterday'));
        $customer = $this->createCustomer($draft);

        $timezone = date_default_timezone_get();
        date_default_timezone_set('CET');
        $dateOfBirth = new \DateTime('today');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetDateOfBirthAction::of()->setDateOfBirth($dateOfBirth))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertEquals($dateOfBirth, $customer->getDateOfBirth()->getDateTime());
        date_default_timezone_set($timezone);
    }

    public function testVatId()
    {
        $draft = $this->getDraft('vat-id');
        $customer = $this->createCustomer($draft);

        $vatId = 'new-' . $this->getTestRun() . '-vatId';

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetVatIdAction::of()->setVatId($vatId))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertEquals($vatId, $customer->getVatId());
    }

    public function testCustomType()
    {
        $draft = $this->getDraft('custom-type');
        $customer = $this->createCustomer($draft);

        $type = $this->getType('key-' . $this->getTestRun(), 'customer');

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                SetCustomTypeAction::ofTypeKey($type->getKey())
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($type->getId(), $customer->getCustom()->getType()->getId());
    }

    public function testCustomField()
    {
        $typeKey = 'key-' . $this->getTestRun();

        // create custom type for customer resource
        $type = $this->getType($typeKey, 'customer');

        $draft = $this->getDraft('custom-field');
        // add custom type field at customer creation
        $draft->setCustom(
            CustomFieldObjectDraft::ofTypeKeyAndFields($typeKey, FieldContainer::of()->setTestField('value'))
        );
        $customer = $this->createCustomer($draft);

        $this->assertSame('value', $customer->getCustom()->getFields()->getTestField());

        // set custom type and field at customer update
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                SetCustomTypeAction::ofTypeKey($typeKey)
                    ->setFields(
                        FieldContainer::of()
                            ->setTestField('new value')
                    )
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame('new value', $customer->getCustom()->getFields()->getTestField());

        // set custom field only if custom type is already set
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                SetCustomFieldAction::ofName('testField')
                    ->setValue($this->getTestRun())
            )
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);
        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($this->getTestRun(), $customer->getCustom()->getFields()->getTestField());
    }


    public function testSetExternalUserOnCustomerUpdate()
    {
        $draft = $this->getDraft('name');

        $request = CustomerCreateRequest::ofDraft($draft);
        $request->setExternalUserId('custom-external-user-id');

        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);

        $this->cleanupRequests[] = $this->deleteRequest = CustomerDeleteRequest::ofIdAndVersion(
            $result->getCustomer()->getId(),
            $result->getCustomer()->getVersion()
        );
        $customer = $result->getCustomer();

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertInstanceOf(CreatedBy::class, $customer->getCreatedBy());
        $this->assertInstanceOf(LastModifiedBy::class, $customer->getLastModifiedBy());
        $this->assertSame('custom-external-user-id', $customer->getCreatedBy()->getExternalUserId());
        $this->assertSame('custom-external-user-id', $customer->getLastModifiedBy()->getExternalUserId());

        $key = 'new-' . $this->getTestRun();
        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerSetKeyAction::of()->setKey($key)
            )
        ;
        $request->setExternalUserId('another-user');

        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapResponse($response);
        $this->deleteRequest->setVersion($result->getVersion());

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertInstanceOf(CreatedBy::class, $result->getCreatedBy());
        $this->assertInstanceOf(LastModifiedBy::class, $result->getLastModifiedBy());
        $this->assertSame('custom-external-user-id', $result->getCreatedBy()->getExternalUserId());
        $this->assertSame('another-user', $result->getLastModifiedBy()->getExternalUserId());
    }

    public function localeProvider()
    {
        return [
            ['en', 'en'],
            ['de', 'de'],
            ['de-de', 'de-DE'],
            ['de-DE', 'de-DE'],
            ['de_de', 'de-DE'],
            ['de_DE', 'de-DE'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function testLocale($locale, $expectedLocale)
    {
        $draft = $this->getCustomerDraft();
        $customer = $this->createCustomer($draft);

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetLocaleAction::ofLocale($locale))
        ;
        $response = $request->executeWithClient($this->getClient());
        $customer = $request->mapResponse($response);

        $this->deleteRequest->setVersion($customer->getVersion());

        $this->assertSame($expectedLocale, $customer->getLocale());
    }

    public function invalidLocaleProvider()
    {
        return [
            ['en-en'],
            ['en_en'],
            ['en_EN'],
            ['en-EN'],
            ['fr'],
        ];
    }

    /**
     * @dataProvider invalidLocaleProvider
     */
    public function testInvalidLocale($locale)
    {
        $draft = $this->getCustomerDraft();
        $customer = $this->createCustomer($draft);

        $request = CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(CustomerSetLocaleAction::ofLocale($locale))
        ;
        $response = $request->executeWithClient($this->getClient());

        $this->assertTrue($response->isError());
    }

    public function testUpdateInStoreCustomerById()
    {
        $store = $this->getStore();
        $draft = $this->getDraft('in-store-update-by-id');
        $customer = $this->createStoreCustomer($store->getKey(), $draft);

        $firstName = 'test-' . $this->getTestRun() . '-new firstName';
        $request = InStoreRequestDecorator::ofStoreKeyAndRequest(
            $store->getKey(),
            CustomerUpdateRequest::ofIdAndVersion($customer->getId(), $customer->getVersion())
            ->addAction(
                CustomerSetFirstNameAction::of()->setFirstName($firstName)
            )
        );
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapFromResponse($response);
        $this->deleteRequest->setVersion($result->getVersion());

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame($firstName, $result->getFirstName());
    }

    public function testUpdateInStoreCustomerByKey()
    {
        $store = $this->getStore();
        $draft = $this->getDraft('in-store-update-by-key');
        $draft->setKey('test-'. $this->getTestRun());
        $customer = $this->createStoreCustomer($store->getKey(), $draft);

        $firstName = 'test-' . $this->getTestRun() . '-new firstName';
        $request =InStoreRequestDecorator::ofStoreKeyAndRequest(
            $store->getKey(),
            CustomerUpdateByKeyRequest::ofKeyAndVersion($customer->getKey(), $customer->getVersion())
            ->addAction(
                CustomerSetFirstNameAction::of()->setFirstName($firstName)
            )
        );
        $response = $request->executeWithClient($this->getClient());
        $result = $request->mapFromResponse($response);
        $this->deleteRequest->setVersion($result->getVersion());

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame($firstName, $result->getFirstName());
    }

    public function testSetStores()
    {
        $client = $this->getApiClient();

        StoreFixture::withStore(
            $client,
            function (Store $store) use ($client) {
                CustomerFixture::withUpdateableCustomer(
                    $client,
                    function (Customer $customer) use ($client, $store) {
                        $storeReference = StoreReferenceCollection::of()->add($store->getReference());

                        $request = RequestBuilder::of()->customers()->update($customer)
                                ->addAction(
                                    CustomerSetStoresAction::ofStores($storeReference)
                                );
                        $response = $this->execute($client, $request);
                        $result = $request->mapFromResponse($response);

                        $this->assertInstanceOf(Customer::class, $result);
                        $this->assertSame($store->getKey(), $result->getStores()->current()->getKey());
                        $this->assertNotSame($customer->getVersion(), $result->getVersion());

                        return $result;
                    }
                );
            }
        );
    }

    public function testAddStore()
    {
        $client = $this->getApiClient();

        CustomerFixture::withUpdateableCustomer(
            $client,
            function (Customer $customer, Store $store) use ($client) {
                $storeReference = StoreReference::ofKey($store->getKey());

                $request = RequestBuilder::of()->customers()->update($customer)
                        ->addAction(CustomerAddStoreAction::ofStore($storeReference));
                $response = $this->execute($client, $request);
                $customer = $request->mapFromResponse($response);

                $this->assertCount(1, $customer->getStores());

                $request = RequestBuilder::of()->customers()->update($customer)
                    ->addAction(
                        CustomerRemoveStoreAction::of()->setStore($storeReference)
                    );
                $response = $this->execute($client, $request);
                $result = $request->mapFromResponse($response);

                $this->assertEmpty($result->getStores());

                return $result;
            }
        );
    }
}
