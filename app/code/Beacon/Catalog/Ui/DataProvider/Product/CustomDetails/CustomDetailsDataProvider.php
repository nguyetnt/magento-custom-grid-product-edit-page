<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Beacon\Catalog\Ui\DataProvider\Product\CustomDetails;

use Beacon\Catalog\Ui\DataProvider\Product\CustomDetails\AbstractDataProvider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Class CustomDetailsDataProvider
 * @package Beacon\Catalog\Ui\DataProvider\Product\CustomDetails
 */
class CustomDetailsDataProvider extends AbstractDataProvider
{
    /**
     * {@inheritdoc
     * @since 101.0.0
     */
    protected function getLinkType()
    {
        return 'relation';
    }

    /**
     * Add specific filters
     *
     * @param Collection $collection
     * @return Collection
     * @since 101.0.0
     */
    protected function addCollectionFilters(Collection $collection)
    {
        $relatedProducts = [];

        /** @var ProductLinkInterface $linkItem */
        foreach ($this->productLinkRepository->getList($this->getProduct()) as $linkItem) {
            if ($linkItem->getLinkType() !== $this->getLinkType()) {
                continue;
            }

            $relatedProducts[] = $this->productRepository->get($linkItem->getLinkedProductSku())->getId();
        }

        if ($relatedProducts) {
            $collection->addAttributeToFilter(
                $collection->getIdFieldName(),
                ['nin' => [$relatedProducts]]
            );
        }

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('go here!');
        $logger->info(get_class($collection));
        $collection->addAttributeToFilter('tier_price');
        $collection->addAttributeToFilter('final_price');
        $collection->addPriceData(3, 1);
        /*$collection->addTierPriceDataByGroupId(3);*/

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($collection->getSelect()->__toString());

        return $collection;
    }
}
