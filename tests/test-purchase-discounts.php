<?php
/** Standalone Regressionstest; WooCommerce wird für wichtige PHP-Hooks simuliert. */
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define( 'ABSPATH', '/tmp/' );
$OPTIONS = [ 'alw_gc_product_id' => 777, 'alw_gc_settings' => [] ];
$NOTICES = [];
function get_option( $key, $default = false ) { global $OPTIONS; return $OPTIONS[$key] ?? $default; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function absint($n) { return abs((int)$n); }
function wc_format_decimal($v, $dec=2) { return number_format((float)$v,$dec,'.',''); }
function wc_add_notice($m,$type) { global $NOTICES; $NOTICES[] = [$m,$type]; }
function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function wc_price($v,$opts=[]) { return $v.' €'; }
class ItemPrice { public $price; public function __construct($p) { $this->price=$p; } public function get_price() { return $this->price; } }
class CartStub { public $items=[]; public $coupons=[]; public $fees=[]; public function get_cart(){return $this->items;} public function get_applied_coupons(){return $this->coupons;} public function get_fees(){return $this->fees;} }
class TestWC { public $cart; public function __construct($cart){$this->cart=$cart;} }
function WC(){ global $WC; return $WC; }
class ErrorStub{ public $codes=[];public function add($key,$message){$this->codes[$key]=$message;} }
class LineStub { public $p,$amt,$total,$subtotal,$q;public function __construct($p,$amt,$total,$subtotal,$q=1){$this->p=$p;$this->amt=$amt;$this->total=$total;$this->subtotal=$subtotal;$this->q=$q;}public function get_product_id(){return $this->p;}public function get_meta($key,$single){return $key==='_alw_gc_amount_cents'?$this->amt:'';}public function get_total(){return $this->total;}public function get_subtotal(){return $this->subtotal;}public function get_quantity(){return $this->q;}}
class FeeStub { public $amount=0;private $meta;public function __construct($amount,$meta=''){ $this->amount=$amount;$this->meta=$meta; }public function get_total(){return $this->amount;} public function get_meta($key,$single){return $key==='_alw_gc_payment'?$this->meta:'';} }
class OrderStub{public $items=[],$fees=[],$coupons=[];public function get_items($type){return $type==='fee'?$this->fees:$this->items;}public function get_coupon_codes(){return $this->coupons;}}
require __DIR__.'/../includes/class-alw-gift-cards.php';
function eq($condition,$msg){if(!$condition){fwrite(STDERR,"FEHLER: $msg\n"); exit(1);}}
function gift($price=50){return [ 'product_id'=>777,'quantity'=>1,'alw_gc_amount_cents'=>5000,'data'=>new ItemPrice($price),'line_total'=>50,'line_subtotal'=>50 ];}
function normal(){return ['product_id'=>45,'quantity'=>1,'data'=>new ItemPrice(75)];}
$cart=new CartStub();$WC=new TestWC($cart);
$settings=ALW_Gift_Cards::settings();eq(strpos($settings['important_notice'],"\n")!==false,'Default-Hinweis hat keinen Zeilenumbruch');
$method=new ReflectionMethod(ALW_Gift_Cards::class,'important_notice_html');$method->setAccessible(true);
$OPTIONS['alw_gc_settings']['important_notice']="Bitte <script>alert(1)</script> nicht ausführen";
$html=$method->invoke(null,false);eq(strpos($html,'<script>')===false && strpos($html,'&lt;script&gt;')!==false,'Hinweis muss escaped sein');
$OPTIONS['alw_gc_settings']['important_notice']='';eq($method->invoke(null,false)==='','Leerer Text soll Hinweis verstecken');
$cart->items=[gift()];
foreach(['percent','fixed_cart','fixed_product','custom'] as $kind){$blocked=false;try{ALW_Gift_Cards::protect_gift_purchase_from_fixed_cart_coupons(true,(object)['type'=>$kind]);}catch(Exception $e){$blocked=true;}eq($blocked,"Coupontyp $kind nicht blockiert");}
$cart->items=[normal()];eq(ALW_Gift_Cards::protect_gift_purchase_from_fixed_cart_coupons(true,(object)['type'=>'percent'])===true,'Normale Ware darf Rabatte erhalten');
$cart->items=[gift()];$cart->coupons=['SALE10'];eq(ALW_Gift_Cards::protect_product(true,777,1)===false,'Gutscheinkauf zu vorhandenen Coupons muss gesperrt sein');
$cart->items=[normal()];eq(ALW_Gift_Cards::protect_product(true,45,1)===true,'Normale Käufe dürfen nicht gesperrt werden');
$cart->items=[gift()];ALW_Gift_Cards::validate_no_discount_purchase();eq(count($NOTICES)>0,'Gutscheinkauf bei vorhandenem Coupon muss Notice anzeigen');
$err=new ErrorStub();ALW_Gift_Cards::checkout_discount_validation([], $err);eq(isset($err->codes['alw_gc_discount_purchase']),'Checkout muss Discount-Kauf blockieren');
$cart->coupons=[];$cart->items=[gift(40)];$err=new ErrorStub();ALW_Gift_Cards::checkout_discount_validation([], $err);eq(isset($err->codes['alw_gc_modified_price']),'Veränderter Gutscheinpreis muss blockiert werden');
$cart->items=[gift()];$cart->fees=[new FeeStub(-5)];$err=new ErrorStub();ALW_Gift_Cards::checkout_discount_validation([], $err);eq(isset($err->codes['alw_gc_discount_purchase']),'Negativer automatischer Rabatt/fee muss blockiert werden');
$o=new OrderStub();$o->items=[new LineStub(777,5000,'50','50')];$o->fees=[];$o->coupons=[];ALW_Gift_Cards::validate_gift_order_amounts($o,[]);
$o->items=[new LineStub(777,5000,'45','50')];$blocked=false;try{ALW_Gift_Cards::validate_gift_order_amounts($o,[]);}catch(Exception $e){$blocked=true;}eq($blocked,'Bestellung mit rabattiertem Gutscheinwert muss blockiert werden');
$o->items=[new LineStub(777,5000,'50','50')];$o->coupons=['SALE10'];$blocked=false;try{ALW_Gift_Cards::validate_gift_order_amounts($o,[]);}catch(Exception $e){$blocked=true;}eq($blocked,'Order mit Coupon muss blockiert werden');
$o->coupons=[];$o->fees=[new FeeStub(-10)];$blocked=false;try{ALW_Gift_Cards::validate_gift_order_amounts($o,[]);}catch(Exception $e){$blocked=true;}eq($blocked,'Order mit negativem Rabattfee muss blockiert werden');
$o->fees=[new FeeStub(-10,'yes')];ALW_Gift_Cards::validate_gift_order_amounts($o,[]);
$cart->items=[normal()];$cart->coupons=['SALE10'];$err=new ErrorStub();ALW_Gift_Cards::checkout_discount_validation([], $err);eq(empty($err->codes),'Nur reguläre Ware darf weiterhin Coupon verwenden');
// v1.8.0: Gemischter Warenkorb -> Rabatte nur auf reguläre Waren.
$cart->items = [gift(), normal()];
$cart->fees = [];
$cart->coupons = ['SALE10'];
foreach (['percent','fixed_cart','fixed_product','custom'] as $kind) {
    eq(ALW_Gift_Cards::protect_gift_purchase_from_fixed_cart_coupons(true, (object)['type' => $kind]) === true, "Coupontyp $kind muss bei regulären Waren zugelassen sein");
}
$NOTICES = [];
ALW_Gift_Cards::validate_no_discount_purchase();
eq(count($NOTICES) === 0, 'Gemischter Warenkorb darf wegen eines normalen Coupons keine Kauf-Fehlermeldung erzeugen');
$err = new ErrorStub();
ALW_Gift_Cards::checkout_discount_validation([], $err);
eq(empty($err->codes), 'Gemischter Warenkorb darf Checkout mit normalen Coupons nicht blockieren');
$allow = new ReflectionProperty(ALW_Gift_Cards::class,'allow_add');
$allow->setAccessible(true);
$allow->setValue(null,true);
eq(ALW_Gift_Cards::protect_product(true,777,1) === true, 'Geschenkgutschein muss zu regulären rabattfähigen Produkten hinzugefügt werden dürfen');
$allow->setValue(null,false);
// WC_Discounts muss Gutscheinzeilen vor Prozent- und Fest-Warenkorbrabatten herausfiltern.
class CouponProductStub { private $id; public function __construct($id){$this->id=$id;}public function get_id(){return $this->id;} }
$giftItem = (object)['product'=>new CouponProductStub(777),'object'=>['product_id'=>777]];
$regularItem = (object)['product'=>new CouponProductStub(45),'object'=>['product_id'=>45]];
$giftFromObject = (object)['product'=>new CouponProductStub(0),'object'=>['product_id'=>777]];
foreach (['percent','fixed_cart','fixed_product'] as $kind) {
    $filtered = ALW_Gift_Cards::exclude_gift_from_coupon_items([$giftItem, $regularItem, $giftFromObject], (object)['type'=>$kind]);
    eq(count($filtered) === 1 && reset($filtered) === $regularItem, "Coupontyp $kind hat Gutscheinposition nicht korrekt ausgeschlossen");
}
eq(ALW_Gift_Cards::exclude_gift_purchase_from_discounts(true,new CouponProductStub(777),null,[]) === false, 'Produkt-Rabatt auf Gutschein nicht gesperrt');
eq(ALW_Gift_Cards::exclude_gift_purchase_from_discounts(true,new CouponProductStub(45),null,[]) === true, 'Reguläres Produkt darf Rabatt erhalten');
eq(ALW_Gift_Cards::exclude_gift_coupon_discount_amount(5,25,['product_id'=>777],false,null) === 0, 'Gutscheinbetrag wurde durch Rabatt verringert');
eq(ALW_Gift_Cards::exclude_gift_coupon_discount_amount(5,25,['product_id'=>45],false,null) === 5, 'Rabatt auf reguläres Produkt wurde ungewollt gesperrt');
$o = new OrderStub();
$o->items = [new LineStub(777,5000,50,50),new LineStub(45,0,22.5,25)];
$o->coupons = ['SALE10'];
ALW_Gift_Cards::validate_gift_order_amounts($o,[]);
$o->items = [new LineStub(777,5000,49,50),new LineStub(45,0,22.5,25)];
$blocked = false;
try { ALW_Gift_Cards::validate_gift_order_amounts($o,[]); } catch (Exception $e) { $blocked = true; }
eq($blocked,'Gutscheinbetrag darf auch bei Rabatt auf reguläre Ware nicht reduziert werden');
$o->items = [new LineStub(777,5000,50,50),new LineStub(45,0,22.5,25)];
$o->fees = [new FeeStub(-5)];
$blocked = false;
try { ALW_Gift_Cards::validate_gift_order_amounts($o,[]); } catch (Exception $e) { $blocked = true; }
eq($blocked,'Nicht zugeordnete negative Gebühren dürfen weiterhin nicht den Gutscheinpreis mindern');
$method = new ReflectionMethod(ALW_Gift_Cards::class,'purchase_notices_html');
$method->setAccessible(true);
$OPTIONS['alw_gc_settings']['important_notice'] = 'Gutscheine nur zum Nennwert';
$html = $method->invoke(null, false);
eq(substr_count($html, '<aside') === 1, 'Kaufseite muss genau eine Hinweisbox anzeigen');
eq(strpos($html, 'Gutscheine nur zum Nennwert') !== false && strpos($html, 'Widerrufsrecht') !== false, 'Eine Hinweisbox muss editierbaren und gesetzlichen Text enthalten');
$OPTIONS['alw_gc_settings']['important_notice'] = '';
$html = $method->invoke(null, false);
eq(substr_count($html, '<aside') === 1 && strpos($html,'Widerrufsrecht') !== false, 'Gesetzlicher Hinweis muss bei leerem Zusatztext in derselben Box erhalten bleiben');
echo "PASS: Bestehende und neue Rabatt-/Hinweis-Szenarien validiert\n";
