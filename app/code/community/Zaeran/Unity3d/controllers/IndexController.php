<?php

class Zaeran_Unity3d_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {

//        if ($_POST['FUNCTION'] == "GetCategories") {
//           /** please refer to categories action, accessible via http://yourmagento.com/index.php/unity3d/index/categories */
//        }
//        if ($_POST['FUNCTION'] == "GetProduct") {
//          /**  please refer to categories action, accessible via http://yourmagento.com/index.php/unity3d/index/product  */
//        } else if ($_POST['FUNCTION'] == "ORDER") {
//          /**  please refer to categories action, accessible via http://yourmagento.com/index.php/unity3d/index/order */
//        }
    }


    /**  accessible via http://yourmagento.com/index.php/unity3d/index/categories */
    public function categoriesAction(){
        $this->getCategoryInfo();
    }

    /** accessible via http://yourmagento.com/index.php/unity3d/index/product  */
    public function productAction(){
        if ($categoryId = $this->getRequest()->getParam('CATEGORYID',false)) {
            $this->getProductInfo($categoryId);
        }
    }

    /** accessible via http://yourmagento.com/index.php/unity3d/index/order */
    public function orderAction(){
        try {
            $this->OrderItems($_POST['PRODUCTS'], $_POST['QTY']);
        }
        catch(Exception $ex){

        }
    }

    public function getCategoryInfo()
    {
        $_catagories = Mage::getModel('catalog/category')->getCOllection()
            ->addAttributeToSelect('id')
            ->addAttributeToSelect('name');

        foreach ($_catagories as $_category) {
            echo $_category->getName() . ',' . $_category->getID() . '</br>';
        }
    }

    public function getProductInfo($_catID)
    {
        $_category = Mage::getModel('catalog/category')->load($_catID);
        $products = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addCategoryFilter($_category);
        echo sizeof($products) . '</br>';

        foreach ($products as $_product) {
            echo "NEW PRODUCT</br>";
            echo "ID:" . $_product->getId() . '</br>';
            echo "NAME:" . $_product->getName() . '</br>';
            echo "PRICE:" . $_product->getPrice() . '</br>';
            echo "DESC:" . $_product->getDescription() . '</br>';
            echo "IMAGE:" . $_product->getThumbnailUrl() . "</br>";

        }
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