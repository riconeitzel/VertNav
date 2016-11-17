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
    /** @var Mage_Catalog_Model_Resource_Category_Collection */
    protected $_baseCategories;
    
    /** @var int */
    protected $_rootCategoryId;
    
    /** @var  Mage_Catalog_Model_Category[] */
    protected $_categoriesByParentId;

    /**
     * Add the customer group to the cache key so this module is compatible with more extensions.
     * Netzarbeiter_GroupsCatalog
     * Netzarbeiter_LoginCatalog
     * Also add the current product and current cms page id if they exist if
     * this block has been added to a cms or product detail page.
     * 
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
     * 
     * @return boolean
     */
    protected function _checkLoginCatalog()
    {
        return $this->_isLoginCatalogInstalledAndActive() && $this->_loginCatalogHideCategories();
    }

    /**
     * Check if the Netzarbeiter_LoginCatalog extension is installed and active
     * 
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
     * 
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
     * @param Mage_Catalog_Model_Category $category
     * @param int                         $level
     * @param array                       $levelClass
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

        if ($this->_hasCategoryChildren($category)) {
            $levelClass[] = 'has-children';
        }

        $levelClass = array_merge($levelClass, $combineClasses);

        $levelClass[] = $this->_getClassNameFromCategoryName($category);

        $productCount = '';
        if ($this->displayProductCount()) {
            $num = Mage::getModel('catalog/layer')
                ->setCurrentCategory($category->getId())->getProductCollection()->getSize();
            $productCount = '<span class="product-count"> (' . $num . ')</span>';
        }

        // indent HTML!
        $html[1] = str_pad("", (($level * 2) + 4), " ") .
            '<span class="vertnav-cat"><a href="' .
            $this->getCategoryUrl($category) .
            '"><span>' .
            $this->escapeHtml($category->getName()) .
            '</span></a>' . $productCount . "</span>\n";

        $autoMaxDepth = Mage::getStoreConfig('catalog/vertnav/expand_all_max_depth');
        $autoExpand = Mage::getStoreConfig('catalog/vertnav/expand_all');

        if (
            in_array($category->getId(), $this->getCurrentCategoryPath()) ||
            ($autoExpand && $autoMaxDepth == 0) ||
            ($autoExpand && $autoMaxDepth > $level + 1)
        ) {
            $children = $this->_getChildrenCategories($category);

            //usort($children, array($this, '_sortCategoryArrayByName'));

            $childrenCount = 0;
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
     * I need an array with the index being continuing numbers, so
     * it's possible to check for the previous/next category
     *
     * @param array|Varien_Data_Collection $collection
     * @return array
     * @throws Mage_Core_Exception
     */
    public function toLinearArray($collection)
    {
        if (is_array($collection)) {
            $array = $collection;
        } elseif (is_object($collection) && $collection instanceof Varien_Data_Collection) {
            $array = $collection->getItems();
        } else {
            $type = is_object($collection) ? get_class($collection) : gettype($collection);
            
            throw new Mage_Core_Exception(
                $this->__('Invalid argument type "%s" passed to toLinearArray()', $type)
            );
        }
        $array = array_values($array);
        return $array;
    }

    /**
     * Sorting Method
     *
     * @param Mage_Catalog_Model_Category $arg1
     * @param Mage_Catalog_Model_Category $arg2
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
     * 
     * @return int
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
     * @return bool
     */
    protected function _isCurrentCategory($category)
    {
        $cat = $this->getCurrentCategory();
        return $cat && $cat->getId() == $category->getId();
    }

    /**
     * Return the number of products assigned to the category
     *
     * @param Mage_Catalog_Model_Category|Varien_Data_Tree_Node $category
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
     * @return int
     */
    protected function _getProductCountFromTreeNode(Varien_Data_Tree_Node $category)
    {
        return Mage::getSingleton('catalog/category')
            ->setId($category->getId())
            ->getProductCount();
    }

    /**
     * Get categories of current store, using the max depth setting for the vertical navigation
     * 
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getStoreCategories()
    {
        if (isset($this->_baseCategories)) {
            return $this->_baseCategories;
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

        $parent = $this->getRootCategoryId();

        /**
         * Check if parent node of the store still exists
         */
        if (!$parent || !$category->checkId($parent)) {
            return array();
        }
        $this->_baseCategories = $this->_getChildrenCategories($parent);

        return $this->_baseCategories;
    }

    /**
     * Get root category id for vertical navigation
     * @return int
     */
	public function getRootCategoryId(){

        if ( $this->_rootCategoryId ) {
            return $this->_rootCategoryId;
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
        $this->_rootCategoryId = $parent;
        return $this->_rootCategoryId;
    }
    
    /**
     * Loads root category for vertical naviation
     * @return Mage_Catalog_Model_Category
     **/
    public function getRootCategory()
    {
        $id = $this->getRootCategoryId();
        if ($id) {
            return Mage::getModel('catalog/category')->load($id);
        }
    }

    /**
     * $childrenIdString is a comma separated list of category IDs
     * 
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

    /**
     * @param int|Mage_Catalog_Model_Category $parentCategory
     * @return Mage_Catalog_Model_Resource_Category_Collection|Mage_Catalog_Model_Category[]
     */
    protected function _getChildrenCategories($parentCategory)
    {
        if ($this->_preloadCategories()) {

            if ($parentCategory instanceof Mage_Catalog_Model_Category) {
                $categoryId = $parentCategory->getId();
            } else {
                $categoryId = $parentCategory;
            }

            if (isset($this->_categoriesByParentId[$categoryId])) {
                return $this->_categoriesByParentId[$categoryId];
            }

            return array();
        }
        
        if (!$parentCategory instanceof Mage_Catalog_Model_Category) {
            $parentCategory = Mage::getModel('catalog/category')->load($parentCategory);
        }
        
        return $this->_getCategoryCollection()->addIdFilter($parentCategory->getChildren());
    }

    /**
     * @param $category
     * @return mixed
     */
    protected function _hasCategoryChildren($category)
    {
        if ($this->_preloadCategories()) {
            return isset($this->_categoriesByParentId[$category->getId()]);
        }
        return $category->hasChildren();
    }

    /**
     * @return bool
     */
    protected function _preloadCategories()
    {
        if (!is_null($this->_categoriesByParentId)) {
            return true;
        }
        
        $autoMaxDepth = Mage::getStoreConfig('catalog/vertnav/expand_all_max_depth');
        $autoExpand = Mage::getStoreConfig('catalog/vertnav/expand_all');

        $this->_categoriesByParentId = array();
        if ($autoExpand) {
            $categories = $this->_getCategoryCollection();
            $categories
                ->addFieldToFilter('level', array('gteq' => 2));
            if ($autoMaxDepth > 0) {
                $categories
                    ->addFieldToFilter('level', array('lteq' => $autoMaxDepth + 1));
            }
            foreach ($categories as $category) {
                $this->_categoriesByParentId[$category->getParentId()][] = $category;
            }
            return true;
        }
        return false;
    }
}
