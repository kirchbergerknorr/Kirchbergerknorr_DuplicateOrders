<?php
/**
 * Observer Model
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr_DuplicateOrders
 * @author      Aleksey Razbakov <ar@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2014 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kirchbergerknorr_DuplicateOrders_Model_Observer
{
    public function log($message)
    {
        echo "$message\n";
        if (Mage::getStoreConfig('kirchbergerknorr/duplicate_orders/log')) {
            Mage::log($message, null, 'kk_duplicate_orders.log');
        }
    }

    public function cancelOrders($observer)
    {
        if (!Mage::getStoreConfig('kirchbergerknorr/duplicate_orders/active')) {
            $this->log('Kirchbergerknorr_DuplicateOrders is not active');
            return false;
        }

        $this->_cancelDuplicateOrders();
    }

    protected function _cancelDuplicateOrders()
    {
        $query = "
                select
                    increment_id, state, email_sent, customer_email, grand_total, created_at, quote_id as quote,
                    (select count(t.quote_id)
                    from sales_flat_order as t
                    where t.quote_id = quote
                    group by t.quote_id) as count
                from sales_flat_order where state = 'new' having count > 1 and email_sent is NULL order by quote_id desc, created_at desc;
        ";

        try {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $results = $connection->fetchAll($query);

            foreach ($results as $result) {
                try {
                    $this->log(json_encode($result));
                    $id = $result['increment_id'];

                    if ($connection->query("UPDATE sales_flat_order SET state = 'duplicate', status = 'duplicate' WHERE increment_id = {$id}")) {
                        $this->log("-> canceled");
                    }
                } catch (Exception $e) {
                    $this->log("Exception: ".$e->getMessage());
                }
            }

        } catch (Exception $e) {
            $this->log("Exception: ".$e->getMessage());
        }
    }
}