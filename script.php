<?php
/*  PHP script to export all enabled products from Prestashop database to an .xml file for vertaa.fi (tested on version 1.6) 
*   
*   Set up the settings below, set up a cron job for this script to run daily.
*   Set the output path to a fixed location on your website and give the path to vertaa.fi
*
*   @Author Miro Metsänheimo
*           miro@metsanheimo.fi
*           http://miro.metsanheimo.fi
*/

// --- Settings --- //
$db = "localhost"; // Database address
$dbuser = "user"; // Database user
$dbpass = "pass"; // Database password
$dbname = "name"; // Database name
$output = "path"; // Path to output file
$url = "http://webshop.com/"; // URL to your Prestashop front page
$deliveryCost = "5.90"; // Delivery costs from your e-commerce
$deliveryTime = "2-4 days"; // Estimated time to deliver products
$taxId10 = "1"; // Tax ID of your 10%, this is the Finnish tax for books and medicine
$taxId14 = "2"; // Tax ID of your 14%, this is the Finnish tax for food
$taxId24 = "3"; // Tax ID of your 24%, this is the normal Finnish tax for goods
// --- Settings END --- //

header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL);
ini_set("display_errors", 1);
set_time_limit(0);
ob_implicit_flush(TRUE);
ob_end_flush();
// Connect to the DB
$mysqli = new mysqli($db, $dbuser, $dbpass, $name);
if (mysqli_connect_errno()) {
	printf("Connect failed: %s\n", mysqli_connect_error());
	exit();
}
if (!$mysqli->set_charset("utf8")) {
    printf("Error loading character set UTF8: %s\n", $mysqli->error);
} else {
    printf("Current character set: %s\n", $mysqli->character_set_name());
}
$xmlfile = $output;
// Empty the file
$f = @fopen($xmlfile, "r+");
if ($f !== false) {
    ftruncate($f, 0);
    fclose($f);
}
// Open the emptied file
$fp = fopen($xmlfile, "a");
$data = '<?xml version="1.0" encoding="UTF-8" ?>
<Products>
';
// The SQL query to get all the required data
$sql = '
SELECT cl.name AS category, c.id_parent as parent, pl.name AS productName, p.price as price_no_tax, CONCAT("' . $url . '", cl.link_rewrite, "/", p.id_product, "-", pl.link_rewrite, "-", p.ean13, ".html") as link,
p.id_tax_rules_group as taxClass, p.ean13 as ean13, pl.description as description, i.id_image as id_image
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
LEFT JOIN ps_category c ON cp.id_category = c.id_category
LEFT JOIN ps_category_lang cl ON cp.id_category = cl.id_category
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product
LEFT JOIN ps_image i ON p.id_product = i.id_product
WHERE p.active = 1
GROUP BY p.id_product
;';
// Execute the DB query
$result = $mysqli->query($sql) or die($mysqli->error.__LINE__);
// Parse the returned data
if($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$data .= "\n<Product>";
		$data .= "\n<Category>" . getCategoryName($row["parent"], $mysqli) . "</Category>";
		$data .= "\n<SubCategory>" . $row["category"] . "</SubCategory>";
		$data .= "\n<Brand>" . getBrandFromName($row["productName"]) . "</Brand>";
		$data .= "\n<ProductName>" . htmlspecialchars($row["productName"]) . "</ProductName>";
		$data .= "\n<DeepLink>" . $row["link"] . "</DeepLink>";
		$data .= "\n<Price>" . priceAddTax($row["price_no_tax"], $row["taxClass"]) . "</Price>";
		$data .= "\n<DeliveryPeriod>" . $deliveryTime . "</DeliveryPeriod>";
		$data .= "\n<DeliveryCosts>" . $deliveryCost . "</DeliveryCosts>";
		$data .= "\n<ProductEAN>" . $row["ean13"] . "</ProductEAN>";
		$data .= "\n<ProductDescription>" . $row["description"] . "</ProductDescription>";
		$data .= "\n<StockStatus>1</StockStatus>";
		$data .= "\n<ProductsInStock>1</ProductsInStock>";
		$data .= "\n<DeeplinkPicture>" . getImageDeepLink($row["id_image"]) . "</DeeplinkPicture>";
		$data .= "\n</Product>";
		echo "<br>Parsing product: " . $row["productName"];
	}
} else {
	$data = "No rows returned from SQL";
}
$data .= "\n</Products>";
// Write the data to file
echo "<br>Saving to file.";
fwrite($fp, $data);
// Close the MySQL connection
mysqli_close($mysqli);
// Function to get a category name based on ID
function getCategoryName($id, $mysqli) {
	$sql = "SELECT name FROM ps_category_lang WHERE id_category = " . $id;
	$result = $mysqli->query($sql) or die($mysqli->error.__LINE__);
	if($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		return $row['name'];
	}
	return '';
}
// Function to get the first word of a product and set it as a brand
function getBrandFromName($data) {
	$arr = explode(" ", $data);
	return $arr[0];
}
// The price is without tax, add the corresponding tax to the price
function priceAddTax($price, $class) {
	if($class == $taxId14) {
		return round($price * 1.14, 2);
	} elseif($class == $taxId24) {
		return round($price * 1.24, 2);
	} elseif($class == $taxId10) {
        return round($price * 1.10, 2);
    }
}
// Build the image deep URL based on the ID
function getImageDeepLink($id) {
	if(isset($id)) {
		$arr = str_split($id);
		$url = $urlimg . "/p";
		// The image location is based on it's ID. For example, if the ID is 1234, the location is /img/p/1/2/3/4/1234.jpg
		for($i = 0; $i < count($arr); $i++) {
			$url .= "/";
			$url .= $arr[$i];
			if($i == (count($arr) - 1)) {
				$url .= "/";
				for ($ii = 0; $ii < count($arr); $ii++) {	
					$url .= $arr[$ii];
				}
			}
		}
		$url .= ".jpg";
		return $url;
	}else {
		return '';
	}
}
?>
