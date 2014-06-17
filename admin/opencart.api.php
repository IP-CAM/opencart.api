<?php
error_reporting(0);
ini_set("display_errors", "0");

/* 
 *	OpenCart Integration Module
 */

require_once ("config.php");
require_once (DIR_SYSTEM . "startup.php");

switch ($_REQUEST["method"]) {
	case "listOrders":
		print_r(getListOrders($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["status"], $_REQUEST["initialDate"], $_REQUEST["finalDate"]));
		break;
	case "getOrder":
		print_r(getOrder($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["orderId"], $_REQUEST["aditionalFields"], $_REQUEST["productCodeField"]));
		break;
	case "listProducts":
		print_r(getProducts($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["productCodeField"], $_REQUEST["page"], $_REQUEST["limit"]));
		break;
	case "listProductsAndOptions":
		print_r(getProductsAndOptions($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["productCodeField"], $_REQUEST["page"], $_REQUEST["limit"]));
		break;
	case "listStatus":
		print_r(getListStatus($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"]));
		break;
	case "insertUpdateProduct":
		print_r(insertUpdateProduct($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["productData"], $_REQUEST["productCodeField"]));
		break;
	case "setProductStockQuantity":
		print_r(setProductStockQuantity($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"], $_REQUEST["productData"], $_REQUEST["productCodeField"]));
		break;
	case "testConfiguration":
		print_r(getTestConfiguration($_REQUEST["user"], $_REQUEST["password"], $_REQUEST["version"]));
		break;
	default:
		print_r(json_encode(array("result" => "Error", "errorDetails" => "Método inválido ou não informado.")));
}

// === Classes ============================================================================
class Order {
	public $id;
	public $client;
	public $payment_method;
	public $total;
	public $status;
	public $comment;
	public $freight;
	public $items;
	public $date;
	public $discount;
	public $address;
	
	function __construct($id, $date, $payment_method, $total, $status, $comment, $freight, $discount) {
		$this->id = $id;
		$this->date = $date;
		$this->payment_method = $payment_method;
		$this->total = $total;
		$this->status = $status;
		$this->comment = $comment;
		$this->freight = $freight;
		$this->discount = $discount;
		$this->items = array();
	}
	
	public function setClient(Client $client) {
		$this->client = $client;
	}
	
	public function setAddress(Address $address) {
		$this->address = $address;
	}
	
	public function addItem(Item $item) {
		$this->items[] = $item;
	}
}

class Item {
	public $product;
	public $quantity;
	public $price;
	public $total;
	
	function __construct(Product $product, $quantity, $price, $total) {
		$this->product = $product;
		$this->quantity = $quantity;
		$this->price = $price;
		$this->total = $total;
	}
}

class Client {
	public $name;
	public $mail;
	public $phone;
	public $address;
	public $aditionalFields;
	
	function __construct($firstName, $lastName, $mail, $phone) {
		$this->name = $firstName . " " . $lastName;
		$this->mail = $mail;
		$this->phone = $phone;
		$aditionalFields = array();
	}
	
	public function setAddress(Address $address) {
		$this->address = $address;
	}
	
	public function addAditionalField($key, $value) {
		$this->aditionalFields[$key] = $value;
	}
}

class Address {
	public $address;
	public $neighborhood;
	public $city;
	public $postcode;
	public $country;
	public $state;
	
	function __construct($address, $neighborhood, $city, $postcode, $country, $state) {
		$this->address = $address;
		$this->neighborhood = $neighborhood;
		$this->city = $city;
		$this->postcode = $postcode;
		$this->country = $country;
		$this->state = $state;
	}
}

class Product {
	public $name;
	public $name_and_options;
	public $model;
	public $id;
	public $price;
	public $stock_quantity;
	public $weight;
	
	function __construct($name, $name_and_options, $model, $id = null, $price = null, $stock_quantity = null, $weight = null) {
		$this->name = $name;
		$this->name_and_options = $name_and_options;
		$this->model = $model;
		$this->id = $id;
		$this->price = $price;
		$this->stock_quantity = $stock_quantity;
		$this->weight = $weight;
	}
}

// === SQLs ============================================================================
function sql_getUser($user, $password, $version) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	
	switch ($version) {
		case "1.5.1":
		case "1.5.2":
		case "1.5.3":
			$query = $db->query("SELECT * FROM `" . DB_PREFIX . "user` WHERE username = '" . $db->escape($user) . "' AND password = '" . $db->escape(md5($password)) . "' AND status = '1'");
			break;
		default:
			$query = $db->query("SELECT * FROM `" . DB_PREFIX . "user` WHERE username = '" . addslashes($user) . "' AND (password = SHA1(CONCAT(salt, SHA1(CONCAT(salt, SHA1('" . addslashes($password) . "'))))) OR password = '" . addslashes(md5($password)) . "') AND status = '1'");
			break;
	}
	return $query->rows;
}

function sql_getLanguageId() {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT MAX(language_id) AS 'language_id' FROM `" . DB_PREFIX . "language`");
	foreach ($query->rows as $language) {
		return $language["language_id"];
	}
}

function sql_getOrders($status, $initialDate, $finalDate) {
	$languageId = sql_getLanguageId();
	
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$sql = "SELECT o.*, s.name AS 'status' FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "order_status` s ON o.order_status_id = s.order_status_id WHERE s.language_id = '" . $languageId . "' AND o.order_status_id <> 0 AND o.date_added >= '" . $initialDate . "' AND o.date_added <= '" . $finalDate . " 23:59:59' ";
	if (trim($status) != "") {
		$sql .= "AND s.name = '" . $status . "' ";
	}
	$sql .= "ORDER BY o.order_id DESC";
	$query = $db->query($sql);
	return $query->rows;
}

function sql_getOrderStatus() {
	$languageId = sql_getLanguageId();
	
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$sql = "SELECT s.name AS 'status' FROM `" . DB_PREFIX . "order_status` s WHERE s.language_id = '" . $languageId . "' ORDER BY s.name";
	$query = $db->query($sql);
	return $query->rows;
}

function sql_getCustomer($customerId) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT c.*, a.address_1, a.address_2, a.city, a.postcode, z.name AS zone, ct.name AS country FROM `" . DB_PREFIX . "customer` c LEFT JOIN `" . DB_PREFIX . "address` a ON c.address_id = a.address_id LEFT JOIN `" . DB_PREFIX . "zone` z ON a.zone_id = z.zone_id LEFT JOIN `" . DB_PREFIX . "country` ct ON a.country_id = ct.country_id WHERE c.customer_id = '" . $customerId . "'");
	return $query->rows;
}

function sql_getOrder($orderId) {
	$languageId = sql_getLanguageId();
	
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT o.*, s.name AS 'status' FROM `" . DB_PREFIX . "order` o LEFT JOIN `" . DB_PREFIX . "order_status` s ON o.order_status_id = s.order_status_id WHERE o.order_id = '" . $orderId . "' AND s.language_id = '" . $languageId . "'");
	return $query->rows;
}

function sql_getOrderFreight($orderId) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . $orderId . "' AND code = 'shipping'");
	$freightValue = 0;
	foreach ($query->rows as $resultFreight) {
		$freightValue += $resultFreight["value"];
	}
	return $freightValue;
}

function sql_getOrderItems($orderId) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT p.model, p.sku, o.* FROM `" . DB_PREFIX . "order_product` o JOIN `" . DB_PREFIX . "product` p ON o.product_id = p.product_id WHERE o.order_id = '" . $orderId . "'");
	return $query->rows;
}

function sql_getProducts() {
	$languageId = sql_getLanguageId();
	
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT p.product_id, pd.name, p.model, p.sku, p.quantity, p.price, p.weight FROM `" . DB_PREFIX . "product` p JOIN `" . DB_PREFIX . "product_description` pd ON p.product_id = pd.product_id WHERE pd.language_id = '" . $languageId . "' ORDER BY pd.name");
	return $query->rows;
}

function sql_getOrderProductOptions($order_product_id) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE order_product_id = '" . $order_product_id . "'");
	return $query->rows;
}

function sql_getProductOptions($productId) {
	$languageId = sql_getLanguageId();
	
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT o.option_id, o.quantity, o.price, o.weight, d.name, t.sort_order FROM `" . DB_PREFIX . "product_option_value` o JOIN `" . DB_PREFIX . "option_value_description` d ON o.option_value_id = d.option_value_id JOIN `" . DB_PREFIX . "option` t ON o.option_id = t.option_id WHERE o.product_id = '" . $productId . "' AND d.language_id = '" . $languageId . "' ORDER BY t.sort_order");
	return $query->rows;
}

function sql_getOrderTotal($orderId) {
    $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    $query = $db->query("SELECT `value`, '0' AS shipping FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . $orderId . "' AND code <> 'coupon' UNION SELECT ch.amount, c.shipping FROM `" . DB_PREFIX . "coupon_history` ch JOIN `" . DB_PREFIX . "coupon` c ON ch.coupon_id = c.coupon_id WHERE ch.order_id = '" . $orderId . "'");
    return $query->rows;
}

function sql_insertUpdateProduct($data, $version) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $data["id"] . "'");
	if ($query->num_rows) {
		$db->query("DELETE FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . $data["id"] . "' AND store_id = '0'");
		
		if (isset($data["product_image"])) {
			if (count($data["product_image"]) > 0) {
				$db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . $data["id"] . "'");
			}
		}
		
		switch ($version) {
			case "1.5.1":
			case "1.5.2":
			case "1.5.3":
				$db->query("UPDATE `" . DB_PREFIX . "product` SET model = '" . $db->escape($data["model"]) . "', sku = '" . $db->escape($data["sku"]) . "', quantity = '" . (int)$data["quantity"] . "', subtract = '" . (int)$data["subtract"] . "', stock_status_id = '" . (int)$data["stock_status_id"] . "', date_available = NOW(), price = '" . (float)$data["price"] . "', weight = '" . (float)$data["weight"] . "', status = '" . (int)$data["status"] . "', date_added = NOW() WHERE product_id = '" . $db->escape($data["id"]) . "';");
				break;
			default:
				$db->query("UPDATE `" . DB_PREFIX . "product` SET model = '" . $db->escape($data["model"]) . "', sku = '" . $db->escape($data["sku"]) . "', ean = '" . $db->escape($data["ean"]) . "', quantity = '" . (int)$data["quantity"] . "', subtract = '" . (int)$data["subtract"] . "', stock_status_id = '" . (int)$data["stock_status_id"] . "', date_available = NOW(), price = '" . (float)$data["price"] . "', weight = '" . (float)$data["weight"] . "', status = '" . (int)$data["status"] . "', date_added = NOW() WHERE product_id = '" . $db->escape($data["id"]) . "';");
				break;
		}
		
		$productId = $data["id"];
	} else {
		switch ($version) {
			case "1.5.1":
			case "1.5.2":
			case "1.5.3":
				$db->query("INSERT INTO `" . DB_PREFIX . "product` SET product_id = '" . $db->escape($data["id"]) . "', model = '" . $db->escape($data["model"]) . "', sku = '" . $db->escape($data["sku"]) . "', upc = '" . $db->escape($data["upc"]) . "', location = '" . $db->escape($data["location"]) . "', quantity = '" . (int)$data["quantity"] . "', minimum = '" . (int)$data["minimum"] . "', subtract = '" . (int)$data["subtract"] . "', stock_status_id = '" . (int)$data["stock_status_id"] . "', date_available = NOW(), manufacturer_id = '" . (int)$data["manufacturer_id"] . "', price = '" . (float)$data["price"] . "', points = '" . (int)$data["points"] . "', weight = '" . (float)$data["weight"] . "', weight_class_id = '" . (int)$data["weight_class_id"] . "', length = '" . (float)$data["length"] . "', width = '" . (float)$data["width"] . "', height = '" . (float)$data["height"] . "', length_class_id = '" . (int)$data["length_class_id"] . "', status = '" . (int)$data["status"] . "', tax_class_id = '" . $db->escape($data["tax_class_id"]) . "', sort_order = '" . (int)$data["sort_order"] . "', date_added = NOW()");
				break;
			default:
				$db->query("INSERT INTO `" . DB_PREFIX . "product` SET product_id = '" . $db->escape($data["id"]) . "', model = '" . $db->escape($data["model"]) . "', sku = '" . $db->escape($data["sku"]) . "', upc = '" . $db->escape($data["upc"]) . "', ean = '" . $db->escape($data["ean"]) . "', jan = '" . $db->escape($data["jan"]) . "', isbn = '" . $db->escape($data["isbn"]) . "', mpn = '" . $db->escape($data["mpn"]) . "', location = '" . $db->escape($data["location"]) . "', quantity = '" . (int)$data["quantity"] . "', minimum = '" . (int)$data["minimum"] . "', subtract = '" . (int)$data["subtract"] . "', stock_status_id = '" . (int)$data["stock_status_id"] . "', date_available = NOW(), manufacturer_id = '" . (int)$data["manufacturer_id"] . "', price = '" . (float)$data["price"] . "', points = '" . (int)$data["points"] . "', weight = '" . (float)$data["weight"] . "', weight_class_id = '" . (int)$data["weight_class_id"] . "', length = '" . (float)$data["length"] . "', width = '" . (float)$data["width"] . "', height = '" . (float)$data["height"] . "', length_class_id = '" . (int)$data["length_class_id"] . "', status = '" . (int)$data["status"] . "', tax_class_id = '" . $db->escape($data["tax_class_id"]) . "', sort_order = '" . (int)$data["sort_order"] . "', date_added = NOW()");
				break;
		}
		$productId = $db->getLastId();
	}
	
	if (isset($data["image"])) {
		$db->query("UPDATE `" . DB_PREFIX . "product` SET image = '" . $db->escape(html_entity_decode($data["image"], ENT_QUOTES, "UTF-8")) . "' WHERE product_id = '" . (int)$productId . "'");
	}
		
	foreach ($data["product_description"] as $languageId => $value) {
		$query = $db->query("SELECT name FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$productId . "' AND language_id = '" . (int)$languageId . "'");
		if ($query->num_rows) {
			$currentOCDescription = $query->rows[0]["name"];
			if ($currentOCDescription != $value["name"]) {
				$db->query("UPDATE `" . DB_PREFIX . "product_description` SET name = '" . $db->escape($value["name"]) . "', meta_description = '" . $db->escape($value["description"]) . "', description = '" . $db->escape($value["description"]) . "' WHERE product_id = '" . (int)$productId . "' AND language_id = '" . (int)$languageId . "'");
			}
		} else {
			$db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET product_id = '" . (int)$productId . "', language_id = '" . (int)$languageId . "', name = '" . $db->escape($value["name"]) . "', meta_keyword = '" . $db->escape($value["meta_keyword"]) . "', meta_description = '" . $db->escape($value["description"]) . "', description = '" . $db->escape($value["description"]) . "'");
		}
	}
		
	if (isset($data["product_image"])) {
		foreach ($data["product_image"] as $product_image) {
			$db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET product_id = '" . (int)$productId . "', image = '" . $db->escape(html_entity_decode($product_image["image"], ENT_QUOTES, "UTF-8")) . "', sort_order = '" . (int)$product_image["sort_order"] . "'");
		}
	}
	
	$db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$productId . "', store_id = '0'");
}

function sql_setProductStockQuantity($data) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$query = $db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $data["id"] . "'");
	if ($query->num_rows) {
		$db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = '" . (int)$data["quantity"] . "' WHERE product_id = '" . $db->escape($data["id"]) . "';");
	}
}

function sql_getProductIdByCode($id, $code, $codeField) {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$productId = $id;
	$query = $db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $id . "'");
	if (! ($query->num_rows)) {
		if ($codeField == "S") {
			$query = $db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE sku = '" . $code . "'");
		} else {
			$query = $db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE model = '" . $code . "'");
		}
		if ($query->num_rows) {
			foreach ($query->rows as $resultProduct) {
				$productId = $resultProduct["product_id"];
			}
		}
	}
	return $productId;
}

// === Methods ============================================================================
function getTestConfiguration($user, $password, $version) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		return json_encode(array("result" => "Ok"));
	}
}

function getListStatus($user, $password, $version) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		$listStatus = array();
		$db_status = sql_getOrderStatus();
		foreach ($db_status as $result) {
			$listStatus[] = $result["status"];
		}
		return json_encode(array("result" => "Ok", "data" => $listStatus));
	}
}

function getListOrders($user, $password, $version, $status, $initialDate, $finalDate) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		$listOrders = array();
		$db_orders = sql_getOrders($status, $initialDate, $finalDate);
		foreach ($db_orders as $result) {
			$objOrder = new Order($result["order_id"], $result["date_added"], $result["payment_method"], ($result["total"] * $result["currency_value"]), $result["status"], "", 0, 0);
			$objOrder->client = new Client($result["firstname"], $result["lastname"], $result["email"], $result["telephone"]);
			$listOrders[] = $objOrder;
		}
		return json_encode(array("result" => "Ok", "data" => $listOrders));
	}
}

function getOrder($user, $password, $version, $orderId, $aditionalFields, $productCodeField) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		$db_order = sql_getOrder($orderId);
		foreach ($db_order as $result) {
			$freightValue = (sql_getOrderFreight($orderId) * $result["currency_value"]);
			$discount = 0;
			
			$db_total = sql_getOrderTotal($orderId);
            foreach ($db_total as $resultTotal) {
                if ($resultTotal["value"] < 0) {
                	if ($resultTotal["shipping"] == 1) {
                		$discount += (abs($resultTotal["value"]) - $freightValue);
						$freightValue = 0;
                	} else {
                		$discount += abs($resultTotal["value"]);
                	}
                }
            }

			$order = new Order($result["order_id"], $result["date_added"], $result["payment_method"], ($result["total"] * $result["currency_value"]), $result["status"], $result["comment"], $freightValue, $discount);
			$client = new Client($result["firstname"], $result["lastname"], $result["email"], $result["telephone"]);
			
			if ($result["customer_id"] > 0) {
				$db_customer = sql_getCustomer($result["customer_id"]);
				$resultCustomer = $db_customer[0];
				$address = new Address($resultCustomer["address_1"], $resultCustomer["address_2"], $resultCustomer["city"], $resultCustomer["postcode"], $resultCustomer["country"], $resultCustomer["zone"]);
				
				$aditionalFields = explode(",", $aditionalFields);
				if (isset($resultCustomer)) {
					foreach ($resultCustomer as $keyField => $valueField) {
						if (in_array($keyField, $aditionalFields)) {
							$client->addAditionalField($keyField, $valueField);
						}
					}
				}
				
				if (isset($result)) {
					foreach ($result as $keyField => $valueField) {
						if (in_array($keyField, $aditionalFields)) {
							$client->addAditionalField($keyField, $valueField);
						}
					}
				}
			} else {
				$address = new Address($result["shipping_address_1"], $result["shipping_address_2"], $result["shipping_city"], $result["shipping_postcode"], $result["shipping_country"], $result["shipping_zone"]);
			}
			
			$shipping_address = new Address($result["shipping_address_1"], $result["shipping_address_2"], $result["shipping_city"], $result["shipping_postcode"], $result["shipping_country"], $result["shipping_zone"]);
			
			$client->setAddress($address);
			$order->setClient($client);
			$order->setAddress($shipping_address);
			
			$db_items = sql_getOrderItems($orderId);
			foreach ($db_items as $resultItem) {
				if ($productCodeField == "S") {
					$order->addItem(new Item(new Product($resultItem["name"], $resultItem["name"] . getProductNameAndOptions($resultItem["order_product_id"]), $resultItem["sku"]), $resultItem["quantity"], ($resultItem["price"] * $result["currency_value"]), ($resultItem["total"] * $result["currency_value"])));
				} else {
					$order->addItem(new Item(new Product($resultItem["name"], $resultItem["name"] . getProductNameAndOptions($resultItem["order_product_id"]), $resultItem["model"]), $resultItem["quantity"], ($resultItem["price"] * $result["currency_value"]), ($resultItem["total"] * $result["currency_value"])));
				}
			}
		}
		return json_encode(array("result" => "Ok", "data" => $order));
	}
}

function getProducts($user, $password, $version, $productCodeField, $page, $limit) {
	if (! (isset($productCodeField))) {
		$productCodeField = "M";
	}
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		$listProducts = array();
		$db_products = sql_getProducts();
		$initialRecordNumber = ($page - 1) * $limit;
		$finalRecordNumber = ($page * $limit) - 1;
		$recordNumber = 0;
		foreach ($db_products as $result) {
			if (($recordNumber >= $initialRecordNumber) && ($recordNumber <= $finalRecordNumber)) {
				if ($productCodeField == "S") {
					$product = new Product($result["name"], $result["name"], $result["sku"], $result["product_id"], $result["price"], $result["quantity"], $result["weight"]);
				} else {
					$product = new Product($result["name"], $result["name"], $result["model"], $result["product_id"], $result["price"], $result["quantity"], $result["weight"]);
				}
				$listProducts[] = $product;
			}
			$recordNumber ++;
		}
		return json_encode(array("result" => "Ok", "data" => $listProducts));
	}
}

$arProductOptions = array();
$productPrice = 0;
$productWeight = 0;

function getProductsAndOptions($user, $password, $version, $productCodeField, $page, $limit) {
	global $arProductOptions, $productPrice, $productWeight;
	
	if (! (isset($productCodeField))) {
		$productCodeField = "M";
	}
	
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		$listProducts = array();
		$db_products = sql_getProducts();
		$initialRecordNumber = ($page - 1) * $limit;
		$finalRecordNumber = ($page * $limit) - 1;
		$recordNumber = 0;
		foreach ($db_products as $result) {
			$db_option = sql_getProductOptions($result["product_id"]);
			if (count($db_option) > 0) {
				$arOptions = array();
				foreach ($db_option as $resultOption) {
					$keyTmp = str_pad($resultOption["sort_order"], 10, "0", STR_PAD_LEFT) . str_pad($resultOption["option_id"], 10, "0", STR_PAD_LEFT);
					if (! (isset($arOptions[$keyTmp]))) {
						$arOptions[$keyTmp] = array();
					}
					$arOptions[$keyTmp][] = array("option_id" => $keyTmp, "option" => $resultOption["name"], "quantity" => $resultOption["quantity"], "price" => $resultOption["price"], "weight" => $resultOption["weight"]);
				}
				
				$arProductOptions = array();
				$productPrice = $result["price"];
				$productWeight = $result["weight"];
				getOptions(0, "", $arOptions);
					
				foreach ($arProductOptions as $optionValue) {
					if (($recordNumber >= $initialRecordNumber) && ($recordNumber <= $finalRecordNumber)) {
						if ($productCodeField == "S") {
							$product = new Product($result["name"], $result["name"] . " - " . $optionValue["name"], $result["sku"], $result["product_id"], $result["price"], $optionValue["quantity"], $result["weight"]);
						} else {
							$product = new Product($result["name"], $result["name"] . " - " . $optionValue["name"], $result["model"], $result["product_id"], $result["price"], $optionValue["quantity"], $result["weight"]);
						}
						$listProducts[] = $product;
					}
					$recordNumber ++;
				}
			} else {
				if (($recordNumber >= $initialRecordNumber) && ($recordNumber <= $finalRecordNumber)) {
					if ($productCodeField == "S") {
						$product = new Product($result["name"], $result["name"], $result["sku"], $result["product_id"], $result["price"], $result["quantity"], $result["weight"]);
					} else {
						$product = new Product($result["name"], $result["name"], $result["model"], $result["product_id"], $result["price"], $result["quantity"], $result["weight"]);
					}
					$listProducts[] = $product;
				}
				$recordNumber ++;
			}
		}
		return json_encode(array("result" => "Ok", "data" => $listProducts));
	}
}

function insertUpdateProduct($user, $password, $version, $productData, $productCodeField) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		
		if (! (isset($productCodeField))) {
			$productCodeField = "M";
		}
		
		$productData = json_decode($productData);
		$productData->id = sql_getProductIdByCode($productData->id, $productData->model, $productCodeField);

		$data = array();
		$data["id"] = $productData->id;
		$data["model"] = $productData->model;
		$data["sku"] = $productData->model;
		$data["price"] = $productData->price;
		$data["weight"] = $productData->weight;
		$data["quantity"] = $productData->quantity;
		$data["ean"] = $productData->ean;
		$data["status"] = $productData->status;
		$data["stock_status_id"] = "5";
		$data["subtract"] = "1";
		
		$productData->description = str_replace("#amp;", "&", $productData->description);
		$productData->description = str_replace("#lt;", "<", $productData->description);
		$productData->description = str_replace("#gt;", ">", $productData->description);
		$productData->description = str_replace("#quot;", "'", $productData->description);
		
		$languageId = sql_getLanguageId();
		$data["product_description"] = array();
		$data["product_description"][$languageId] = array("name" => $productData->description, "description" => $productData->description);
		
		$data["image"] = null;
		$data["product_image"] = array();
		
		foreach ($productData->images as $value) {
			$path = file_get_contents(urldecode($value->url));
			$fp = fopen("../image/data/" . $value->name, "w");
			fwrite($fp, $path);
			fclose($fp);
			
			if ($data["image"] == null) {
				$data["image"] = "data/" . $value->name;
			} else {
				$data["product_image"][] = array("image" => "data/" . $value->name);
			}
		}
				
		sql_insertUpdateProduct($data, $version);
		
		return json_encode(array("result" => "Ok"));
	}
}

function setProductStockQuantity($user, $password, $version, $productData, $productCodeField) {
	if (! testUser($user, $password, $version)) {
		return json_encode(array("result" => "Error", "errorDetails" => "Usuário não cadastrado ou senha incorreta."));
	} else {
		
		if (! (isset($productCodeField))) {
			$productCodeField = "M";
		}
		
		$productData = json_decode($productData);
		$productData->id = sql_getProductIdByCode($productData->id, $productData->model, $productCodeField);

		$data = array();
		$data["id"] = $productData->id;
		$data["quantity"] = $productData->quantity;
		
		sql_setProductStockQuantity($data);
		
		return json_encode(array("result" => "Ok"));
	}
}

// === Auxiliary methods ============================================================================
function testUser($user, $password, $version) {
	$db_user = sql_getUser($user, $password, $version);
	foreach ($db_user as $result) {
		return true;
	}
	return false;
}

function getProductNameAndOptions($order_product_id) {
	$db_options = sql_getOrderProductOptions($order_product_id);
	$result = "";
	foreach ($db_options as $resultOption) {
		$result .= " - " . $resultOption["value"];
	}
	return $result;
}

function getOptions($option_id, $descricao, $arOptions) {
	global $arProductOptions, $productPrice, $productWeight;
	
	foreach ($arOptions as $keyOption => $valueoption) {
		if ($keyOption > $option_id) {
			foreach ($arOptions[$keyOption] as $keyAux => $valueAux) {
				if (trim($descricao) == "") {
					$descricaoAux = getOptions($keyOption, $valueAux["option"], $arOptions);
				} else {
					$descricaoAux = getOptions($keyOption, $descricao . " |-| " . $valueAux["option"], $arOptions);
				}
				
				$test = explode("|-|", $descricaoAux);
				
				if (count($test) == count($arOptions)) {
					$productName = str_replace("|-|", "-", $descricaoAux);
					$arProductOptions[] = array("name" => $productName, "price" => ($productPrice + $valueAux["price"]), "quantity" => $valueAux["quantity"], "weight" => ($productWeight + $valueAux["weight"]));
				}
			}
		}
	}
	return $descricao;
}
