<?php
/**
 * 用户自提信息
 *
 *
 *
 *
 * @copyright  Copyright (c) 2007-2015 EasySoBuy Inc. (http://www.easysobuy.com)
 * @license    http://www.easysobuy.com
 * @link       http://www.easysobuy.com
 * @since      File available since Release v1.1
 */
use Shopnc\Tpl;


defined('InShopNC') or exit('Access Invalid!');

class member_deliveryControl extends BaseMemberControl{
    /**
     * 会员地址
     *
     * @param
     * @return
     */
    public function deliveryOp() {

        Language::read('member_address');
        $lang   = Language::getLangContent();

        $delivery_class = Model('delivery');
        /**
         * 判断页面类型
         */
        if (!empty($_GET['type'])){
            /**
             * 新增/编辑地址页面
             */
            if (intval($_GET['id']) > 0){
                /**
                 * 得到地址信息
                 */
                $delivery_info = $delivery_class->getOneDelivery(intval($_GET['id']));

                if ($delivery_info['member_id'] != $_SESSION['member_id']){
                    showMessage('参数不正确','index.php?act=member_delivery&op=delivery','html','error');
                }
                /**
                 * 输出地址信息
                 */
                Tpl::output('delivery_info',$delivery_info);
            }
            /**
             * 增加/修改页面输出
             */
            Tpl::output('type',$_GET['type']);
            Tpl::showpage('member_delivery.edit','null_layout');
            exit();
        }

        /**
         * 判断操作类型
         */
        if (chksubmit()){
            /**
             * 验证表单信息
             */
            $obj_validate = new Validate();
            $obj_validate->validateparam = array(
                array("input"=>$_POST["receive_name"],"require"=>"true","message"=>'提货人不能为空'),
                array("input"=>$_POST["id_card"],"require"=>"true","message"=>'请输入身份证'),
                array("input"=>$_POST["plate_number"],"require"=>"true","message"=>'请输入车牌号')
            );
            $error = $obj_validate->validate();
            if ($error != ''){
                showValidateError($error);
            }

            $data = array();
            $data['member_id'] = $_SESSION['member_id'];
            $data['receive_name'] = $_POST['receive_name'];
            $data['id_card'] = trim($_POST['id_card']);
            $data['plate_number'] = trim($_POST['plate_number']);
            $data['is_default'] = $_POST['is_default'] ? 1 : 0;

            //更新数据之前，先取消用户的默认收货地址
            if ($_POST['is_default']) {
                $delivery_class->editDelivery(array('is_default'=>0),array('member_id'=>$_SESSION['member_id'],'is_default'=>1));
            }

            if (intval($_POST['id']) > 0){
                $rs = $delivery_class->editDelivery($data, array('delivery_id' => intval($_POST['id']),'member_id'=>$_SESSION['member_id']));
                if (!$rs){
                    exit(json_encode(array('state'=>false,'msg'=>'自提信息修改失败')));
                }
            }else {
                $count = $delivery_class->getDeliveryCount(array('member_id'=>$_SESSION['member_id']));
                if ($count >= 20) {
                    exit(json_encode(array('state'=>false,'msg'=>'最多允许添加20个有效地址')));
                }
                $rs = $delivery_class->addDelivery($data);
                if (!$rs){
                    exit(json_encode(array('state'=>false,'msg'=>'自提信息添加失败')));
                }
            }
            exit(json_encode(array('state'=>true,'msg'=>'自提信息添加成功')));
        }

        $del_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0 ;
        if ($del_id > 0){
            $rs = $delivery_class->delDelivery(array('delivery_id'=>$del_id,'member_id'=>$_SESSION['member_id']));
            if ($rs){
                showDialog(Language::get('自提信息添加成功'),'index.php?act=member_delivery&op=delivery','js');
            }else {
                showDialog(Language::get('自提信息添加失败'),'','error');
            }
        }
        $delivery_list = $delivery_class->getDeliveryList(array('member_id'=>$_SESSION['member_id']));

        self::profile_menu('delivery','delivery');
        Tpl::output('delivery_list',$delivery_list);
        Tpl::showpage('member_delivery.index');

    }

