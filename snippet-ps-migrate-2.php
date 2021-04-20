<?php
/*
ps-migrate-2 MODX snippet
Prestashop to MODX Commerce migrate, step 2/2
Jean-Philippe Dain
dev.jpdn.net

To create
Template for product and set $product_template_id


*/

################################################################################
# Configuration
################################################################################

//xmod
//limiter le nombre d'import pour les test
//$limit = 1500;
//$offset = 13187;
//$limit = null;

// Parent category for Commerce products
$parentforce = 19815;

/**
 * a flag to turn this script on and off
 */
$can_migrate = true;

$ps_database_host     = '';
//$ps_database_host     = 'localhost';
$ps_database_charset  = 'utf8';
$ps_database_name     = '';
$ps_database_prefix   = 'ps_';
$ps_database_username = '';
$ps_database_password = '';

/**
 * templates ids to use during import
 * if you would like to have different templates for posts and pages you can
 * specify that below, if not they will default to the default template id
 * if the templates don't exist they will be created.  The default template id
 * must be set.
 */

$product_template_id = 2;

//ci dessous inutile
$default_template_id = 2;
$page_template_id = 2;
$event_template_id = 2;//added for former WP Tribe Events : specific template

//limite query
$limit=5;


/*
TV ps_idproduct for former Prestashop product ID
Need to be created 'manually' in MODX
*/
//$wppostid_tv = 'wp_postid';
$psidproduct_tv = 'ps_idproduct';


/*
TV ps_catids for former Prestashop category IDs
Need to be created 'manually' in MODX
Prestashop table to query: ps_category_product
get id_category where id_product = id_product(ps_category_product) = id_product(ps_product)
e.g. 12,23,54
*/
//$categories_tv = 'wp_cat';
$pscategories_tv = 'ps_catids';


/*
TV ps_imgs for Prestashop image IDs attached to a product
Need to be created 'manually' in MODX
Prestashop table to query: ps_image
get id_image where id_product = id_product(ps_image) = id_product(ps_product)
e.g. 1252,564
*/
//$categories_tv = 'imgfeat';
$psimgs_tv = 'ps_imgs';
$psimgmain_tv = 'image';//change?


/*
TV stock for Prestashop former id_tax_rules_group attached to a product
Need to be created 'manually' in MODX
Prestashop table to query: ps_product
e.g. 2
*/
$pstaxrulesgroup_tv = 'ps_taxrulesgroup';


/*
TV stock for stock attached to a product
Need to be created 'manually' in MODX
Prestashop table to query: ps_stock_available
Query: get quantity where  id_product(ps_stock_available) = id_product(ps_product)
e.g. 2
*/
$stock_tv = 'stock';

/**
 * $post_document_parent
 * the MODx document parent id, or array of values (if the page needs to be
 * created) for WP posts. If set to 0 the parent will be the document root.
 * If the id does not exist, a document will be created.
 *
 * integer or array (see modResource for possible values)
 * example array: array('pagetitle'=>'Blog','context_key'=>'web')
 * !! don't forget the context_key or your pages won't show up in your context
 */
if (!$can_migrate) die('Migration cannot be processed, this script is off.');
if (!is_int($default_template_id)) die('The default template id must be set. Check that $default_template_id is set and is an integer.');

// Include the xpdo and modx classes
require_once (MODX_CORE_PATH . 'xpdo/xpdo.class.php');
require_once (MODX_CORE_PATH . 'model/modx/modx.class.php');

// Instantiate a new modx object.  MODx inherits from xpdo so we can use it
// like an xpdo object, but it has the extra functions needed for saving content.
// Thanks to Shaun McCormick for writing docs on this.
$modx = new modX();
$modx->initialize('web');

// Now instantiate a new xpdo object and add our Prestashop package.  This gives
// us the ability to make queries on the Prestashop database as an xpdo object.
$ps = new xPDO('mysql:host=' . $ps_database_host .
        ';dbname=' . $ps_database_name .
        ';charset=' . $ps_database_charset,
    $ps_database_username,
    $ps_database_password );

echo $o = ($ps->connect()) ? 'Connected' : 'Not Connected';

//$can_migrate_ps = $wp->addPackage('Prestashop','../',$ps_database_prefix);
$can_migrate_ps = $ps->addPackage('presta',MODX_CORE_PATH.'components/',$ps_database_prefix);

if (!$can_migrate_ps) die('Prestashop Package could not be loaded.');

// set up our default templates in an array
if (!empty($product_template_id))
  $templates['product'] = $product_template_id;

