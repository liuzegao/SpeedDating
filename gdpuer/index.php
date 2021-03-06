<?php
require_once ("../api/date.php");
require_once ("../api/topic.php");
class Wechat {
    public $token;
    public $request = array ();
    protected $funcflag = false;
    protected $debug = false;
    public function __construct($token, $debug = false) {
        $this->token = $token;
        $this->debug = $debug;
    }
    public function get_msg_type() {
        return strtolower ( $this->request ['MsgType'] );
    }
    public function get_media_id() {
        return strtolower ( $this->request ['MediaId'] );
    }
    public function get_event_type() {
        return strtolower ( $this->request ['Event'] );
    }
    public function get_event_key() {
        return strtolower ( $this->request ['EventKey'] );
    }
    public function get_creattime() {
        return strtolower ( $this->request ['CreateTime'] );
    }
    public function valid() {
        $echoStr = $_GET ["echostr"];
        if ($this->checkSignature ()) {
            echo $echoStr;
            exit ();
        }
    }

    public function replyText($message) {
        if($message == "")
            return "";
        $textTpl = <<<eot
<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[%s]]></MsgType>
    <Content><![CDATA[%s]]></Content>
    <FuncFlag>%d</FuncFlag>
</xml>
eot;
        $req = $this->request;
        return sprintf ( $textTpl, $req ['FromUserName'], $req ['ToUserName'], time (), 'text', $message, $this->funcflag ? 1 : 0 );
    }


    public function replyNews($arr_item) {
        $itemTpl = <<<eot
        <item>
            <Title><![CDATA[%s]]></Title>
            <Discription><![CDATA[%s]]></Discription>
            <PicUrl><![CDATA[%s]]></PicUrl> 
            <Url><![CDATA[%s]]></Url>
        </item>

eot;
        $real_arr_item = $arr_item;
        if (isset ( $arr_item ['title'] ))
            $real_arr_item = array (
                $arr_item 
            );

        $nr = count ( $real_arr_item );
        $item_str = "";
        foreach ( $real_arr_item as $item )
            $item_str .= sprintf ( $itemTpl, $item ['title'], $item ['description'], $item ['pic'], $item ['url'] );

        $time = time ();
        $fun = $this->funcflag ? 1 : 0;

        return <<<eot
<xml>
    <ToUserName><![CDATA[{$this->request['FromUserName']}]]></ToUserName>
    <FromUserName><![CDATA[{$this->request['ToUserName']}]]></FromUserName>
    <CreateTime>{$time}</CreateTime>
    <MsgType><![CDATA[news]]></MsgType>
    <Content><![CDATA[]]></Content>
    <ArticleCount>{$nr}</ArticleCount>
    <Articles>
$item_str
    </Articles>
    <FuncFlag>{$fun}</FuncFlag>
</xml>
eot;
    }

    public function reply() {
        // get post data, May be due to the different environments
        $postStr = $GLOBALS ["HTTP_RAW_POST_DATA"];
        //    file_put_contents ( "request.txt", $postStr );

        if (!empty($postStr)){
            $this->request = ( array ) simplexml_load_string ( $postStr, 'SimpleXMLElement', LIBXML_NOCDATA );
            $message = self::reply_main($this->request, $this);//reply_main

            if (!is_array($message)) {
                $ret = $this->replyText($message);
            } else {
                $ret = $this->replyNews($message);
            }
            echo $ret;
        }else {
            echo "success";
            exit;
        }
    }
    private function checkSignature() {
        $args = array (
            "signature",
            "timestamp",
            "nonce" 
        );
        foreach ( $args as $arg )
            if (! isset ( $_GET [$arg] ))
                return false;

        $signature = $_GET ["signature"];
        $timestamp = $_GET ["timestamp"];
        $nonce = $_GET ["nonce"];

        $tmpArr = array (
            $this->token,
            $timestamp,
            $nonce 
        );
        sort ( $tmpArr, SORT_STRING );
        $tmpStr = implode ( $tmpArr );
        $tmpStr = sha1 ( $tmpStr );

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    function reply_main($request, $w) {
        $to = $request ['ToUserName'];
        $open_id = $request ['FromUserName'];
        $reply_content ="";

        $date_user = new eight_min_date;
        $topic = new topic;
        // store session data

        // 大众接口
        if ($w->get_msg_type () == "location") {
            $lacation = "x@" . ( string ) $request ['Location_X'] . "@" . ( string ) $request ['Location_Y'];
            $lacation = urlencode ( str_replace ( '\.', '\\\.', $lacation ) );
            $lacation = urldecode ( xiaojo ( $lacation, $open_id, $to ) );
            return $lacation;
        }   // 返回图片地址
        else if ($w->get_msg_type () == "event") {
            if ($w->get_event_type () == "subscribe") {
                if($date_user->is_register($open_id)) {
                    $date_ret = "你已经完成注册了，请点击左下角按钮继续使用\n";
                }else {
                    //$date_ret = "欢迎首次使用8分钟交友,为了保持活动的宗旨，请遵守以下规则：\n1.在活动中不能问对方真实姓名，每人只有编号;\n2.不能问对方电话号码，微信号;\n3.不能问对方详细地址。\n但是，一旦聊得投机而时间已到，怎么办呢？交友结束后，你可将想结交的朋友的编号记下来，再通过我们的公众号去联系对方哦。\n\n请输入性别： 男或女";
                    $date_ret = "欢迎来到大学城8分钟交友\n1.点击左下角按钮 约会吧，“点我开始”进入匹配聊天 \n聊天框可输入好友发给你的邀请码哦，这样可以获取神秘道具哈\n";
                    $user_info = $date_user->get_user_info($open_id);
                    $sex = $user_info['sex'];
                    if($sex==1){$sex=1;}else{$sex=0;}
                    $step = 4;
                    $date_user->register($open_id,$sex);
                    $date_user->update_step($open_id, $step);
                }
                return $date_ret;
            }elseif ($w->get_event_type () == "unsubscribe") {
                $unsubscribe = "真的要取消关注了吗？我们会做的更好的";
                return $unsubscribe;
            }

            // 点击菜单
            elseif ($w->get_event_type () == "click") {
                $menukey = $w->get_event_key ();
                switch ($menukey) {
                case 'dingdan':
                        if($date_user->is_talking($open_id))
                        return "你已经在聊天了喔，回复结束 结束聊天，再输入订单号";
                        $date_user->update_step($open_id, 88);
                        $date_ret = "请输入订单号，可获取道具（在微店购买道具后会有订单号）\n<a href='http://weidian.com/?userid=326297086&wfr=qfriend'>微店入口<<<<==</a>";
                        return $date_ret;
                        //$user_info = $date_user->get_user_info($open_id);
                        break;
                case 'qbt':
                        $qbt = $date_user->get_qbt($open_id);
                        $invitation_code = $date_user->get_invitation_code($open_id);
                        $date_ret = "你的丘比特之箭的数量是".$qbt."支\n可以通过邀请好友输入邀请码获得丘比特之箭";
                        return $date_ret;
                        break;
                case 'invitation':
                        $Id = $date_user->get_Id_by_open_id($open_id);
                        $date_ret = "个人邀请码(Id)是".$Id."\n当好友关注后输入你的邀请码注册，两人皆可以获得一支丘比特之箭，\n可以使用该道具来续聊和免排队哦";
                        return $date_ret;
                        break;
                case 'date':
                    if(!$date_user->is_register($open_id)) {
                        $step = 1;
                        $date_user->register($open_id);
                        $date_user->update_step($open_id, $step);
                        $date_ret = "请先输入男或女来完成注册\n";
                        return $date_ret;
                    }
                    if($date_user->is_talking($open_id)) {
                        $date_ret = "你已经在聊天了喔\n";
                    }else if($date_user->get_sex($open_id) == -1) {
                        $date_ret = "请先输入男或女来完成注册\n";
                    }else {
                        /* Delete in Ours */
                        //                            if($date_user->get_gdpu_talk_times($open_id) == 0) {
                        //                                $date_ret = "要关注我们公众号体验\n";
                        //                                return $date_ret;
                        //                            }

                        /* Delete in Gdpuer */
                        if($date_user->get_real_first_talk_times($open_id)==0 && $date_user->is_transfer($open_id)==0) {
                            $date_ret = "请点击详情介绍，获取图文介绍转发到朋友圈，截图回复给我们继续使用,谢谢！！\n";
                            return $date_ret;
                        }

                        if($date_user->is_need_to_wait($open_id)) {
                            $date_user->update_waiting_start_time($open_id);
                            $date_ret = "你在排队中还有".$date_user->get_waiting_people($open_id)."人\n";
                        }else
                            $date_ret = $date_user->find_target_to_talk($open_id);
                        return $date_ret;
                    }
                    return $date_ret;
                    break;
                case 'change_sex':
                    if($date_user->get_sex($open_id) == -1) {
                        $date_ret = "请先输入男或女来完成注册\n";
                        return $date_ret;
                    }
                    if($date_user->is_talking($open_id))
                        return "你已经在聊天了喔";
                    $left_change_sex_times = $date_user->get_left_change_sex_times($open_id);
                    if($left_change_sex_times > 0) {
                        $date_user->update_step($open_id, 12);
                        $next = "请输入男或女，修正输错的性别\n你的修改次数剩下".$left_change_sex_times."次";
                    }else {
                        $next = "你的修改次数已经用光了，如有问题，请联系帅帅的开发者";
                    }
                    return $next;
                    break;

                case 'xuliao':
                    if($date_user->get_sex($open_id) == -1) {
                        $date_ret = "请先输入男或女来完成注册\n";
                        return $date_ret;
                    }
                    if($date_user->is_talking($open_id))
                        return "你已经在聊天了喔";
                    $date_user->update_step($open_id, 11);
                    $qbt = $date_user->get_qbt($open_id);
                    $next = "请输入对方的Id号，使用丘比特之箭找回ta\n你的丘比特之箭还剩下".$qbt."支\n 输入0结束续聊过程";
                    return $next;
                    break;

                case 'chat':
                    $about = "建议or合作 请发至反馈邮箱 \n（点击发送邮件）用户建议戳这 eight_mins@126.com \n联系:\nPJM \n微信号 jiamingpeng1994 \n或\nYongHaoHu--QQ: 645310824(这货有女票了)";
                    return $about;
                    break;
                case 'jubao':
                    $about = "如有不和谐行为，请将对方编号及截图 请发至举报邮箱 \n（点击发送邮件）eight_mins_110@126.com  \n您的支持就是我们的动力";
                    return $about;
                    break;   


                default:
                    # code...
                    break;
                }
                //$menu = urldecode ( xiaojo ( $menukey, $open_id, $to ) );
                //return $menu;
            }       // 点击菜单选项
            else {
                $menukey = $w->get_event_key ();
                return $menukey;
            }
        }

        else if ($w->get_msg_type () == "voice" || $w->get_msg_type () == "image" || $w->get_msg_type () == "video") {
            if($date_user->is_talking($open_id)) {
                $video_id = NULL;
                if($w->get_msg_type () == "image")
                    $type = 'image';
                else if($w->get_msg_type () == "voice")
                    $type = 'voice';
                else {
                    $type = 'video';
                    $video_id = $request ['ThumbMediaId'];
                }
                $target = $date_user->get_target($open_id);
                $content = $request ['MediaId'];
                //$content = $w->get_media_id();
                $date_user->sendmsg($target, $content, $type, $video_id);
                $content = $date_user->caculate_left_time($open_id);
            }else {
                if($date_user->is_transfer($open_id)==0 && $w->get_msg_type () == "image") {
                    $date_user->update_transfer($open_id);
                    $content = "thank you for your transfer\n谢谢你的转发，欢迎继续使用八分钟约会";
                }else {
                    $content = "咦,我也有这东西喔\n";
                }
            }
            return $content;
        }

        else if ($w->get_msg_type () == "text"){
            $content = trim ( $request ['Content'] );
            //            if($date_user->get_step($open_id) == 1) {
            //                $step = 2;
            //                $date_user->update_step($open_id, $step);
            //                $content = "请正确输入您的微信号或手机号\n";
            //                return $content;
            //            }
            //            if($date_user->get_step($open_id) == 2) {
            //                $date_user->update_wechat_id($open_id, $content);
            //                $step = 3;
            //                $date_user->update_step($open_id, $step);
            //                $content = "现在请输入你的性别(男或女)";
            //                return $content;
            //            }
            //
            if($date_user->get_step($open_id) == 1) {
                if (strstr ( $content, '女' )) {
                    $sex = 0;
                }else if (strstr ( $content, '男' )) {
                    $sex = 1;
                }else {
                    $content = "请输入正确的信息： 男或女";
                    return $content;
                }

                $start_time = time();
                $step = 4;
                $date_user->update_step($open_id, $step);
                //                $want_to_talk = 1;
                $want_to_talk = 0;
                $date_user->update_all($open_id, $sex, $start_time, $want_to_talk);
                //                $content = $date_user->find_target_to_talk($open_id);
                $content = "恭喜，已经完成注册.\n1.如有邀请码，请现在输入邀请码获得神秘礼物\n2.请直接点击左下角按钮进行聊天\n";
                return $content;
            }

            if($date_user->is_talking($open_id)) {
                if($content == "结束") {
                    $date_user->stop_talking($open_id);
                    $target = $date_user->get_target($open_id);
                    $date_user->stop_talking($target);
                    $content = "你的聊天已结束，请继续享用我们的8分钟约会：P\n";
                }else if($content == "换"){
                    $content = $topic->type();
                    $target_id = $date_user->get_target($open_id);
                    
                    $date_user->sendmsg($target_id, $content, 'text', NULL);

                } else {
                    $target = $date_user->get_target($open_id);
                    $content = $date_user->filt_wechat_num($content);
                    $type = "text";
                    $date_user->sendmsg($target, $content, $type, NULL);
                    $content = $date_user->caculate_left_time($open_id);
                }
                return $content;
            }
        }
        $step = $date_user->get_step($open_id);
        if(preg_match('/^[0-9]*$/',$content) && $step<11) {
            if($date_user->get_invitation_code($open_id) == -1) {
                if($date_user->check_invitation_code($content)) {
                    $date_user->plus_twos_qbt($open_id, $content);
                    $date_user->update_invitation_status($open_id, $content);
                    $reply_content = "恭喜邀请码使用成功，你跟邀请者皆获得丘比特之箭一支，可以使用它来续聊和免排队\n";
                }else {
                    $reply_content = "输入邀请码错误\n";
                }
            }else {
                $reply_content = "你已经输入过邀请码了\n但你可以让其他人输入你的专属码来获得道具——丘比特之箭";
            }
        }else if($step == 11) {
            $step = 4;
            $date_user->update_step($open_id, $step);
            $Id = $content;
            $target_id = $date_user->get_open_id_by_Id($Id);
            $ret = $date_user->check_continue_status($open_id, $target_id);
            if($ret != "success") {
                return $ret;
            }
            if($date_user->get_info_by_Id($Id) > 0) {
                $reply_content = $date_user->continue_talking($open_id, $target_id);
            }else {
                return "不存在此Id编号";
            }
        }else if($step == 12) {
            $step = 4;
            $date_user->update_step($open_id, $step);
            if (strstr ( $content, '女' )) {
                $sex = 0;
                $date_user->minus_left_change_sex_times($open_id);
                $date_user->update_sex($open_id, $sex);
                $reply_content = "你已成功修改自己的性别为：女";
            }else if(strstr ( $content, '男' )) {
                $sex = 1;
                $date_user->minus_left_change_sex_times($open_id);
                $date_user->update_sex($open_id, $sex);
                $reply_content = "你已成功修改自己的性别为：男";
            }else {
                $reply_content = "请输入正确的信息： 男或女";
            }
        }else if($step == 88){
            $step = 4;
            $date_user->update_step($open_id, $step);
            $info=$date_user->get_pay($content);
            //$buyer_info=$info['buyer_info'];
            $status=$info['status'];
            $order_id=$info['order_id'];
            $quantity=$info['quantity'];
            if($status=='pay'){
                $flag=$date_user->get_dingdan($content);
                if($flag){$reply_content = "该订单号已使用过";}
                else{
                    $status_reason=$date_user->fa_huo($content);
                    $date_user->charu_dingdan($open_id,$content);
                    if($status_reason['status_reason']=="success"){
                        $date_user->update_qbt_dingdan($open_id,$quantity);
                    $qbt=$date_user->get_qbt($open_id);

                    $reply_content = "您购买了".$quantity."支丘比特之箭\n您现在拥有".$qbt."支丘比特之箭";
                    }else{$reply_content ="系统繁忙，请重试";}

                    }
            }else if($status=='unpay'){$reply_content = "该订单号，您还没有支付\n<a href='http://weidian.com/?userid=326297086&wfr=qfriend'>点击支付</a>";}
            else{$reply_content = "您的订单号不存在或已使用过\n<a href='http://weidian.com/?userid=326297086&wfr=qfriend'>微店入口</a>";}
        }
        else if(strstr ( $content, '取消' )) {
            if($date_user->get_want_to_talk($open_id) == 1) {
                $date_user->update_want_to_talk_to_zero($open_id);
                $reply_content = "你已经成功取消匹配状态\n请按[约会吧]按钮，再点[点我开始]按钮开始约会";
            }else {
                $reply_content = "你没有在等待中\n 请按[约会吧]按钮，再点[点我开始]按钮开始约会";
            }
        }else if(strstr ( $content, '六一' )) {
                    $url = "http://av.jejeso.com/Ours/jm/61/index.php?open_id=".$open_id;
                    $reply_content = "#title|送你一份奇葩礼物?@title|点此获得你专属的奇葩礼物xD。#url|".$url;
                    $reply_content = self::replypic($reply_content);
                    return $reply_content;
        }else if(strstr ( $content, '订单' )){
            $date_user->update_step($open_id, 88);
                        $date_ret = "请输入订单号，可获取道具（在微店购买道具后会有订单号）\n<a href='http://weidian.com/?userid=326297086&wfr=qfriend'>微店入口</a>";
                        return $date_ret;
        }
        else {
            $reply_content = "#title|什么是八分钟约会呢?@title|点此进入了解详情,点击8分钟约会按钮使用,在8分钟内遇见‘她/他’。#url|http://mp.weixin.qq.com/s?__biz=MzAwNjUxMzcwNA==&mid=207779817&idx=1&sn=9262e599f34718f70fa6e51caf4dd367#rd#pic|http://av.jejeso.com/Ours/eightmins/8.jpg";
            $reply_content = self::replypic($reply_content);
        }

        return $reply_content;
    }

    //多图文回复function
    function replypic($reply_content) {
        $a = array ();
        $b = array ();
        $c = array ();
        $n = 0;
        $contents = $reply_content;
        foreach ( explode ( '@t', $reply_content ) as $b [$n] ) {
            if (strstr ( $contents, '@t' )) {
                $b [$n] = str_replace ( "itle", "title", $b [$n] );
                $b [$n] = str_replace ( "ttitle", "title", $b [$n] );
            }

            foreach ( explode ( '#', $b [$n] ) as $reply_content ) {
                list ( $k, $v ) = explode ( '|', $reply_content );
                $a [$k] = $v;
                $d .= $k;
            }
            $c [$n] = $a;
            $n ++;
        }
        $reply_content = $c;
        return $reply_content;
    }

}


define ( "WELCOME", "欢迎关注8分钟约会!\n精彩功能即将推出，XD我们的第一批粉丝，将会得到一大份惊喜哦" );

$token = "gdpuer";
$w = new Wechat($token);
if (isset ( $_GET ['echostr'] )) {
    $w->valid ();
    exit ();
}
$w->reply();

?>