    /**
     * 添加自提点型收货地址
     */
    public function delivery_addOp() {
        if (chksubmit()) {
            $info = Model('delivery_point')->getDeliveryPointOpenInfo(array('dlyp_id'=>intval($_POST['dlyp_id'])));
            if (empty($info)) {
                showDialog('该自提点不存在','','error');
            }
            $data = array();
            $data['member_id'] = $_SESSION['member_id'];
            $data['true_name'] = $_POST['true_name'];
            $data['area_id'] = $info['dlyp_area_3'];
            $data['city_id'] = $info['dlyp_area_2'];
            $data['area_info'] = $info['dlyp_area_info'];
            $data['address'] = $info['dlyp_address'];
            $data['tel_phone'] = $_POST['tel_phone'];
            $data['mob_phone'] = $_POST['mob_phone'];
            $data['dlyp_id'] = $info['dlyp_id'];
            $data['is_default'] = 0;
            if (intval($_POST['address_id'])) {
                $result = Model('address')->editAddress($data, array('address_id' => intval($_POST['address_id'])));
            } else {
                $count = Model('address')->getAddressCount(array('member_id'=>$_SESSION['member_id']));
                if ($count >= 20) {
                    showDialog('最多允许添加20个有效地址','','error');
                }
                $result = Model('address')->addAddress($data);
            }
            if (!$result){
                showDialog('保存失败','','error');
            }
            showDialog('保存成功','reload','js');
        } else {
            if (intval($_GET['id']) > 0) {
                $model_addr = Model('address');
                $condition = array('address_id'=>intval($_GET['id']),'member_id'=>$_SESSION['member_id']);
                $address_info = $model_addr->getAddressInfo($condition);
                //取出省级ID
                $area_info = Model('area')->getAreaInfo(array('area_id'=>$address_info['city_id']));
                $address_info['province_id'] = $area_info['area_parent_id'];
                Tpl::output('address_info',$address_info);
            }
            Tpl::showpage('member_address.delivery_add','null_layout');
        }
    }

    /**
     * 删除收货地址
     */
    public function del_deliveryOp() {
        if (isset($_POST['id'])) {
            $id = trim($_POST['id']);
            $model = Model('delivery');
            $val = $model->where(array('member_id' => $_SESSION['member_id'], 'delivery_id' => $id))->delete();

            if ($val) {
                exit(json_encode(array('state' => true, 'msg' => '删除自提信息成功')));
            } else {
                exit(json_encode(array('state' => false, 'msg' => '删除自提信息失败')));
            }
        } else {
            exit(json_encode(array('state' => false, 'msg' => '删除自提信息失败')));
        }
    }

    /**
     * 展示自提点列表
     */
    public function delivery_listOp() {
        $model_delivery = Model('delivery_point');
        $condition = array();
        $condition['dlyp_area'] = intval($_GET['area_id']);
        $list = $model_delivery->getDeliveryPointOpenList($condition,5);
        Tpl::output('show_page',$model_delivery->showpage());
        Tpl::output('list',$list);
        Tpl::showpage('member_address.delivery_list','null_layout');
    }


    /**
     * 用户中心右边，小导航
     *
     * @param string    $menu_type  导航类型
     * @param string    $menu_key   当前导航的menu_key
     * @return
     */
    private function profile_menu($menu_type,$menu_key='') {
        /**
         * 读取语言包
         */
        Language::read('member_layout');
        $menu_array = array();
        switch ($menu_type) {
            case 'delivery':
                $menu_array = array(
                1=>array('menu_key'=>'delivery','menu_name'=>'自提信息列表',   'menu_url'=>'index.php?act=member_delivery&op=delivery'));
                break;
        }
        Tpl::output('member_menu',$menu_array);
        Tpl::output('menu_key',$menu_key);
    }

