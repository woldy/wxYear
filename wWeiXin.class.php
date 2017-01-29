 <?php
    class wWeixin{
        private $appid = 'wx782c26e4c19acffb';
        private $User;

        private function getMillisecond(){
            list($t1, $t2) = explode(' ', microtime());
            return $t2 . ceil(($t1 * 1000));
        }
        
        //获取UUID，用于获取登录二维码
        public function get_uuid(){
            $url = 'https://login.weixin.qq.com/jslogin';
            $url .= '?appid=' . $this->appid;
            $url .= '&fun=new';
            $url .= '&lang=zh_CN';
            $url .= '&_=' . time();

            $content = $this->curlPost($url);
            $content = explode(';', $content);
            $content_uuid = explode('"', $content[1]);
            $uuid = $content_uuid[1];
            return $uuid;
        }

        //根据UUID获取二维码扫码地址
        public function qrcode($uuid){
            $url = 'https://login.weixin.qq.com/qrcode/' . $uuid . '?t=webwx';
            return $url;
        }

        //检测扫码状态 408:未扫描;201:扫描未登录;200:登录成功; icon:用户头像
        public function login($uuid, $icon = 'true'){
            $url = 'https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?loginicon=' . $icon . '&r=' . ~time() . '&uuid=' . $uuid . '&tip=0&_=' . $this->getMillisecond();
            $content = $this->curlPost($url);
            preg_match('/\d+/', $content, $match);
            $code = $match[0];
            preg_match('/([\'"])([^\'"\.]*?)\1/', $content, $icon);
            if (isset($icon[2])) {
                $data = array(
                    'code' => $code,
                    'icon' => $icon[2],
                );
            } else {
                $data['code'] = $code;
            }
            return $data;
        }

        //登录成功后的回调，功能其实就是获取会话信息(包括各种临时key)及用户API地址，因为据说有一部分人的微信API请求接口是https://wx2.qq.com，另一部分人是https://wx.qq.com
        public function get_uri($uuid){
            $url = 'https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?uuid=' . $uuid . '&tip=0&_=e' . time();
            $content = $this->curlPost($url);
            $content = explode(';', $content);
            $content_uri = explode('"', $content[1]);
            $uri = $content_uri[1];

            preg_match("~^https:?(//([^/?#]*))?~", $uri, $match);
            $https_header = $match[0];
            $post_url_header = $https_header . "/cgi-bin/mmwebwx-bin";

            $new_uri = explode('scan', $uri);
            $uri = $new_uri[0] . 'fun=new&scan=' . time();
            $getXML = $this->curlPost($uri);

            $XML = simplexml_load_string($getXML);

            $callback = array(
                'post_url_header' => $post_url_header,
                'Ret' => (array)$XML,
            );
            return $callback;
        }

        //构造基本请求包参数，用于后续数据请求
        public function post_self($callback){
            $post = new \stdClass;
            $Ret = $callback['Ret'];
            $status = $Ret['ret'];
            if ($status == '1203') {
                $this->error('');
            }
            if ($status == '0') {
                $post->BaseRequest = array(
                    'Uin' => $Ret['wxuin'],
                    'Sid' => $Ret['wxsid'],
                    'Skey' => $Ret['skey'],
                    'DeviceID' => 'e' . rand(10000000, 99999999) . rand(1000000, 9999999),
                );
                $post->skey = $Ret['skey'];
                $post->pass_ticket = $Ret['pass_ticket'];
                $post->sid = $Ret['wxsid'];
                $post->uin = $Ret['wxuin'];
                return $post;
            }
        }

        //初始化，主要是用来获取自己的UserName
        public function wxinit($post,$post_url_header){
            $url = $post_url_header.'/webwxinit?pass_ticket=' . $post->pass_ticket . '&skey=' . $post->skey . '&r=' . time();
            $post = array(
                'BaseRequest' => $post->BaseRequest,
            );
            $json = $this->curlPost($url, $post);
            $this->User=json_decode($json,true)['User'];
            if(!isset($this->User['UserName']) || empty($this->User['UserName'])){
                die('---');
            }

            return $json;
        }

        //并不知道有啥用
        public function wxstatusnotify($post, $json, $post_url_header){
            $init = json_decode($json, true);
            $User = $init['User'];
            $url = $post_url_header . '/webwxstatusnotify?lang=zh_CN&pass_ticket=' . $post->pass_ticket;
            $params = array(
                'BaseRequest' => $post->BaseRequest,
                "Code" => 3,
                "FromUserName" => $User['UserName'],
                "ToUserName" => $User['UserName'],
                "ClientMsgId" => time()
            );

            $data = $this->curlPost($url, $params);
            $data = json_decode($data, true);
            return $data;
        }

        //获取通讯录列表，有大用
        public function webwxgetcontact($post, $post_url_header){
            $url = $post_url_header . '/webwxgetcontact?pass_ticket=' . $post->pass_ticket . '&seq=0&skey=' . $post->skey . '&r=' . time();
            $params['BaseRequest'] = $post->BaseRequest;
            $data = $this->curlPost($url, $params);
            return $data;
        }

        //获取活跃群信息，貌似没啥乱用
        public function webwxbatchgetcontact($post, $post_url_header, $group_list){
            $url = $post_url_header . '/webwxbatchgetcontact?type=ex&lang=zh_CN&r=' . time() . '&pass_ticket=' . $post->pass_ticket;
            $params['BaseRequest'] = $post->BaseRequest;
            $params['Count'] = count($group_list);
            foreach ($group_list as $key => $value) {
                if ($value[MemberCount] == 0) {
                    $params['List'][] = array(
                        'UserName' => $value['UserName'],
                        'ChatRoomId' => "",
                    );
                }
                $params['List'][] = array(
                    'UserName' => $value['UserName'],
                    'EncryChatRoomId' => "",
               );
            }
            $data = $this->curlPost($url, $params);
            $data = json_decode($data, true);
            return $data;
        }

        //心跳检测，我也不知道有啥用
        public function synccheck($post, $SyncKey){
            if (!$SyncKey['List']) {
                $SyncKey = $_SESSION['json']['SyncKey'];
            }
            foreach ($SyncKey['List'] as $key => $value) {
                if ($key == 1) {
                    $SyncKey_value = $value['Key'] . '_' . $value['Val'];
                } else {
                    $SyncKey_value .= '|' . $value['Key'] . '_' . $value['Val'];
                }

            }
            $header = array(
                '0' => 'https://webpush.wx2.qq.com',
                '1' => 'https://webpush.wx.qq.com',
            );
            foreach ($header as $key => $value) {
                $url = $value . "/cgi-bin/mmwebwx-bin/synccheck?r=" . getMillisecond() . "&skey=" . urlencode($post->skey) . "&sid=" . $post->sid . "&deviceid=" . $post->BaseRequest['DeviceID'] . "&uin=" . $post->uin . "&synckey=" . urlencode($SyncKey_value) . "&_=" . getMillisecond();
                $data[] = $this->curlPost($url);
            }
            foreach ($data as $k => $val) {
                $rule = '/window.synccheck={retcode:"(\d+)",selector:"(\d+)"}/';
                preg_match($rule, $data[$k], $match);
                if ($match[1] == '0') {
                    $retcode = $match[1];
                    $selector = $match[2];
                }
            }
            $status = array(
                'ret' => $retcode,
                'sel' => $selector,
            );
            return $status;
        }

        //获取新消息，反正我是用不到
        public function webwxsync($post, $post_url_header, $SyncKey){
            $url = $post_url_header . '/webwxsync?sid=' . $post->sid . '&skey=' . $post->skey . '&pass_ticket=' . $post->pass_ticket;

            $params = array(
                'BaseRequest' => $post->BaseRequest,
                'SyncKey' => $SyncKey,
                'rr' => ~time(),
            );
            $data = $this->curlPost($url, $params);

            return $data;
        }


        //发送消息接口，群发全靠他
        public function webwxsendmsg($post, $post_url_header, $to, $word){
            $url = $post_url_header . '/webwxsendmsg?pass_ticket=' . $post->pass_ticket;
            $clientMsgId = $this->getMillisecond() * 1000 + rand(1000, 9999);
            $params = array(
                'BaseRequest' => $post->BaseRequest,
                'Msg' => array(
                    "Type" => 1,
                    "Content" => $word,
                    "FromUserName" => $this->User['UserName'],
                    "ToUserName" => $to,
                    "LocalID" => $clientMsgId,
                    "ClientMsgId" => $clientMsgId
                ),
                'Scene' => 0,
            );
            $data = $this->curlPost($url, $params, 1);
            $data=json_decode($data,true);
            return $data;
        }

        //注销，反正我是直接结束进程
        public function wxloginout($post, $post_url_header){
            $url = $post_url_header . '/webwxlogout?redirect=1&type=1&skey=' . urlencode($post->skey);
            $param = array(
                'sid' => $post->sid,
                'uin' => $post->uin,
            );
            $this->curlPost($url, $param);
            return true;
        }

        //发请求方法，虽然我也不知道他为啥写这么复杂，这还是我给简化并修改了一些，实在懒得优化了，就这样吧，能用。
        public function curlPost($url, $data=null){
            $timeout=30;
            $CA = false;
            $cacert = getcwd() . '/cacert.pem'; 
            $SSL = substr($url, 0, 8) == "https://" ? true : false;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout - 2);
            if ($SSL && $CA) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
                curl_setopt($ch, CURLOPT_CAINFO, $cacert); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
            } else if ($SSL && !$CA) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); //閬垮厤data鏁版嵁杩囬暱闂
            if ($data) {
                $data = json_encode($data,JSON_UNESCAPED_UNICODE);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(  
                'Content-Type: application/json; charset=utf-8',  
                'Content-Length: ' . strlen($data))  
            );   
            $ret = curl_exec($ch);
            curl_close($ch);
            return $ret;
        }
    }