<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0).
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category   RicoNeitzel
 * @package    RicoNeitzel_VertNav
 * @copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com/
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Catalog vertical navigation
 *
 * @category   RicoNeitzel
 * @package    RicoNeitzel_VertNav
 * @author     Vinai Kopp <vinai@netzarbeiter.com>
 */
class RicoNeitzel_VertNav_Block_Navigation extends Mage_Catalog_Block_Navigation
{
    protected $_storeCategories;

    /**
     * Add the customer group to the cache key so this module is compatible with more extensions.
     * Netzarbeiter_GroupsCatalog
     * Netzarbeiter_LoginCatalog
     * Also add the current product and current cms page id if they exist if
     * this block has been added to a cms or product detail page.
     * @return string
     */
    public function getCacheKey()
    {
        $key = parent::getCacheKey();
        $customerGroupId = $this->_getCustomerGroupId();
        $productId = Mage::registry('current_product') ? Mage::registry('current_product')->getId() : 0;
        $cmsPageId = Mage::app()->getRequest()->getParam(
            'page_id', Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE
        ));

        return 'VERTNAV_' . $key . '_' . $customerGroupId . '_' . $productId . '_' . $cmsPageId;
    }

    /**
     * check if we should hide the categories because of Netzarbeiter_LoginCatalog
     * @return boolean
     */
    protected function _checkLoginCatalog()
    {
        return $this->_isLoginCatalogInstalledAndActive() && $this->_loginCatalogHideCategories();
    }

    /**
     * Check if the Netzarbeiter_LoginCatalog extension is installed and active
     * @return boolean
     */
    protected function _isLoginCatalogInstalledAndActive()
    {
        if ($node = Mage::getConfig()->getNode('modules/Netzarbeiter_LoginCatalog')) {
            return strval($node->active) == 'true';
        }
        return false;
    }

    /**
     * Check if the Netzarbeiter_LoginCatalog extension is configured to hide categories from logged out customers
     * @return boolean
     */
    protected function _loginCatalogHideCategories()
    {
        if (
            !Mage::getSingleton('customer/session')->isLoggedIn() &&
            Mage::helper('logincatalog')->moduleActive() &&
            Mage::helper('logincatalog')->getConfig('hide_categories')
        ) {
            return true;
        }
        return false;
    }

    /**
     * This method is only here to provide compatibility with the Netzarbeiter_LoginCatalog extension
     *
     * @param Varien_Data_Tree_Node $category
     * @param int                   $level
     * @param bool                  $last
     *
     * @return string
     */
    public function drawItem($category, $level = 0, $last = false)
    {
        if ($this->_checkLoginCatalog()) {
            return '';
        }
        return parent::drawItem($category, $level, $last);
    }

    /**
     * Add project specific formatting
     *
     * @param Mage_Model_Catalog_Category $category
     * @param integer                     $level
     * @param array                       $levelClass
     *
     * @return string
     */
    public function drawOpenCategoryItem($category, $level = 0, array $levelClass = NULL)
    {
        $html = array();

        if ($this->_checkLoginCatalog()) {
            return '';
        }
        if (!$category->getIsActive()) {
            return '';
        }
        if (!$category->getIncludeInMenu()) {
            return '';
        }

        if (!isset($levelClass)) {
            $levelClass = array();
        }
        $combineClasses = array();

        $combineClasses[] = 'level' . $level;
        if ($this->_isCurrentCategory($category)) {
            $combineClasses[] = 'active';
        } else {
            $combineClasses[] = $this->isCategoryActive($category) ? 'parent' : 'inactive';
        }
        $levelClass[] = implode('-', $combineClasses);

        if ($category->hasChildren()) {
            $levelClass[] = 'has-children';
        }

        $levelClass = array_merge($levelClass, $combineClasses);

        $levelClass[] = $this->_getClassNameFromCategoryName($category);

        $productCount = '';
        if ($this->displayProductCount()) {
            $num = Mage::getModel('catalog/layer')
                ->setCurrentCategory($category->getID())->getProductCollection()->getSize();
            $productCount = '<span class="product-count"> (' . $num . ')</span>';
        }

        // indent HTML!
        $html[1] = str_pad("", (($level * 2) + 4), " ") .
            '<span class="vertnav-cat"><a href="' .
            $this->getCategoryUrl($category) .
            '"><span>' .
            $this->htmlEscape($category->getName()) .
            '</span></a>' . $productCount . "</span>\n";

        $autoMaxDepth = Mage::getStoreConfig('catalog/vertnav/expand_all_max_depth');
        $autoExpand = Mage::getStoreConfig('catalog/vertnav/expand_all');

        if (
            in_array($category->getId(), $this->getCurrentCategoryPath()) ||
            ($autoExpand && $autoMaxDepth == 0) ||
            ($autoExpand && $autoMaxDepth > $level + 1)
        ) {
            $children = $this->_getCategoryCollection()->addIdFilter($category->getChildren());

            $children = $this->toLinearArray($children);

            //usort($children, array($this, '_sortCategoryArrayByName'));

            $hasChildren = $children && ($childrenCount = count($children));
            if ($hasChildren) {
                $children = $this->toLinearArray($children);
                $htmlChildren = '';

                foreach ($children as $i => $child) {
                    $class = array();
                    if ($childrenCount == 1) {
                        $class[] = 'only';
                    } else {
                        if (!$i) {
                            $class[] = 'first';
                        }
                        if ($i == $childrenCount - 1) {
                            $class[] = 'last';
                        }
                    }
                    if (isset($children[$i + 1]) && $this->isCategoryActive($children[$i + 1])) {
                        $class[] = 'prev';
                    }
                    if (isset($children[$i - 1]) && $this->isCategoryActive($children[$i - 1])) {
                        $class[] = 'next';
                    }
                    $htmlChildren .= $this->drawOpenCategoryItem($child, $level + 1, $class);
                }

                if (!empty($htmlChildren)) {
                    $levelClass[] = 'open';

                    // indent HTML!
                    $html[2] = str_pad("", ($level * 2) + 2, " ") .
                        '<ul>' . "\n" . $htmlChildren . "\n" .
                        str_pad("", ($level * 2) + 2, " ") . '</ul>';
                }
            }
        }

        // indent HTML!
        $html[0] = str_pad("", ($level * 2) + 2, " ") . sprintf('<li class="%s">', implode(" ", $levelClass)) . "\n";

        // indent HTML!
        $html[3] = "\n" . str_pad("", ($level * 2) + 2, " ") . '</li>' . "\n";

        ksort($html);
        return implode('', $html);
    }

    /**
     * I need an array with the index being continunig numbers, so
     * it's possible to check for the previous/next category
     *
     * @param mixed $collection
     *
     * @return array
     */
    public function toLinearArray($collection)
    {
        $array = array();
        foreach ($collection as $item) {
            $array[] = $item;
        }
        return $array;
    }

    /**
     * Sorting Method
     *
     * @param Mage_Catalog_Model_Category $arg1
     * @param Mage_Catalog_Model_Category $arg2
     *
     * @return int
     * @deprecated
     */
    protected function _sortCategoryArrayByName($arg1, $arg2)
    {
        return strcoll($arg1->getName(), $arg2->getName());
    }

    /**
     * Convert the category name into a string that can be used as a css class
     *
     * @param Mage_Catalog_Model_Category $category
     *
     * @return string
     */
    protected function _getClassNameFromCategoryName($category)
    {
        $name = $category->getName();
        $name = preg_replace('/-{2,}/', '-', preg_replace('/[^a-z-]/', '-', strtolower($name)));
        $name = trim($name, '-');
        return $name;
    }

    /**
     * Return the current customer group id. Logged out customers get the group id 0,
     * not the default set in system > config > customers
     * @return integer
     */
    protected function _getCustomerGroupId()
    {
        $session = Mage::getSingleton('customer/session');
        if (!$session->isLoggedIn()) {
            $customerGroupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        } else {
            $customerGroupId = $session->getCustomerGroupId();
        }
        return $customerGroupId;
    }

    /**
     * Check if the current category matches the passed in category
     *
     * @param Mage_Catalog_Model_Category $category
     *
     * @return bool
     */
    protected function _isCurrentCategory($category)
    {
        return ($cat = $this->getCurrentCategory()) && $cat->getId() == $category->getId();
    }

    /**
     * Return the number of products assigned to the category
     *
     * @param Mage_Catalog_Model_Category|Varien_Data_Tree_Node $category
     *
     * @return int
     */
    protected function _getProductCount($category)
    {
        if (null === ($count = $category->getData('product_count'))) {
            $count = 0;
            if ($category instanceof Mage_Catalog_Model_Category) {
                $count = $category->getProductCount();
            } elseif ($category instanceof Varien_Data_Tree_Node) {
                $count = $this->_getProductCountFromTreeNode($category);
            }
        }
        return $count;
    }

    /**
     * Get the number of products from a category tree node
     *
     * @param Varien_Data_Tree_Node $category
     *
     * @return int
     */
    protected function _getProductCountFromTreeNode(Varien_Data_Tree_Node $category)
    {
        return Mage::getSingleton('catalog/category')->setId($category->getId())->getProductCount();
    }

    /**
     * Get catagories of current store, using the max depth setting for the vertical navigation
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection
     */
    public function getStoreCategories()
    {
        if (isset($this->_storeCategories)) {
            return $this->_storeCategories;
        }

        /* @var $category Mage_Catalog_Model_Category */
        $category = Mage::getModel('catalog/category');

        /*
         * Set Category Object if Product is requested without category path in URI (top level request).
         * Takes first Category of a Product as Category Object
         */

        if (
            true == (bool)Mage::getStoreConfig('catalog/vertnav/show_cat_on_toplevel') &&
            false == Mage::registry('current_category') && false != Mage::registry('current_product')
        ) {
            $productCategories = Mage::registry('current_product')->getCategoryIds();
            if (count($productCategories) > 0) {
                $newActiveCategory = Mage::getModel('catalog/category')->load($productCategories[0]);
                Mage::register('current_category', $newActiveCategory);
            }
        }

        $parent = false;
        switch (Mage::getStoreConfig('catalog/vertnav/vertnav_root')) {
            case 'current':
                if (Mage::registry('current_category')) {
                    $parent = Mage::registry('current_category')->getId();
                }
                break;
            case 'siblings':
                if (Mage::registry('current_category')) {
                    $parent = Mage::registry('current_category')->getParentId();
                }
                break;
            case 'root':
                $parent = Mage::app()->getStore()->getRootCategoryId();
                break;
            default:
                /*
                 * Display from level N
                 */
                $fromLevel = Mage::getStoreConfig('catalog/vertnav/vertnav_root');
                if (
                    Mage::registry('current_category') &&
                    Mage::registry('current_category')->getLevel() >= $fromLevel
                ) {
                    $cat = Mage::registry('current_category');
                    while ($cat->getLevel() > $fromLevel) {
                        $cat = $cat->getParentCategory();
                    }
                    $parent = $cat->getId();
                }
        }

        /**
         * Thanks to thebod for this patch!
         * It enables the setting of the category ID to use via Layout XML:
         * <reference name="catalog.vertnav">
         *    <action method="setCategoryId"><category_id>8</category_id></action>
         * </reference>
         */
        if ($customId = $this->getCategoryId()) {
            $parent = $customId;
        }

        if (!$parent && Mage::getStoreConfig('catalog/vertnav/fallback_to_root')) {
            $parent = Mage::app()->getStore()->getRootCategoryId();
        }

        /**
         * Check if parent node of the store still exists
         */
        if (!$parent || !$category->checkId($parent)) {
            return array();
        }
        $storeCategories = $this->_getCategoryCollection()->addFieldToFilter('parent_id', $parent);

        $this->_storeCategories = $storeCategories;
        return $storeCategories;
    }

    /**
     * $childrenIdString is a comma seperated list of category IDs
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection
     */
    protected function _getCategoryCollection()
    {
        $collection = Mage::getResourceModel('catalog/category_collection');
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */
        $collection->addAttributeToSelect('url_key')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('all_children')
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC')
            ->joinUrlRewrite();

        if ($this->displayProductCount()) {
            $collection->setLoadProductCount(true);
        }

        return $collection;
    }

    /**
     * @param Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection $collection
     *
     * @return RicoNeitzel_VertNav_Block_Navigation
     * @deprecated Now the count is added directly in _getCategoryChildren()
     * @see        _getCategoryChildren()
     */
    protected function _addProductCount($collection)
    {
        if ($collection instanceof Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection) {
            if ($collection->isLoaded()) {
                $collection->loadProductCount($collection->getItems());
            } else {
                $collection->setLoadProductCount(true);
            }
        } else {
            $this->_getProductCollectionResource()->addCountToCategories($collection);
        }
        return $this;
    }

    /**
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
     */
    protected function _getProductCollectionResource()
    {
        if (null === $this->_productCollection) {
            $this->_productCollection = Mage::getResourceModel('catalog/product_collection');
        }
        return $this->_productCollection;
    }

    /**
     * @return bool
     */
    public function displayProductCount()
    {
        return Mage::getStoreConfigFlag('catalog/vertnav/display_product_count');
    }
}
