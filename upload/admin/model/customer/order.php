<?php  
class ModelCustomerOrder extends Model {
	public function editOrder($order_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$data['order_status_id'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

      	$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$data['order_status_id'] . "', notify = '" . (int)@$data['notify'] . "', comment = '" . $this->db->escape(strip_tags($data['comment'])) . "', date_added = NOW()");

      	if (isset($data['notify'])) {
        	$query = $this->db->query("SELECT *, os.name AS status, l.code AS language FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id AND os.language_id = o.language_id) LEFT JOIN " . DB_PREFIX . "language l ON (o.language_id = l.language_id) WHERE o.order_id = '" . (int)$order_id . "'");
	    	
			if ($query->num_rows) {
				$language = new Language($query->row['language']);
				$language->load('customer/order');

				$subject = sprintf($language->get('mail_subject'), $this->config->get('config_store'), $order_id);
	
				$message  = $language->get('mail_order') . ' ' . $order_id . "\n";
				$message .= $language->get('mail_date_added') . ' ' . date($language->get('date_format_short'), strtotime($query->row['date_added'])) . "\n\n";
				$message .= $language->get('mail_order_status') . "\n\n";
				$message .= $query->row['status'] . "\n\n";
					
				$message .= $language->get('mail_invoice') . "\n";
				$message .= html_entity_decode(HTTP_CATALOG . 'index.php?route=account/invoice&order_id=' . $order_id) . "\n\n";
					
				if (isset($data['comment'])) { 
					$message .= $language->get('mail_comment') . "\n\n";
					$message .= strip_tags(html_entity_decode($data['comment'])) . "\n\n";
				}
					
				$message .= $language->get('mail_footer');

				$mail = new Mail();
	    		$mail->setTo($query->row['email']);
				$mail->setFrom($this->config->get('config_email'));
	    		$mail->setSender($this->config->get('config_store'));
	    		$mail->setSubject($subject);
	    		$mail->setText($message);
	    		$mail->send();
			}
		}
	}
	
	public function deleteOrder($order_id) {
      	$this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
      	$this->db->query("DELETE FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$order_id . "'");
      	$this->db->query("DELETE FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
      	$this->db->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "'");
	  	$this->db->query("DELETE FROM " . DB_PREFIX . "order_download WHERE order_id = '" . (int)$order_id . "'");
      	$this->db->query("DELETE FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "'");
	}
		
	public function getOrder($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
	
		return $query->row;
	}
	
	public function getOrders($data = array()) {
		$sql = "SELECT o.order_id, CONCAT(o.firstname, ' ', o.lastname) AS name, os.name AS status, o.date_added, o.total, o.currency, o.value FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) WHERE os.language_id = '" . (int)$this->language->getId() . "' AND o.order_status_id > '0'";

		if (isset($data['order_id'])) {
			$sql .= " AND o.order_id = '" . (int)$data['order_id'] . "'";
		}

		if (isset($data['name'])) {
			$sql .= " AND CONCAT(o.firstname, ' ', o.lastname) LIKE '%" . $this->db->escape($data['name']) . "%'";
		}

		if (isset($data['order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['order_status_id'] . "'";
		}
		
		if (isset($data['date_added'])) {
			$sql .= " AND DATE(o.date_added) = DATE('" . $this->db->escape($data['date_added']) . "')";
		}
		
		if (isset($data['total'])) {
			$sql .= " AND o.total = '" . (float)$data['total'] . "'";
		}

		$sort_data = array(
			'o.order_id',
			'name',
			'os.name',
			'o.date_added',
			'o.total',
		);	
			
		if (in_array(@$data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];	
		} else {
			$sql .= " ORDER BY o.order_id";	
		}
			
		if (@$data['order'] == 'DESC') {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}
			
		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}		
		
		$query = $this->db->query($sql);
		
		return $query->rows;
	}	
	
	public function getOrderProducts($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
	
		return $query->rows;
	}

	public function getOrderOptions($order_id, $order_product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "'");
	
		return $query->rows;
	}
	
	public function getOrderTotals($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "'");
	
		return $query->rows;
	}	

	public function getOrderHistory($order_id) { 
		$query = $this->db->query("SELECT oh.date_added, os.name AS status, oh.comment, oh.notify FROM " . DB_PREFIX . "order_history oh LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id WHERE oh.order_id = '" . (int)$order_id . "' AND os.language_id = '" . (int)$this->language->getId() . "' ORDER BY oh.date_added");
	
		return $query->rows;
	}	

	public function getOrderDownloads($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_download WHERE order_id = '" . (int)$order_id . "' ORDER BY name");
	
		return $query->rows; 
	}	
				
	public function getTotalOrders($data = array()) {
      	$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE order_status_id > '0'";

		if (isset($data['order_id'])) {
			$sql .= " AND order_id = '" . (int)$data['order_id'] . "'";
		}

		if (isset($data['name'])) {
			$sql .= " AND CONCAT(o.firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['name']) . "%'";
		}

		if (isset($data['order_status_id'])) {
			$sql .= " AND order_status_id = '" . (int)$data['order_status_id'] . "'";
		}
		
		if (isset($data['date_added'])) {
			$sql .= " AND DATE(date_added) = DATE('" . $this->db->escape($data['date_added']) . "')";
		}
		
		if (isset($data['total'])) {
			$sql .= " AND total = '" . (float)$data['total'] . "'";
		}
		
		$query = $this->db->query($sql);
		
		return $query->row['total'];
	} 
			
	public function getOrderHistoryTotalByOrderStatusId($order_status_id) {
	  	$query = $this->db->query("SELECT oh.order_id FROM " . DB_PREFIX . "order_history oh LEFT JOIN `" . DB_PREFIX . "order` o ON (oh.order_id = o.order_id) WHERE oh.order_status_id = '" . (int)$order_status_id . "' AND o.order_status_id > '0' GROUP BY order_id");

		return $query->num_rows;
	}

	public function getTotalOrdersByOrderStatusId($order_status_id) {
      	$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE order_status_id = '" . (int)$order_status_id . "' AND order_status_id > '0'");
		
		return $query->row['total'];
	}
	
	public function getTotalOrdersByLanguageId($language_id) {
      	$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE language_id = '" . (int)$language_id . "' AND order_status_id > '0'");
		
		return $query->row['total'];
	}	
	
	public function getTotalOrdersByCurrencyId($currency_id) {
      	$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE currency_id = '" . (int)$currency_id . "' AND order_status_id > '0'");
		
		return $query->row['total'];
	}		
}
?>