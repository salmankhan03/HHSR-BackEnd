<?php

namespace App\Http\Controllers\API;


use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\OrderDetail;
use App\MenuDetail;
use App\Models\RoomDetail;
use App\SpecialInstructionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use App\Models\TableDetail;
use Validator;
use App\User;
use App\ItemOption;
use App\ItemPreference;

class DinningController extends Controller
{

  public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "room_no" => "required",
                "password" => "required"
            ], [
                "room_no.required" => "Please enter room no",
                "password.required" => "Please enter password",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $room_no = $request->input("room_no");
            $password = $request->input("password");
            $user = RoomDetail::where("room_name", $room_no)->where("password", $password)->first();
            
            $last_date = "";
            $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
            if ($menu_data) {
                $last_date = $menu_data->date;
            }
            if (!$user) {
                $user = User::where("user_name",$room_no)->first();
               
                if($user){
                    if (!\Hash::check($password, $user->password)){
                        return $this->sendResultJSON("2", "User not Found");
                    }else{
                        $role = intval($user->role_id) == 1 ? "admin" : "kitchen";
                        $user_token = $this->generate_access_token($user->id,$role);
                        return $this->sendResultJSON("1", "Successfully Login", array("room_id" => 0, "room_number" => "", "occupancy" => 0, "resident_name" => "", "language" => 0, "last_menu_date" => $last_date, "authentication_token" => $user_token,"role" => $role));
                    }
                }else{
                    return $this->sendResultJSON("2", "User not Found");
                }
            }
            
           
            if ($user->is_active == 1) {
                $user_token = $this->generate_access_token($user->id,"user");
                return $this->sendResultJSON("1", "Successfully Login", array("room_id" => $user->id, "room_number" => $user->room_name, "occupancy" => $user->occupancy, "resident_name" => $user->resident_name, "language" => intval($user->language), "last_menu_date" => $last_date, "authentication_token" => $user_token,"role" => "user"));
            } else {
                return $this->sendResultJSON("3", "User not active");
            }
          
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
    
    function generate_access_token($user_id,$role)
    {
        $token = json_encode(array(
            'user_id' => $user_id,
            'timestamp' => Carbon::Now()->timestamp,
            'role' => $role
        ));
        return 'Bearer ' . base64_encode(base64_encode($token));
    }
    
    public function getRoomList()
    {
        $rooms = RoomDetail::where("is_active", 1)->get();
        $rooms_array = array();
        foreach (count($rooms) > 0 ? $rooms : array() as $r) {
            array_push($rooms_array, array("id" => $r->id, "name" => $r->room_name,"occupancy" => $r->occupancy));
        }
        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date","desc")->first();
        if($menu_data){
            $last_date = $menu_data->date;
        }
        return $this->sendResultJSON('1', '', array('rooms' => $rooms_array,'last_menu_date' => $last_date));
    }

    public function getOrderList(Request $request)
    {
        if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
        }
       
        $room_id = intval($request->input('room_id'));
        
        $date = $request->input('date');
        $sub_cat_details = array();
        $cat_array = array();
        $breakfast = $lunch = $dinner = array();
        
        // $items = "";
        // $day = Carbon::parse($date)->format("l");
        // if ($day == "Sunday") {
        //     $items = "1,4,5,6,7,20,28,38,15,18,3,52,17,16";
        // } elseif ($day == "Monday") {
        //     $items = "9,4,5,6,7,21,29,39,15,17,18,46,53,16";
        // } elseif ($day == "Tuesday") {
        //     $items = "4,5,6,7,15,17,18,16,10,22,31,41,47,54";
        // } elseif ($day == "Wednesday") {
        //     $items = "4,5,6,7,15,17,18,16,11,23,32,42,48,55";
        // } elseif ($day == "Thursday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 12, 24, 34, 43, 49, 56";
        // } elseif ($day == "Friday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 13, 25, 36, 44, 50, 57";
        // } elseif ($day == "Saturday") {
        //     $items = "4, 5, 6, 7, 15, 17, 18, 16, 14, 27, 37, 45, 51, 58";
        // }

        $items = array();
        $menu_data = MenuDetail::selectRaw("items")->whereRaw("date = '" . $date . "' OR is_allDay = 1")->get();
        foreach (count($menu_data) > 0 ? $menu_data : array() as $m) {
            $menu_items = json_decode($m->items, true);
            foreach (count($menu_items) > 0 ? $menu_items : array() as $mi) {
                if (count($mi) > 0)
                    array_push($items, implode(",", $mi));
            }

        }
        $option_details = $preference_details = array();
        $items = implode(",",$items);
        if($items != "") {
            $options = ItemOption::all();
            foreach (count($options) > 0 ? $options : array() as $o) {
                $option_details[intval($o->id)] = array("option_name" => $o->option_name,"option_name_cn" => ($o->option_name_cn != null ? $o->option_name_cn : $o->option_name));
            }
            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname,"name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }
           
            
            $category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", 0)->whereRaw("item_details.id IN (".$items.")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id","asc")->orderBy("item_details.id","asc")->get();
        
            foreach (count($category_data) > 0 ? $category_data : array() as $c) {
                if (!isset($cat_array[$c->id])) {
                    $cat_array[$c->id] = array("cat_id" => $c->id, "cat_name" => $c->cat_name, "chinese_name" => $c->category_chinese_name, "items" => array(), "type" => $c->type);
                }
                $options = array();
                
                $preference = array();
                
                 if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference")->where("room_id", $room_id)->where("date", $date)->where("item_id", $c->item_id)->first();
                    
                    if($c->options != ""){
                       $c_options = json_decode($c->options);
                       foreach (count($c_options) > 0 ? $c_options : array() as $co){
                           $co = intval($co);
                           if($option_details[$co]){
                                $options[$co] = array("id" => $co,"name" => $option_details[$co]['option_name'],"c_name" => $option_details[$co]['option_name_cn'],"is_selected" => ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 :0) : 0)); 
                           }
                           
                       }
                    }
                    
                    if($c->preference != ""){
                      $c_preferences = json_decode($c->preference);
                      foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp){
                          $cp = intval($cp);
                          if($preference_details[$cp]){
                                $preference[$cp] = array("id" => $cp,"name" => $preference_details[$cp]['name'],"c_name" => $preference_details[$cp]['name_cn'],"is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp,explode(",",$order_data->preference)) ? 1 : 0) : 0)); 
                          }
                           
                      }
                   }
                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name,"options" => array_values($options),"preference" => array_values($preference), "item_image" => Voyager::image($c->item_image), "qty" => ($order_data ? $order_data->quantity : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                 } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $c->item_id)->groupBy("item_id")->first();
                    
                     if($c->options != "") {
                        $c_options = json_decode($c->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co){
                            $co = intval($co);
                            if($option_details[$co]){
                                $options[$co] = array("id" => $co,"name" => $option_details[$co]['option_name'],"c_name" => $option_details[$co]['option_name_cn'],"is_selected" => 0,"item_count" => OrderDetail::where("date", $date)->where("item_id", $c->item_id)->where("item_options",$co)->count());
                            }

                        }
                    }
                    
                    array_push($cat_array[$c->id]["items"], array("type" => "item", "item_id" => $c->item_id, "item_name" => $c->item_name, "chinese_name" => $c->item_chinese_name,"is_expanded" => count(array_values($options)) > 0 ? 1 :0,"options" => array_values($options),"preference" => array_values($preference), "item_image" => Voyager::image($c->item_image), "qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => 0));
                }
            }
            $sub_category_data = CategoryDetail::join("item_details", "item_details.cat_id", "=", "category_details.id")->selectRaw("category_details.*,item_details.id as item_id,item_details.item_name,item_details.item_image,item_details.item_chinese_name,item_details.options,item_details.preference")->where("category_details.parent_id", "!=", 0)->whereRaw("item_details.id IN (" . $items . ")")->whereRaw("item_details.deleted_at IS NULL")->orderBy("category_details.id", "asc")->orderBy("item_details.id","asc")->get();
            foreach (count($sub_category_data) > 0 ? $sub_category_data : array() as $sc) {
                if (!isset($sub_cat_details[$sc->id])) {
                    $sub_cat_details[$sc->id] = array("cat_id" => $sc->id, "cat_name" => $sc->cat_name, "chinese_name" => $sc->category_chinese_name, "parent_id" => $sc->parent_id, "items" => array());
                }
                if (!isset($cat_array[$sc->parent_id])) {
                    if ($sc->parentData) {
                        $cat_array[$sc->parent_id] = array("cat_id" => $sc->parentData->id, "cat_name" => $sc->parentData->cat_name, "chinese_name" => $sc->parentData->category_chinese_name, "items" => array(), "type" => $c->type);
                    }
                }
               $options = array();
                
                $preference = array();
                
                if ($room_id != 0) {
                    $order_data = OrderDetail::selectRaw("id,quantity,item_options,preference")->where("room_id", $room_id)->where("date", $date)->where("item_id", $sc->item_id)->first();
                    
                    if($sc->options != ""){
                       $c_options = json_decode($sc->options);
                       foreach (count($c_options) > 0 ? $c_options : array() as $co){
                           $co = intval($co);
                           if($option_details[$co]){
                                $options[$co] = array("id" => $co,"name" => $option_details[$co]['option_name'],"c_name" => $option_details[$co]['option_name_cn'],"is_selected" =>  ($order_data && $order_data->item_options != null ? ($co == $order_data->item_options ? 1 :0) : 0)); 
                           }
                           
                       }
                    }
                    
                     if($sc->preference != ""){
                      $c_preferences = json_decode($sc->preference);
                      foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp){
                          $cp = intval($cp);
                          if($preference_details[$cp]){
                                $preference[$cp] = array("id" => $cp,"name" => $preference_details[$cp]['name'],"c_name" => $preference_details[$cp]['name_cn'],"is_selected" => ($order_data && $order_data->preference != null ? (in_array($cp,explode(",",$order_data->preference)) ? 1 : 0) : 0)); 
                          }
                           
                      }
                    }
                
                    array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name,"chinese_name" => $sc->item_chinese_name, "item_image" => Voyager::image($sc->item_image),"options" => array_values($options),"preference" => array_values($preference), "qty" => ($order_data ? $order_data->quantity : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                } else {
                    $order_data = OrderDetail::selectRaw("sum(quantity) as quantity")->where("date", $date)->where("item_id", $sc->item_id)->groupBy("item_id")->first();
                    
                     if($sc->options != ""){
                        $c_options = json_decode($sc->options);
                        foreach (count($c_options) > 0 ? $c_options : array() as $co){
                            $co = intval($co);
                            if($option_details[$co]){
                                $options[$co] = array("id" => $co,"name" => $option_details[$co]['option_name'],"c_name" => $option_details[$co]['option_name_cn'],"is_selected" => 0,"item_count" => OrderDetail::where("date", $date)->where("item_id",$sc->item_id)->where("item_options",$co)->count());
                            }
                        }
                    }
                    
                   array_push($sub_cat_details[$sc->id]["items"], array("item_id" => $sc->item_id, "item_name" => $sc->item_name,"chinese_name" => $sc->item_chinese_name, "item_image" => Voyager::image($sc->item_image),"is_expanded" => count(array_values($options)) > 0 ? 1 :0,"options" => array_values($options), "preference" => array_values($preference),"qty" => ($order_data ? intval($order_data->quantity) : 0), "comment" => "", "order_id" => ($order_data ? $order_data->id : 0)));
                }
            }
            foreach (count($sub_cat_details) > 0 ? $sub_cat_details : array() as $sc) {
                if (isset($cat_array[$sc['parent_id']])) {
                    array_push($cat_array[$sc['parent_id']]["items"], array("type" => "sub_cat", "item_id" => $sc["cat_id"], "item_name" => $sc["cat_name"],"chinese_name" => $sc["chinese_name"],"options" => [], "preference" => [], "item_image" => "", "qty" => 0, "comment" => "", "order_id" => 0));
                    foreach (count($sc["items"]) > 0 ? $sc["items"] : array() as $sci) {
                        $sc_item = array("type" => "sub_cat_item", "item_id" => $sci["item_id"], "item_name" => $sci["item_name"], "chinese_name" => $sci["chinese_name"], "item_image" => $sci["item_image"],"options" => $sci["options"],"preference" => $sci["preference"], "qty" => $sci["qty"], "comment" => $sci["comment"], "order_id" => $sci["order_id"]);
                        if(isset($sci["is_expanded"])){
                            $sc_item["is_expanded"] = $sci["is_expanded"];
                        }
                        array_push($cat_array[$sc['parent_id']]["items"], $sc_item);
                    }
                    //, "items" => array_values($sc["items"]
                }
            }
        }
        foreach (count($cat_array) > 0 ? $cat_array : array() as $c) {
            $type = intval($c['type']);
            unset($c['type']);
            if ($type == 1) {
                array_push($breakfast, $c);
            } else if ($type == 2) {
                array_push($lunch, $c);
            } else if ($type == 3) {
                array_push($dinner, $c);
            }
        }
       
        $last_date = "";
            $menu_data = MenuDetail::select("date")->orderBy("date","desc")->first();
        if($menu_data){
            $last_date = $menu_data->date;
        }
        
        $instruction = "";
        $spi_data = RoomDetail::select("special_instrucations")->where("id", $room_id)->first();
        if($spi_data)
            $instruction = $spi_data->special_instrucations;
            
        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'last_menu_date' => $last_date,'special_instruction' => $instruction));
    }

    public function getItemList(Request $request)
    {
        $cat_id = $request->input('cat_id');
        $date = $request->input('date');
        if ($cat_id != "" && $date != "") {
            $date_query = "";
            if ($date == "all") {
                $date_query = "(day = 'all')";
            } else {
                $date_query = "(day = '" . strtolower(Carbon::parse($date)->format("l")) . "' OR day = 'all')";
            }
            $item_details = ItemDetail::where("cat_id", $cat_id)->whereRaw($date_query)->get();
            $item_data = array();
            foreach (count($item_details) > 0 ? $item_details : array() as $i) {
                array_push($item_data, array("item_id" => $i->id, "item_name" => $i->item_name, "item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => 0, "comment" => "", "order_id" => 0));
            }
            return $this->sendResultJSON('1', '', array('items' => $item_data));
        }
    }

    public function updateOrder(Request $request)
    {
        if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
        }
        $room_id = $request->input('room_id');
        $date = $request->input('date');
        $special_instructions = $request->input('special_instructions');
        $remember = $request->input('remember_instruction');
        $item_array = $order_array = array();
        if ($room_id != "" && $date != "") {
            if ($request->input('orders_to_change') && $request->input('orders_to_change') != "") {
                $new_data = json_decode($request->input('orders_to_change'));
                foreach (count($new_data) > 0 ? $new_data : array() as $n) {
                    $n->order_id = intval($n->order_id);
                    $n->qty = intval($n->qty);
                    if ($n->order_id == 0) {
                        if($n->qty != 0){
                            $order = new OrderDetail();
                            $order->room_id = $room_id;
                            $order->date = $date;
                            $order->item_id = $n->item_id;
                            $order->item_options = $n->item_options;
                            $order->preference = $n->preference;
                            $order->quantity = $n->qty;
                            $order->comment = "";
                            $order->status = 0;
                            $order->save();
                            
                            array_push($item_array,$n->item_id);
                            array_push($order_array,$order->id);
                        }
                    } else {
                        if ($n->qty == 0) {
                            OrderDetail::where("id", $n->order_id)->delete();
                            array_push($item_array,$n->item_id);
                            array_push($order_array,0);
                        } else {
                            OrderDetail::where("id", $n->order_id)->update(['quantity' => $n->qty,'item_options' => $n->item_options,'preference' => $n->preference, 'comment' => ""]);
                        }

                    }
                }
            }
            return $this->sendResultJSON('1', 'success',array('item_id' => $item_array,'order_id' =>$order_array));
        }
    }


    public function getCategoryWiseData(Request $request)
    {
        $date = $request->input('date');
        $menu_details = MenuDetail::where("date", $date)->first();
        $breakfast = $lunch = $dinner = array();
        $breakfast_rooms_array = $lunch_rooms_array = $dinner_rooms_array = array();
        $rooms_array = array();
        $cat_id = array(
            1 => 'BA',
            2 => 'LS',
            7 => 'LD',
           13 => 'DD',
        );
        $alternative = array(4, 8, 11);
        $ab_alternative = array(5, 3);
        if ($menu_details) {
            $menu_items = json_decode($menu_details->items, true);
            $all_rooms = RoomDetail::where("is_active", 1)->get();
            $is_first = true;
            foreach (count($all_rooms) > 0 ? $all_rooms : array() as $r) {
                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["breakfast"]) . ")")->orderBy("cat_id")->get();
                $count = 1;
                $items = array();
                if (!isset($breakfast_rooms_array[$r->id]))
                    $breakfast_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());
                foreach (count($all_items) > 0 ? $all_items : array() as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "B" . $count : $cat_id[$a->cat_id]);
                    if (!isset($breakfast[$a->id]))
                        $breakfast[$a->id] = array();

                    if ($is_first) {
                        $breakfast[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                    }
                    $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                    if ($order_data) {
                        $breakfast[$a->id]["total_count"] += intval($order_data->quantity);
                        array_push($items, intval($order_data->quantity));
                    } else {
                        array_push($items, 0);
                    }
                    if (in_array($a->cat_id, $alternative)) $count++;
                }
                $breakfast_rooms_array[$r->id]["quantity"] = $items;

                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["lunch"]) . ")")->orderBy("cat_id")->get();
                $ab_count = 'A';
                $count = 1;
                $items = array();
                if (!isset($lunch_rooms_array[$r->id]))
                    $lunch_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());
                foreach (count($all_items) > 0 ? $all_items : array() as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "L" . $count : (in_array($a->cat_id, $ab_alternative) ? "L" . $ab_count : $cat_id[$a->cat_id]));
                    if (!isset($lunch[$a->id]))
                        $lunch[$a->id] = array();

                    if ($is_first) {
                        $lunch[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                    }
                    $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                    if ($order_data) {
                        $lunch[$a->id]["total_count"] += intval($order_data->quantity);
                        array_push($items, intval($order_data->quantity));
                    } else {
                        array_push($items, 0);
                    }
                    if (in_array($a->cat_id, $alternative)) $count++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';

                }
                $lunch_rooms_array[$r->id]["quantity"] = $items;

                $all_items = ItemDetail::selectRaw("id,item_name,cat_id")->whereRaw("id IN (" . implode(",", $menu_items["dinner"]) . ")")->orderBy("cat_id")->get();
                $count = 1;
                $ab_count = 'A';
                $items = array();
                if (!isset($dinner_rooms_array[$r->id]))
                    $dinner_rooms_array[$r->id] = array("room_no" => $r->room_name, "quantity" => array());
                foreach (count($all_items) > 0 ? $all_items : array() as $a) {
                    $title = (in_array($a->cat_id, $alternative) ? "D" . $count : (in_array($a->cat_id, $ab_alternative) ? "D" . $ab_count : $cat_id[$a->cat_id]));
                    if (!isset($dinner[$a->id]))
                        $dinner[$a->id] = array();

                    if ($is_first) {
                        $dinner[$a->id] = array("item_name" => $title, "real_item_name" => $a->item_name, "total_count" => 0);
                    }
                    $order_data = OrderDetail::select("quantity")->where("date", $date)->where("room_id", $r->id)->where("item_id", $a->id)->first();
                    if ($order_data) {
                        $dinner[$a->id]["total_count"] += intval($order_data->quantity);
                        array_push($items, intval($order_data->quantity));
                    } else {
                        array_push($items, 0);
                    }
                    if (in_array($a->cat_id, $alternative)) $count++;
                    if (in_array($a->cat_id, $ab_alternative)) $ab_count = 'B';
                }
                $dinner_rooms_array[$r->id]["quantity"] = $items;
                $is_first = false;
                 array_push($rooms_array, array("room_id" => $r->id, "room_name" => $r->room_name, "has_special_ins" => ($r->special_instrucations != null ? 1 : 0),"has_breakfast_order" => array_sum($breakfast_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0, "has_lunch_order" => array_sum($lunch_rooms_array[$r->id]["quantity"]) > 0 ? 1 : 0, "has_dinner_order" => array_sum($dinner_rooms_array[$r->id]["quantity"]) > 0 ? 1 :0));
            }
        }
        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date", "desc")->first();
        if ($menu_data) {
            $last_date = $menu_data->date;
        }
        
        return $this->sendResultJSON('1', '', array('breakfast_item_list' => array_values($breakfast), 'lunch_item_list' => array_values($lunch), 'dinner_item_list' => array_values($dinner), 'report_breakfast_list' => array_values($breakfast_rooms_array), 'report_lunch_list' => array_values($lunch_rooms_array), 'report_dinner_list' => array_values($dinner_rooms_array),'rooms_list' => $rooms_array,"last_menu_date" => $last_date));

    }
    
    public function getUserData(){
        if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
        }
        $user = session("user_details");
        $role = session("role");
     
        $last_date = "";
        $menu_data = MenuDetail::select("date")->orderBy("date","desc")->first();
        if($menu_data){
            $last_date = $menu_data->date;
        }
        if($role == "user"){
            return $this->sendResultJSON('1', '', array("occupancy" => $user->occupancy,"resident_name" => $user->room_name, "language" => intval($user->language), "last_menu_date" => $last_date,"role" => $role,'guideline' => setting('site.app_msg'),'guideline_cn'=> setting('site.app_msg_cn') != "" ? setting('site.app_msg_cn') : setting('site.app_msg')));
        }else{
            return $this->sendResultJSON('1', '', array("occupancy" => 0,"resident_name" => "", "language" => 0, "last_menu_date" => $last_date,"role" => $role,'guideline' => setting('site.app_msg'),'guideline_cn'=> setting('site.app_msg_cn') != "" ? setting('site.app_msg_cn') : setting('site.app_msg')));
        }
    }

    public function getRoomData(Request $request)
    {
        $date = $request->input('date');
        $item_id = intval($request->input('item_id'));
        $order_details = array();
        $room_array = array();
        if ($date != "" && $item_id != "") {
            $rooms_data = RoomDetail::all();
            foreach ($rooms_data as $r) {
                $room_array[$r->room_id] = $r->room_name;
            }
            $order_data = OrderDetail::where("date", $date)->where("item_id", $item_id)->get();
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $order_details[$o->room_id] = array("room_id" => $o->room_id, "room_name" => $room_array[$o->room_id]);
            }
            return $this->sendResultJSON('1', '', array('rooms' => array_values($order_details)));
        }
    }
    
    public function printOrderData(Request $request){
      $date = $request->input('date');
        $room_id = intval($request->input('room_id'));
        $instruction = "";
        $food_texture = "";
        $resident_name = "";
        $breakfast = $lunch = $dinner = array();
        if ($date != "" && $room_id != "") {
            $order_data = OrderDetail::where("room_id", $room_id)->where("date", $date)->orderBy("id", "asc")->get();

           $preference_details = array();
          
            $preferences = ItemPreference::all();
            foreach (count($preferences) > 0 ? $preferences : array() as $p) {
                $preference_details[$p->id] = array("name" => $p->pname, "name_cn" => ($p->pname_cn != null ? $p->pname_cn : $p->pname));
            }

            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $preference_array = array();
                $option_details = "";
                if (isset($o->itemData) && isset($o->itemData->categoryData)) {
                    $cat_data = $o->itemData->categoryData;
                    $type = intval($cat_data->type);
                    if ($o->item_options != "") {
                        $option_data = ItemOption::select("option_name")->where("id", $o->item_options)->first();
                        if ($option_data) {
                            $option_details = $option_data->option_name;
                        }
                    }


                    if ($o->preference != "") {
                        $c_preferences = explode(",", $o->preference);
                        foreach (count($c_preferences) > 0 ? $c_preferences : array() as $cp) {
                            $cp = intval($cp);
                            if ($preference_details[$cp]) {
                                array_push($preference_array, $preference_details[$cp]['name']);
                            }

                        }
                    }

                    $o->cat_id = intval($o->itemData->categoryData->id);
                    $data = array("category" => (intval($cat_data->parent_id) == 0 ? $cat_data->cat_name : ($cat_data->catParentId ? $cat_data->catParentId->cat_name : "")), "sub_cat" => (intval($cat_data->parent_id) == 0 ? "" : $cat_data->cat_name), "item_name" => $o->itemData->item_name, "quantity" => intval($o->quantity), "options" => $option_details, "preference" => $preference_array);
                    if ($type == 1) {
                        array_push($breakfast, $data);
                    } else if ($type == 2) {
                        array_push($lunch, $data);
                    } else {
                        array_push($dinner, $data);
                    }
                }
            }

            $spi_data = RoomDetail::selectRaw("special_instrucations,food_texture,resident_name")->where("id", $room_id)->first();
            if ($spi_data)
                $instruction = $spi_data->special_instrucations;
            $food_texture = $spi_data->food_texture != null ? $spi_data->food_texture : "";
            
            $resident_name = "NA";
            if ($spi_data){
                $resident_name = $spi_data->resident_name != null ? $spi_data->resident_name : "NA";
            }
        }
        return $this->sendResultJSON('1', '', array('breakfast' => $breakfast, 'lunch' => $lunch, 'dinner' => $dinner, 'special_instruction' => $instruction, 'food_texture' => $food_texture, 'resident_name' => $resident_name));

    }
}