    /**
     * [修改用户自提信息]
     * @Auth     Marks
     * @DateTime 2016-06-07T18:39:06+0800
     * @return   [type]                   [description]
     */
    public function update_deliveryOp() {

        $delivery_class = Model('delivery');
        if (chksubmit()){
            // $count = $model_addr->getAddressCount(array('member_id'=>$_SESSION['member_id']));
            // if ($count >= 20) {
            //     exit(json_encode(array('state'=>false,'msg'=>'最多允许添加20个有效地址')));
            // }
            //验证表单信息
            $obj_validate = new Validate();
            $obj_validate->validateparam = array(
                array("input"=>$_POST["receive_name"],"require"=>"true","message"=>'提货人不能为空'),
                array("input"=>$_POST["id_card"],"require"=>"true","message"=>'请输入身份证'),
                array("input"=>$_POST["plate_number"],"require"=>"true","message"=>'请输入车牌号')
            );

            $error = $obj_validate->validate();
            if ($error != ''){
                $error = strtoupper(CHARSET) == 'GBK' ? Language::getUTF8($error) : $error;
                exit(json_encode(array('state'=>false,'msg'=>$error)));
            }

            $data = array();
            $where = array();
            $where['member_id'] = $_SESSION['member_id'];

            $data['receive_name'] = $_POST['receive_name'];
            $data['id_card'] = trim($_POST['id_card']);
            $data['plate_number'] = trim($_POST['plate_number']);

            $data['is_default'] = 1;


            //新增、更新之前，先将该用户的默认收货地址做修改
            $delivery_class->editDelivery(array('is_default'=>0),array('member_id'=>$_SESSION['member_id'],'is_default'=>1));


            if (!empty($_POST['delivery_id'])) {
                $where['delivery_id'] = intval($_POST['delivery_id']);
                $result = $delivery_class->editDelivery($data, $where);
            } else {
                $data['member_id'] = $_SESSION['member_id'];
                $result = $delivery_class->insert($data);
                $where['delivery_id'] = $result;
                // $res = $model_addr->field('city_id,area_id')->where('delivery_id='.$where['delivery_id'])->find();
                // $data['city_id'] = $res['city_id'];
                // $data['area_id'] = $res['area_id'];

            }

            if ($result){
$info =
<<<EOT
            <div class="have order_adress" id="delivery_$where[delivery_id]">
                <span class="fr">
                <a href="#" class="operate delete" onclick="del_delivery($where[delivery_id])">删除</a>
                <a href="#" class="operate" onclick="modify_delivery($where[delivery_id])">修改</a>
                </span>
                <b class="selected" onclick="change_delivery($where[delivery_id])" id="default2_$where[delivery_id]">$data[receive_name]</b>
                <span>$data[id_card]</span>
                <span>$data[plate_number]</span>
            </div>
EOT;
                exit(json_encode(array('state'=>true,'delivery_id'=>$where['delivery_id'],'modify'=>$_POST['delivery_id'] ? true : false,'info'=>$info)));
            }else {
                exit(json_encode(array('state'=>false,'msg'=>'自提信息添加失败')));
            }
        } else {
            exit(json_encode(array('state'=>false,'msg'=>'提交表单错误')));
        }
    }

    /**
     * [设置默认收货地址]
     * @Auth     Marks
     * @DateTime 2016-07-08T14:40:04+0800
     */
    public function set_default_deliveryOp() {
        if (isset($_POST['id']) && is_numeric($_POST['id'])) {
            $model_delivery = Model('delivery');
            //新增、更新之前，先将该用户的默认收货地址做修改
            $model_delivery->updateDefault($_SESSION['member_id']);

            $res = $model_delivery->setDefault($_SESSION['member_id'],$_POST['id']);

            if ($res) {
                exit(json_encode(array('status'=>1,'msg'=>'设置默认自提信息成功')));
            } else {
                exit(json_encode(array('status'=>0,'msg'=>'设置默认自提信息失败')));
            }
        } else {
            exit(json_encode(array('status'=>0,'msg'=>'自提信息数据不正确')));
        }
    }

    /**
     * [根据ID查看自提信息具体信息]
     * @Auth     Marks
     * @DateTime 2016-06-06T16:19:29+0800
     * @param    [Int]                   $addr_id [收货地址ID]
     * @return   [Array]                            [description]
     */
    public function show_deliveryOp() {
        $model_delivery = Model('delivery');

        if (!empty($_POST['id'])) {
            $delivery_info = $model_delivery->getDeliveryInfo(array('delivery_id' => $_POST['id'], 'member_id' => $_SESSION['member_id']));
            exit(json_encode($delivery_info));
        } else {
            exit('error');
        }
    }
}
