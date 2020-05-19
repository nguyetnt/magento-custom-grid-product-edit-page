<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Beacon\Catalog\Ui\DataProvider\Product;

use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Catalog\Model\ResourceModel\Category;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\CatalogUrlRewrite\Model\Storage\DbStorage;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;

/**
 * Collection which is used for rendering product list in the backend.
 *
 * Used for product grid and customizes behavior of the default Product collection for grid needs.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ProductCollection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * Add attribute filter to collection
     *
     * @param AttributeInterface|integer|string|array $attribute
     * @param null|string|array $condition
     * @param string $joinType
     * @return $this
     * @throws LocalizedException
     */
    public function addAttributeToFilter($attribute, $condition = null, $joinType = 'inner')
    {
        $storeId = (int)$this->getStoreId();
        if ($attribute === 'is_saleable'
            || is_array($attribute)
            || $storeId !== $this->getDefaultStoreId()
        ) {
            return parent::addAttributeToFilter($attribute, $condition, $joinType);
        }

        if (is_string($attribute) && $attribute == 'tier_price') {
            $this->addTierPriceAttributeToFilter($attribute, $condition);
        }

        if ($attribute instanceof AttributeInterface) {
            $attributeModel = $attribute;
        } else {
            $attributeModel = $this->getEntity()->getAttribute($attribute);
            if ($attributeModel === false) {
                throw new LocalizedException(
                    __('Invalid attribute identifier for filter (%1)', get_class($attribute))
                );
            }
        }

        if ($attributeModel->isScopeGlobal() || $attributeModel->getBackend()->isStatic()) {
            return parent::addAttributeToFilter($attribute, $condition, $joinType);
        }

        $this->addAttributeToFilterAllStores($attributeModel, $condition);

        return $this;
    }

    /**
     * Add tier price attribute to filter
     *
     * @param string $attribute
     * @param mixed $condition
     * @return $this
     */
    private function addTierPriceAttributeToFilter(string $attribute, $condition): self
    {
        $attrCode = $attribute;
        $connection = $this->getConnection();
        $attrTable = $this->_getAttributeTableAlias($attrCode);
        $entity = $this->getEntity();
        $fKey = 'e.' . $this->getEntityPkName($entity);
        $pKey = $attrTable . '.' . $this->getEntityPkName($entity);
        $attribute = $entity->getAttribute($attrCode);
        $attrFieldName = $attrTable . '.value';
        $fKey = $connection->quoteColumnAs($fKey, null);
        $pKey = $connection->quoteColumnAs($pKey, null);

        $condArr = ["{$pKey} = {$fKey}"];
        $this->getSelect()->join(
            [$attrTable => $this->getTable('catalog_product_entity_tier_price')],
            '(' . implode(') AND (', $condArr) . ')',
            [$attrCode => $attrFieldName]
        );
        $this->removeAttributeToSelect($attrCode);
        $this->_filterAttributes[$attrCode] = $attribute->getId();
        $this->_joinFields[$attrCode] = ['table' => '', 'field' => $attrFieldName];
        $field = $this->_getAttributeTableAlias($attrCode) . '.value';
        $conditionSql = $this->_getConditionSql($field, $condition);
        $this->getSelect()->where($conditionSql, null, Select::TYPE_CONDITION);
        $this->_totalRecords = null;

        return $this;
    }

    /**
     * Add attribute to filter by all stores
     *
     * @param Attribute $attributeModel
     * @param array $condition
     * @return void
     */
    private function addAttributeToFilterAllStores(Attribute $attributeModel, array $condition): void
    {
        $tableName = $this->getTable($attributeModel->getBackendTable());
        $entity = $this->getEntity();
        $fKey = 'e.' . $this->getEntityPkName($entity);
        $pKey = $tableName . '.' . $this->getEntityPkName($entity);
        $attributeId = $attributeModel->getAttributeId();
        $condition = "({$pKey} = {$fKey}) AND ("
            . $this->_getConditionSql("{$tableName}.value", $condition)
            . ') AND ('
            . $this->_getConditionSql("{$tableName}.attribute_id", $attributeId)
            . ')';
        $selectExistsInAllStores = $this->getConnection()->select()->from($tableName);
        $this->getSelect()->exists($selectExistsInAllStores, $condition);
    }
}
