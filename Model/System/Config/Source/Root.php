<?php

class RicoNeitzel_VertNav_Model_System_Config_Source_Root
	extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
	protected $_options;

    public function toOptionArray()
    {
		if (! isset($this->_options))
		{
			$options = array(
				array(
					'label' => Mage::helper('vertnav')->__('Store base'),
					'value' => 'root',
				),
				array(
					'label' => Mage::helper('vertnav')->__('Current category children'),
					'value' => 'current',
				),
				array(
					'label' => Mage::helper('vertnav')->__('Same level as current category'),
					'value' => 'siblings',
				),
			);
			$resource = Mage::getModel('catalog/category')->getResource();
			$select = $resource->getReadConnection()->select()->reset()
				->from($resource->getTable('catalog/category'), new Zend_Db_Expr('MAX(`level`)'));
			$maxDepth = $resource->getReadConnection()->fetchOne($select);
			for ($i = 2; $i < $maxDepth; $i++)
			{
				$options[] = array(
					'label' => Mage::helper('vertnav')->__('Category Level %d', $i),
					'value' => $i,
				);
			}
			$this->_options = $options;
		}
		return $this->_options;
    }

    public function getAllOptions()
    {
    	return $this->toOptionArray();
    }
}