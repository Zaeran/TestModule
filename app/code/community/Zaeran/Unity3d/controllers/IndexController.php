<?php

class Zaeran_Unity3d_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Predispatch: insuring that unity3d is enabled for this store
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        parent::preDispatch();
        //Force POST request check for all actions in this controller
        //Insure that unity3d is enabled for this store
        if (!Mage::getStoreConfig(Zaeran_Unity3d_Helper_Data::XPATH_ENABLED) || !$this->getRequest()->isPost()) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
        return $this;
    }

    /**
     * POST Requests Only
     * Returns the category information for store
     *  accessible via http://yourmagento.com/index.php/unity3d/index/categories
     * @return Mage_Core_Controller_Front_Action | void
     */
    public function categoriesAction()
    {

        /** @var Mage_Catalog_Model_Category $rootCategory */
        $rootCategory = Mage::getModel('catalog/category')
            ->load(Mage::app()->getStore()->getRootCategoryId());

        /** @var Mage_Catalog_Model_Resource_Category_Collection $categories */
        //This doesn't put any sub-category layering on this, all categories are equal.
        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('name', 'id')
            ->addIdFilter($rootCategory->getAllChildren())
            //this solves the foreach problem you were getting, should only be called after all filters added
            ->load();

        //Send data using JSON
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($categories->toArray(array('name'))));
    }

    /**
     * POST Requests Only
     * Returns the product information for given category
     *  accessible via  http://yourmagento.com/index.php/unity3d/index/product/
     * @return Mage_Core_Controller_Front_Action | void
     */
    public function productAction()
    {

        $categoryId = $this->getRequest()->getParam('CATEGORYID', false);
        if ($categoryId === false || !is_numeric($categoryId)) {
            //we may want to force a 500 error here instead of a 404 (let me know if that's how you want the api to work)
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return;
        }

        /** @var Mage_Catalog_Model_Category $_category */
        $_category = Mage::getModel('catalog/category')
            ->load($categoryId);

        $outputAttributes = array('name', 'price', 'description', 'type_id', 'image');
        /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
        $products = Mage::getModel('catalog/product')->getCollection()
            //seeing we know the attributes we need, we only need to join those attributes instead of * (take load off db processing)
            // ->addAttributeToSelect('*')
            ->addAttributeToSelect($outputAttributes)
            ->addCategoryFilter($_category);
            //This is if you want to get the final price including specials and tax
            //->addFinalPrice()

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($this->_toProductsArray($products, $outputAttributes)));

    }


    /**
     * Converts Product Collection to array that includes required data.
     *
     * @param $collection Mage_Catalog_Model_Resource_Product_Collection
     * @return array
     */
    protected function _toProductsArray($collection, $attributes){
        $output = array();
        $fetchImage = in_array('image', $attributes);
        //This is the cleanest way to achieve this. Inside $collection->toArray(), this is what it's doing.
        foreach ($collection as $id => $_product){
            $output[$id] = $_product->toArray($attributes);
            if ($fetchImage) {
                $output[$id]['image'] = $_product->getImageUrl();
            }
            //This is if you want to get the final price including specials and tax (and the attribute is included within the collection)
            //$store = $_product->getStore();
            //$outputArray[$id]['final_price'] = $store->roundPrice($store->convertPrice($_product->getFinalPrice()));
        }
        return $output;
    }
    /**
     * POST Requests Only
     * Returns the child product information for given product attributes
     * accessible via  http://yourmagento.com/index.php/unity3d/index/childProduct/
     * @return Mage_Core_Controller_Front_Action | void
     */
    public function childProductAction()
    {
        //get parent ID
        $_parentID = $this->getRequest()->getParam('PARENTID', false);
        if (!$_parentID){
            return;
        }
        //load ID of all child objects
        $ids = Mage::getResourceSingleton('catalog/product_type_configurable')
            ->getChildrenIds($_parentID);

        //load in JSON data
        $attData = $this->getRequest()->getParam('ATTRIBUTES', false);
        $JSONArray = Mage::helper('core')->jsonDecode($attData);

        $noOfAttributes = sizeOf($JSONArray);

        //get our child products
        $_subproducts = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('entity_id', $ids)
            ->addAttributeToSelect('*');

        $productModel = Mage::getModel('catalog/product');
        //filter each selected attribute
        for ($i = 0; $i < $noOfAttributes; $i++) {
            $attr = $productModel->getResource()->getAttribute($JSONArray[$i]["ATTRIBUTE"]);
            if ($attr->usesSource()) {
                $attrID = $attr->getSource()->getOptionId($JSONArray[$i]["VALUE"]);
            }
            $_subproducts->addAttributeToFilter($JSONArray[$i]["ATTRIBUTE"], $attrID);
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($this->_toProductsArray($_subproducts, array('name', 'price', 'description', 'image', 'type_id'))));
    }

    /**
     * POST Requests Only
     * Returns the product information for given category
     *  accessible via  http://yourmagento.com/index.php/unity3d/index/order
     * @return Mage_Core_Controller_Front_Action | void
     */
    public function orderAction()
    {

        $result = array();
        //Added try/catch here, if a db transaction fails, it throws an exception that you'll want to handle in your application.
        try {
            $products = $this->getRequest()->getParam('PRODUCTS', false);
            //TODO: validate products
            $qty = $this->getRequest()->getParam('QTY', false);
            //TODO: validate qtys
            //this OrderItems looks like there is no validation happening here but it is multistore compatible
            //$_POST will work, but to keep inline with Magento Coding, I'd change them to  $this->getRequest()->getParam('PARAM_NAME',false);
            //check out Mage_Checkout_OnepageController::saveOrderAction and Mage_Sales_Model_Quote for how Magento handles default validation
            $this->OrderItems($products, $qty);
            $result['success'] = true;
        } catch (Exception $ex) {
            Mage:
            logException($ex);
            $result['success'] = false;
            $result['error_message'] = Mage::helper('core')->__('Failed to save order: %s', $ex->getMessage());
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }


    //public function OrderItem($customer){
    public function OrderItems($productString, $qtyString)
    {

//create guest customer
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->setStore(Mage::app()->getStore());

        $customer->setFirstname($_POST['FNAME']);
        $customer->setLastname($_POST['LNAME']);
        $customer->setEmail($_POST['EMAIL']);

        $transaction = Mage::getModel('core/resource_transaction');
        $storeId = $customer->getStoreId();
        $reservedOrderId = Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($storeId);

        $order = Mage::getModel('sales/order')
            ->setIncrementId($reservedOrderId)
            ->setStoreId($storeId)
            ->setQuoteId(0)
            ->setGlobal_currency_code('USD')
            ->setBase_currency_code('USD')
            ->setStore_currency_code('USD')
            ->setOrder_currency_code('USD');


// set Customer data
        $order->setCustomer_email($customer->getEmail())
            ->setCustomerFirstname($customer->getFirstname())
            ->setCustomerLastname($customer->getLastname())
            ->setCustomer_is_guest(1)
            ->setCustomer($customer);

        $billingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING)
            ->setCustomerId($customer->getId())
            ->setCustomerAddressId($customer->getDefaultBilling())
            ->setCustomer_address_id($customer->getDefaultBilling())
            ->setPrefix($customer->getPrefix())
            ->setFirstname($customer->getFirstname())
            ->setMiddlename($customer->getMiddlename())
            ->setLastname($customer->getLastname())
            ->setSuffix($customer->getSuffix())
            ->setCompany($customer->getCompany())
            ->setStreet($_POST['STREET'])
            ->setCity($_POST['CITY'])
            ->setCountry_id($_POST['COUNTRY'])
            ->setRegion($_POST['REGION'])
            ->setRegion_id("")
            ->setPostcode($_POST['POSTCODE'])
            ->setTelephone($_POST['PHONE'])
            ->setFax($_POST['FAX']);
        $order->setBillingAddress($billingAddress);

        $shippingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
            ->setCustomerId($customer->getId())
            ->setCustomerAddressId($customer->getDefaultShipping())
            ->setCustomer_address_id($customer->getEntityId())
            ->setPrefix($customer->getPrefix())
            ->setFirstname($customer->getFirstname())
            ->setMiddlename($customer->getMiddlename())
            ->setLastname($customer->getLastname())
            ->setSuffix($customer->getSuffix())
            ->setCompany($customer->getCompany())
            ->setStreet($_POST['STREET'])
            ->setCity($_POST['CITY'])
            ->setCountry_id($_POST['COUNTRY'])
            ->setRegion($_POST['REGION'])
            ->setRegion_id("")
            ->setPostcode($_POST['POSTCODE'])
            ->setTelephone($_POST['PHONE'])
            ->setFax($_POST['FAX']);

        $order->setShippingAddress($shippingAddress)
            ->setShipping_method('flatrate_flatrate');

//you can set your payment method name here as per your need
        $orderPayment = Mage::getModel('sales/order_payment')
            ->setMethod('ccsave')
            ->setCcNumber($_POST['CCNO'])
            ->setCcOwner($_POST['CCNAME'])
            ->setCcType($_POST['CCTYPE'])
            ->setCcExpMonth($_POST['CCMONTH'])
            ->setCcExpYear($_POST['CCYEAR'])
            ->setCcLast4($_POST['CCLASTFOUR'])
            ->setCcCid($_POST['CCCID']);
        $order->setPayment($orderPayment);


        $subTotal = 0;
//get individual product names
        $productIndividual = explode(",", $productString);
        $qtyIndividual = explode(",", $qtyString);
        $noOfProducts = sizeof($productIndividual);
        $products = array();
//add products + qty to array
        for ($i = 0; $i < $noOfProducts; $i++) {
            $tempArray = array($productIndividual[$i] => array('qty' => $qtyIndividual[$i]));
            $products = $tempArray + $products;
        }
//$productIndividual[$i]
        foreach ($products as $productId => $product) {
            $_product = Mage::getModel('catalog/product')->load($productId);
            $rowTotal = $_product->getPrice() * $product['qty'];
            $orderItem = Mage::getModel('sales/order_item')
                ->setStoreId($storeId)
                ->setQuoteItemId(0)
                ->setQuoteParentItemId(NULL)
                ->setProductId($productId)
                ->setProductType($_product->getTypeId())
                ->setQtyBackordered(NULL)
                ->setTotalQtyOrdered($product['rqty'])
                ->setQtyOrdered($product['qty'])
                ->setName($_product->getName())
                ->setSku($_product->getSku())
                ->setPrice($_product->getPrice())
                ->setBasePrice($_product->getPrice())
                ->setOriginalPrice($_product->getPrice())
                ->setRowTotal($rowTotal)
                ->setBaseRowTotal($rowTotal);

            $subTotal += $rowTotal;
            $order->addItem($orderItem);
        }

        $order->setSubtotal($subTotal)
            ->setBaseSubtotal($subTotal)
            ->setGrandTotal($subTotal)
            ->setBaseGrandTotal($subTotal);

        $transaction->addObject($order);
        $transaction->addCommitCallback(array($order, 'place'));
        $transaction->addCommitCallback(array($order, 'save'));
        $transaction->save();


    }
}