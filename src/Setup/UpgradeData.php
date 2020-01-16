<?php
/**
 *
 * ShipperHQ Shipping Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * Shipper HQ Shipping
 *
 * @category ShipperHQ
 * @package ShipperHQ_Shipping_Carrier
 * @copyright Copyright (c) 2015 Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ShipperHQ\Shipper\Setup;

use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * Category setup factory
     *
     * @var CategorySetupFactory
     */
    protected $categorySetupFactory;

    /**
     * Quote setup factory
     *
     * @var QuoteSetupFactory
     */
    protected $quoteSetupFactory;

    /**
     * Sales setup factory
     *
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * Customer setup factory
     *
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * \Magento\Framework\App\ProductMetadata
     */
    private $productMetadata;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    private $configStorageWriter;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @param CategorySetupFactory $categorySetupFactory
     * @param QuoteSetupFactory $quoteSetupFactory
     * @param SalesSetupFactory $salesSetupFactory
     * @param CustomerSetupFactory $customerSetupFactory
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configStorageWriter
     */
    public function __construct(
        CategorySetupFactory $categorySetupFactory,
        QuoteSetupFactory $quoteSetupFactory,
        SalesSetupFactory $salesSetupFactory,
        CustomerSetupFactory $customerSetupFactory,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Framework\App\Config\Storage\WriterInterface $configStorageWriter,
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->categorySetupFactory = $categorySetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->salesSetupFactory = $salesSetupFactory;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->productMetadata = $productMetadata;
        $this->configStorageWriter = $configStorageWriter;
        $this->attributeCollectionFactory = $attributeCollectionFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(AttributeCollectionFactory::class);
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        $catalogSetup = $this->categorySetupFactory->create(['setup' => $setup]);

        //if less than 1.0.1 then install attributes
        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->installAttributes($catalogSetup);
        }
        //v 1.0.3
        if (version_compare($context->getVersion(), '1.0.3') < 0) {
            $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_availability_date', [
                'type' => 'datetime',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                'input' => 'date',
                'label' => 'Availability Date',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'visible_on_front' => false,
                'is_html_allowed_on_front' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'is_configurable' => false,
                'unique' => false,
                'user_defined' => true,
                'used_in_product_listing' => false
            ]);
        }
        /** @var \Magento\Quote\Setup\QuoteSetup $quoteSetup */
        $quoteSetup = $this->quoteSetupFactory->create(['setup' => $setup]);
        $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $custDestTypeAttribute = $customerSetup->getAttribute('customer_address', 'destination_type');

        if (version_compare($context->getVersion(), '1.0.5') < 0 || !$custDestTypeAttribute) {
            $destinationTypeAttr = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'visible' => false,
                'required' => false,
                'comment' => 'ShipperHQ Address Type'
            ];
            $quoteSetup->addAttribute('quote_address', 'destination_type', $destinationTypeAttr);
            $salesSetup->addAttribute('order', 'destination_type', $destinationTypeAttr);
            $destinationTypeAddressAttr = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'label' => 'Address Type',
                'input' => 'select',
                'source' => 'ShipperHQ\Shipper\Model\Customer\Attribute\Source\AddressType',
                'system' => 0, // <-- important, otherwise values aren't saved.
                // @see Magento\Customer\Model\Metadata\AddressMetadata::getCustomAttributesMetadata()
                //            'visible' => false,
                'required' => false,
                'position' => 100,
                'comment' => 'ShipperHQ Address Type'
            ];
            $customerSetup->addAttribute('customer_address', 'destination_type', $destinationTypeAddressAttr);

            $addressValiationStatus = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'visible' => false,
                'required' => false,
                'comment' => 'ShipperHQ Address Validation Status'
            ];
            $quoteSetup->addAttribute('quote_address', 'validation_status', $addressValiationStatus);
            $salesSetup->addAttribute('order', 'validation_status', $addressValiationStatus);

            $validationStatusAddressAttr = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'label' => 'Address Validation',
                'system' => 0, // <-- important, otherwise values aren't saved.
                // @see Magento\Customer\Model\Metadata\AddressMetadata::getCustomAttributesMetadata()
                //            'visible' => false,
                'required' => false,
                'position' => 101,
                'comment' => 'ShipperHQ Address Validation Status'
            ];
            $customerSetup->addAttribute('customer_address', 'validation_status', $validationStatusAddressAttr);

            // add attribute to form
            /** @var  $attribute */
            $attribute = $customerSetup->getEavConfig()->getAttribute('customer_address', 'validation_status');
            $attribute->setData('used_in_forms', ['adminhtml_customer_address']);
            $attribute->save();

            $attribute = $customerSetup->getEavConfig()->getAttribute('customer_address', 'destination_type');
            $attribute->setData('used_in_forms', ['adminhtml_customer_address']);
            $attribute->save();
        }

        //1.0.7
        if (version_compare($context->getVersion(), '1.0.7') < 0) {
            $dispatchDateAttr = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATE,
                'visible' => false,
                'required' => false,
                'comment' => 'ShipperHQ Address Type'
            ];
            $quoteSetup->addAttribute('quote_address_rate', 'shq_dispatch_date', $dispatchDateAttr);
            $deliveryDateAttr = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATE,
                'visible' => false,
                'required' => false,
                'comment' => 'ShipperHQ Address Type'
            ];
            $quoteSetup->addAttribute('quote_address_rate', 'shq_delivery_date', $deliveryDateAttr);
        }

        //1.0.12
        if (version_compare($context->getVersion(), '1.0.12') < 0) {
            $this->installFreightAttributes($catalogSetup);
        }

        //1.0.16
        if (version_compare($context->getVersion(), '1.0.16') < 0) {
            $customerSetup->updateAttribute(
                'customer_address',
                'destination_type',
                [
                    'source_model' => 'ShipperHQ\Shipper\Model\Customer\Attribute\Source\AddressType',
                    'frontend_input' => 'select'
                ]
            );
        }

        //1.1.17
        if (version_compare($context->getVersion(), '1.1.17') < 0) {
            $catalogSetup->updateAttribute(
                'catalog_product',
                'must_ship_freight',
                ['note' => 'Can be overridden at Carrier level within ShipperHQ']
            );
        }

        if (version_compare($context->getVersion(), '1.1.19') < 0) {
            $this->configStorageWriter->save('carriers/shipper/magento_version', $this->productMetadata->getVersion());
        }

        if (version_compare($context->getVersion(), '1.1.21') < 0) {
            $this->installCrossBorderAttributes($catalogSetup);
        }

        $installer->endSetup();
    }

    private function installAttributes($catalogSetup)
    {
        /* ------ shipperhq_shipping_fee -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_shipping_fee', [
            'type' => 'decimal',
            'backend' => 'Magento\Catalog\Model\Product\Attribute\Backend\Price',
            'input' => 'price',
            'label' => 'Shipping Fee',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ shipperhq_handling_fee -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_handling_fee', [
            'type' => 'decimal',
            'backend' => 'Magento\Catalog\Model\Product\Attribute\Backend\Price',
            'input' => 'price',
            'label' => 'Handling Fee',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ shipperhq_volume_weight -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_volume_weight', [
            'type' => 'varchar',
            'input' => 'text',
            'label' => 'Volume Weight',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false,
            'note' => 'This value is only used in conjunction with shipping filters'
        ]);

        /* ------ shipperhq_declared_value -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_declared_value', [
            'type' => 'decimal',
            'backend' => 'Magento\Catalog\Model\Product\Attribute\Backend\Price',
            'input' => 'price',
            'label' => 'Declared Value',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false,
            'note' => 'The deemed cost of this product for customs & insurance purposes'
        ]);

        /* ------ ship_separately -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'ship_separately', [
            'type' => 'int',
            'input' => 'boolean',
            'label' => 'Ship Separately',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ shipperhq_dim_group -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_dim_group', [
            'type' => 'int',
            'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
            'frontend' => '',
            'label' => 'ShipperHQ Dimensional Rule Group',
            'input' => 'select',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);
        /* ------ ship_length -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'ship_length', [
            'type' => 'decimal',
            'input' => 'text',
            'label' => 'Dimension Length',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ ship_width -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'ship_width', [
            'type' => 'decimal',
            'input' => 'text',
            'label' => 'Dimension Width',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ ship_height -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'ship_height', [
            'type' => 'decimal',
            'input' => 'text',
            'label' => 'Dimension Height',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ shipperhq_poss_boxes -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_poss_boxes', [
            'type' => 'text',
            'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
            'input' => 'multiselect',
            'label' => 'Possible Packing Boxes',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        /* ------ shipperhq_malleable_product -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_malleable_product', [
            'type' => 'int',
            'input' => 'boolean',
            'label' => 'Malleable Product',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false,
            'note' => 'Ignore if unsure. Indicates the product dimensions can be adjusted to fit box',
        ]);

        /* ------ shipperhq_master_boxes -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_master_boxes', [
            'type' => 'text',
            'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
            'input' => 'multiselect',
            'label' => 'Master Packing Boxes',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        $entityTypeId = $catalogSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);

        $attributeSetArr = $catalogSetup->getAllAttributeSetIds($entityTypeId);

        $dimAttributeCodes = [
            'ship_separately' => '2',
            'shipperhq_dim_group' => '1',
            'ship_length' => '10',
            'ship_width' => '11',
            'ship_height' => '12',
            'shipperhq_poss_boxes' => '20'
        ];

        foreach ($attributeSetArr as $attributeSetId) {
            //SHQ16-2123 handle migrated instances from M1 to M2
            $migrated = $catalogSetup->getAttributeGroup($entityTypeId, $attributeSetId, 'migration-dimensional-shipping');
            $existingDimAttributeIds = [];

            if ($migrated !== false) {
                $existingDimAttributeIds = $this->getNonShqAttributeIds($catalogSetup, 'migration-dimensional-shipping', $attributeSetId);
                $catalogSetup->removeAttributeGroup($entityTypeId, $attributeSetId, 'migration-dimensional-shipping');
            }

            $attributeGroupId = $catalogSetup->getAttributeGroup(
                $entityTypeId,
                $attributeSetId,
                'Dimensional Shipping'
            );

            if (!$attributeGroupId) {
                $catalogSetup->addAttributeGroup($entityTypeId, $attributeSetId, 'Dimensional Shipping', '100');
            }

            $attributeGroupId = $catalogSetup->getAttributeGroupId(
                $entityTypeId,
                $attributeSetId,
                'Dimensional Shipping'
            );

            $ourDimAttributeIds = [];

            foreach ($dimAttributeCodes as $code => $sort) {
                $attributeId = $catalogSetup->getAttributeId($entityTypeId, $code);
                $ourDimAttributeIds[] = $attributeId;
                $catalogSetup->addAttributeToGroup(
                    $entityTypeId,
                    $attributeSetId,
                    $attributeGroupId,
                    $attributeId,
                    $sort
                );
            }

            // SHQ18-2825 Add any attributes that were in migration-dimensional-shipping that were not our attributes back
            if (count($existingDimAttributeIds)) {
                $attributeIdsToAdd = array_diff($existingDimAttributeIds, $ourDimAttributeIds);

                foreach ($attributeIdsToAdd as $attributeId) {
                    $catalogSetup->addAttributeToGroup(
                        $entityTypeId,
                        $attributeSetId,
                        $attributeGroupId,
                        $attributeId,
                        10
                    );
                }
            }
        };
    }

    private function installFreightAttributes($catalogSetup)
    {
        /* ------ freight_class -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'freight_class', [
            'type' => 'int',
            'source' => 'ShipperHQ\Shipper\Model\Product\Attribute\Source\FreightClass',
            'input' => 'select',
            'label' => 'Freight Class',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);
        /* ------ shipperhq_nmfc_class -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_nmfc_class', [
            'type' => 'text',
            'input' => 'text',
            'label' => 'NMFC',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);
        /* ------ must_ship_freight -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'must_ship_freight', [
            'type' => 'int',
            'input' => 'boolean',
            'label' => 'Must Ship Freight',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false,
            'note' => 'Can be overridden at Carrier level within ShipperHQ'
        ]);
        /* ------ shipperhq_nmfc_sub -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_nmfc_sub', [
            'type' => 'text',
            'input' => 'text',
            'label' => 'NMFC Sub',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false,
            'note' => 'Only required to support ABF Freight'
        ]);

        $entityTypeId = $catalogSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);

        $attributeSetArr = $catalogSetup->getAllAttributeSetIds($entityTypeId);

        $freightAttributeCodes = [
            'freight_class' => '1',
            'must_ship_freight' => '10'
        ];

        foreach ($attributeSetArr as $attributeSetId) {
            //SHQ16-2123 handle migrated instances from M1 to M2
            $migrated = $catalogSetup->getAttributeGroup($entityTypeId, $attributeSetId, 'migration-freight-shipping');
            $existingFreightAttributeIds = [];

            if ($migrated !== false) {
                $existingFreightAttributeIds = $this->getNonShqAttributeIds($catalogSetup, 'migration-freight-shipping',$attributeSetId);
                $catalogSetup->removeAttributeGroup($entityTypeId, $attributeSetId, 'migration-freight-shipping');
            }

            $attributeGroupId = $catalogSetup->getAttributeGroup(
                $entityTypeId,
                $attributeSetId,
                'Freight Shipping'
            );

            if (!$attributeGroupId) {
                $catalogSetup->addAttributeGroup($entityTypeId, $attributeSetId, 'Freight Shipping', '101');
            }

            $attributeGroupId = $catalogSetup->getAttributeGroupId(
                $entityTypeId,
                $attributeSetId,
                'Freight Shipping'
            );

            $ourFreightAttributeIds = [];

            foreach ($freightAttributeCodes as $code => $sort) {
                $attributeId = $catalogSetup->getAttributeId($entityTypeId, $code);
                $ourFreightAttributeIds[] = $attributeId;
                $catalogSetup->addAttributeToGroup(
                    $entityTypeId,
                    $attributeSetId,
                    $attributeGroupId,
                    $attributeId,
                    $sort
                );
            }

            // SHQ18-2825 Add any attributes that were in migration-freight-shipping that were not our attributes back
            if (count($existingFreightAttributeIds)) {
                $attributeIdsToAdd = array_diff($existingFreightAttributeIds, $ourFreightAttributeIds);

                foreach ($attributeIdsToAdd as $attributeId) {
                    $catalogSetup->addAttributeToGroup(
                        $entityTypeId,
                        $attributeSetId,
                        $attributeGroupId,
                        $attributeId,
                        10
                    );
                }
            }
        };
    }

    private function installCrossBorderAttributes($catalogSetup)
    {
        /* ------ shq_hs_code -------- */
        $catalogSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'shipperhq_hs_code', [
            'type' => 'text',
            'input' => 'text',
            'label' => 'HS Code',
            'global' => false,
            'visible' => true,
            'required' => false,
            'visible_on_front' => false,
            'is_html_allowed_on_front' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'is_configurable' => false,
            'unique' => false,
            'user_defined' => true,
            'used_in_product_listing' => false
        ]);

        $entityTypeId = $catalogSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);

        $attributeSetArr = $catalogSetup->getAllAttributeSetIds($entityTypeId);

        $crossAttributeCodes = [
            'shipperhq_hs_code' => '25',
        ];

        foreach ($attributeSetArr as $attributeSetId) {

            $attributeGroupId = $catalogSetup->getAttributeGroup(
                $entityTypeId,
                $attributeSetId,
                'Shipping'
            );

            if (!$attributeGroupId) {
                $catalogSetup->addAttributeGroup($entityTypeId, $attributeSetId, 'Shipping', '99');
            }

            $attributeGroupId = $catalogSetup->getAttributeGroupId(
                $entityTypeId,
                $attributeSetId,
                'Shipping'
            );

            foreach ($crossAttributeCodes as $code => $sort) {
                $attributeId = $catalogSetup->getAttributeId($entityTypeId, $code);
                $catalogSetup->addAttributeToGroup(
                    $entityTypeId,
                    $attributeSetId,
                    $attributeGroupId,
                    $attributeId,
                    $sort
                );
            }
        };
    }

    /**
     * SHQ18-2825 Gets all attribute IDs for a given attribute group
     *
     * @param $attributeGroupName
     * @param $attributeSetId
     *
     * @return array
     */
    private function getNonShqAttributeIds($catalogSetup, $attributeGroupName, $attributeSetId)
    {
        $entityTypeId = $catalogSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);

        $attributeGroupId = $catalogSetup->getAttributeGroupId(
            $entityTypeId,
            $attributeSetId,
            $attributeGroupName
        );

        $collection = $this->attributeCollectionFactory->create();
        $collection->setAttributeGroupFilter($attributeGroupId);

        $allAttributeIds = [];

        foreach ($collection->getItems() as $attribute) {
            $allAttributeIds[] = $attribute->getAttributeId();
        }

        return $allAttributeIds;
    }
}