// the base query
if($limit){
    echo "<br>Limite de la requete de base : " . $limit;
    $query = $ps->newQuery('Product');
    $query->limit($limit);
    $query->sortby('id_product','desc');
    //print_r($query);
    $products = $ps->getCollection('Product',$query);
} else {
    //update a faire ici
    $query = $ps->newQuery('Product');
    $query->where(array('post_type' => 'post','post_status' => 'publish'));
    $products = $ps->getCollection('Product',$query);
}

//$post_count = 0;

foreach($products as $product)
{
    $resource = '';
    $template_id = '';
    
    $id_product = $product->get('id_product');
    $id_tax_rules_group = $product->get('id_tax_rules_group');
    $price = $product->get('price');//HT

    /*
    Memo taxes 
    17 	Rule 19 %	
	18 	Rule 7 		
	19 	Rule 16 		
	20 	Rule 5 		
	21 	Rule test
	*/
	
    switch($id_tax_rules_group){
    case '0':
        //produit non dispo pour Presta cas à prévoir
      $taxcoef = 0;
      break;
    case '17':
        //rule 19 -> id_tax 15
      $taxcoef = 19;
      break;
    case '18':
        // rule 7 -> id_tax 16
      $taxcoef = 7;
      break;
    case '19':
        //rule 6 -> id_tax 17
      $taxcoef = 16;
      break;
    case '20':
        //rule 5 -> id_tax 18
      $taxcoef = 5;
      break;
    default:
      $taxcoef = 0;
    }
    
    // all inclusive tax mode for Commerce, recalculate price
    $price_todisplay = number_format(round((($taxcoef / 100) * $price) + $price,2),2);

    // test
    echo "<br>------------------------------------";
    echo "<br>id_product: " . $id_product;
    echo "<br>id_tax_rules_group: " . $id_tax_rules_group;
    echo "<br>price (HT): " . $price;
    echo "<br>price (à afficher, recalculé): " . $price_todisplay;
    
    
    //Get product name
    $query = $ps->newQuery('ProductLang');
    $query->where(array('id_product' => $id_product));
    $names = $ps->getCollection('ProductLang',$query);
    $name_strings  = array();
    $desc_strings  = array();
    $descshort_strings  = array();
    $linkrw_strings  = array();
    foreach($names as $name){
      $name_strings[] = $name->get('name');
      $desc_strings[] = $name->get('description');
      $descshort_strings[] = $name->get('description_short');
      $linkrw_strings[] = $name->get('link_rewrite');
    }
    $name_string = $name_strings[0];// value for pagetitle native TV
    echo "<br>Product name (name_strings): " . $name_string;
    $desc_string = $desc_strings[0];// value for content native TV (html content)
    //echo "<br>Product description (desc_string): " . $desc_string;//ok
    $descshort_string = $descshort_strings[0];// value for introtext native TV?
    //echo "<br>Product description (descshort_string): " . $descshort_string;//ok
    $alias = $linkrw_strings[0];// value for alias native TV
    echo "<br>Product alias  (alias): " . $alias;
    
    /* Get cats IDs as string, separated comma list for $psidproduct_tv */
    $query = $ps->newQuery('CategoryProduct');
    $query->where(array('id_product' => $id_product));
    $cats = $ps->getCollection('CategoryProduct',$query);
    $cats_ids  = array();
    foreach($cats as $cat){
      $cats_ids[] = $cat->get('id_category');
    }
    $catsAsString = join(',',$cats_ids);
    echo "<br>catsAsString: " . $catsAsString;
    
    /* Get images IDs as string, separated comma list for $psimgs_tv and main product image (Commerce 'image' TV) */
    $query = $ps->newQuery('Image');
    $query->where(array('id_product' => $id_product));
    $query->sortby('position','asc');
    $imgs = $ps->getCollection('Image',$query);
    $imgs_ids  = array();
    foreach($imgs as $img){
      $ismain = $img->get('cover');
      $imgs_ids[] = $img->get('id_image');
      if($ismain==1){
          $imgMain = $img->get('id_image');
      }
      
    }
    $imgsAsString = join(',',$imgs_ids);
    echo "<br>Main product image: " . $imgMain;// to set TV image
    echo "<br>imgsAsString: " . $imgsAsString;//for further gallery setup
    // TODO : build new images path to use moreGallery to create specific resource galleries?
    
    //Get stock
    $query = $ps->newQuery('StockAvailable');
    $query->where(array('id_product' => $id_product));
    $stocks = $ps->getCollection('StockAvailable',$query);
    $stock_ids  = array();
    foreach($stocks as $stock){
      $stock_ids[] = $stock->get('quantity');
    }
    $quantity = $stock_ids[0];
    echo "<br>quantity: " . $quantity;//if variations exists, total
    
    //TODO: check if product has variations and use a Commerce class to create product variations?
    
}
