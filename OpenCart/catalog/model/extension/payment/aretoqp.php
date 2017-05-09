<?php

class ModelExtensionPaymentAretoqp extends Model
{
    /**
     * Returns information about Payment Method for the checkout process
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/aretoqp');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('aretoqp_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if ($this->config->get('aretoqp_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('aretoqp_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'aretoqp',
                'title' => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('aretoqp_sort_order')
            );
        }

        return $method_data;
    }
}
