<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\AddressInterface;
use PostDirekt\Addressfactory\Test\Integration\Fixture\Data\ProductInterface;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;

/**
 * Class OrderFixture
 *
 * @author  Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class OrderFixture
{
    private static $createdEntities = [
        'products' => [],
        'customers' => [],
        'orders' => [],
        'selections' => [],
    ];

    /**
     * Creates a single order
     *
     * @param AddressInterface $recipientData
     * @param ProductInterface[] $productData
     * @param string $shippingMethod
     * @return OrderInterface
     * @throws \Exception
     */
    public static function createOrder(
        AddressInterface $recipientData,
        array $productData,
        string $shippingMethod
    ): OrderInterface {

        // set up logged-in customer
        $addressBuilder = AddressBuilder::anAddress()
                                        ->withFirstname('François')
                                        ->withLastname('Češković')
                                        ->withCompany(null)
                                        ->withCountryId($recipientData->getCountryId())
                                        ->withRegionId($recipientData->getRegionId())
                                        ->withCity($recipientData->getCity())
                                        ->withPostcode($recipientData->getPostcode())
                                        ->withStreet($recipientData->getStreet());

        $customer = CustomerBuilder::aCustomer()
                                   ->withFirstname('François')
                                   ->withLastname('Češković')
                                   ->withAddresses(
                                       $addressBuilder->asDefaultBilling(),
                                       $addressBuilder->asDefaultShipping()
                                   )
                                   ->build();

        self::$createdEntities['customers'][] = $customer;
        $customerFixture = new CustomerFixture($customer);
        $customerFixture->login();

        // place order
        $cartBuilder = CartBuilder::forCurrentSession();
        $cartBuilder = self::addProductsToCart($productData, $cartBuilder);
        $cart = $cartBuilder->build();
        $checkout = CustomerCheckout::fromCart($cart);

        $order = $checkout
            ->withShippingMethodCode($shippingMethod)
            ->placeOrder();

        self::$createdEntities['orders'][] = $order;

        $cart->getCheckoutSession()->clearQuote();

        return $order;
    }


    /**
     * Rollback for created order, customer and product entities
     *
     * @throws LocalizedException
     * @throws StateException
     */
    public static function rollbackFixtureEntities(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /**
         * this is needed otherwise delete operations will possible not work.
         * see: Magento\Framework\Model\AbstractModel::beforeDelete
         */
        /** @var \Magento\Framework\Registry $registry */
        $registry = $objectManager->get(\Magento\Framework\Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        /** @var OrderInterface $order */
        foreach (self::$createdEntities['orders'] as $order) {
            /** @var OrderRepositoryInterface $orderRepo */
            $orderRepo = $objectManager->get(OrderRepositoryInterface::class);
            $orderRepo->delete($order);
        }
        self::$createdEntities['orders'] = [];

        /** @var CustomerInterface $customer */
        foreach (self::$createdEntities['customers'] as $customer) {
            /** @var CustomerRepositoryInterface $customerRepo */
            $customerRepo = $objectManager->get(CustomerRepositoryInterface::class);
            $customerRepo->delete($customer);
        }
        self::$createdEntities['customers'] = [];

        /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
        foreach (self::$createdEntities['products'] as $product) {
            /** @var ProductRepositoryInterface $productRepo */
            $productRepo = $objectManager->get(ProductRepositoryInterface::class);
            $productRepo->delete($product);
        }
        self::$createdEntities['products'] = [];
        $registry->unregister('isSecureArea');
    }

    /**
     * @param ProductInterface[] $productData
     * @param CartBuilder $cartBuilder
     * @return CartBuilder
     * @throws \Exception
     */
    private static function addProductsToCart(array $productData, CartBuilder $cartBuilder): CartBuilder
    {
        foreach ($productData as $productDatum) {
            if ($productDatum->getType() === Type::TYPE_SIMPLE) {
                // set up product
                $productBuilder = ProductBuilder::aSimpleProduct();
                $productBuilder = $productBuilder
                    ->withSku($productDatum->getSku())
                    ->withPrice($productDatum->getPrice())
                    ->withWeight($productDatum->getWeight())
                    ->withName($productDatum->getDescription());
                $product = $productBuilder->build();

                self::$createdEntities['products'][] = $product;
                $productFixture = new ProductFixture($product);
                $cartBuilder = $cartBuilder->withSimpleProduct(
                    $productFixture->getSku(),
                    $productDatum->getCheckoutQty()
                );
            } else {
                throw new \InvalidArgumentException('Only simple product data fixtures are currently supported.');
            }
        }

        return $cartBuilder;
    }
}