<?php
    include_once("wWeiXin.class.php");
    include_once("wConsole.class.php");


    function welcome($console){
        $console->tip("|-------------------------------|\n");
        $console->tip("|-------------------------------|\n");
        $console->tip("|----wxYear script by woldy.----|\n");
        $console->tip("|--------king@woldy.net---------|\n");
        $console->tip("|-------------------------------|\n");
        $console->tip("|------------v0.1---------------|\n");
        $console->tip("|-------------------------------|\n");
        $console->tip("|------------start--------------|\n");
        $console->tip("|--1 help------+----------------|\n");
        $console->tip("|--2 get contacts friends list--|\n");
        $console->tip("|--3 sends test-----------------|\n");
        $console->tip("|--4 start send-----------------|\n");
        $console->tip("|--5 quit-----------------------|\n");

        $console->tip("\n\n\n");
    } 


    $wx=new wWeixin();
    $console=new wConsole();
    echo "尝试获取登录二维码...\n";
    $uuid=$wx->get_uuid();
    $qrcode=$wx->qrcode($uuid);
    shell_exec("explorer \"{$qrcode}\"");
    echo "检测扫码状态...\n";
    while (true) {
        $login=$wx->login($uuid)['code']==200;
        if($login){
            echo "登录成功！\n";
            break;
        }
        sleep(1);
    }
    echo "正在初始化！\n";
    $callback=$wx->get_uri($uuid);
    $post_url_header=$callback['post_url_header'];
    $post=$wx->post_self($callback);
    $init=$wx->wxinit($post,$post_url_header);
    echo "初始化完毕...\n";


    welcome($console);
    while(true){
        $cmd=$console->input("what can i do for u?");
        switch ($cmd) {
            case '1':
                # code...
                break;
            case '2':
                $contact=json_decode($wx->webwxgetcontact($post, $post_url_header),true);
                $friends='';//用户列表
                $ChildName='';//对方孩子的名字，待填写字段
                $Call='';//称呼(你/您)，待填写字段
                foreach ($contact['MemberList'] as $friend) {
                    //默认发送名称，先用备注或用户昵称占位，然后再修改
                    $SendName=empty($friend['RemarkName'])?$friend['NickName']:$friend['RemarkName'];

                    $friends.="{$friend['NickName']},{$friend['RemarkName']},{$SendName},{$ChildName},{Call}\n";
                }
                file_put_contents("friends.list",$friends);
                break; 
            case '3':

                $contact=json_decode($wx->webwxgetcontact($post, $post_url_header),true);
                $friend_list=[];
                $secondName='';
                foreach ($contact['MemberList'] as $friend) {
                    //将昵称与备注分别存入数组
                    $friend_list[$friend['RemarkName']]=$friend;
                    $friend_list[$friend['NickName']]=$friend;
                }



                $friends=file("friends.csv");
                foreach ($friends as $friend) {
                    $friend=explode(',',trim($friend));
                    $NickName=$friend[0]??'unknown';
                    $RemarkName=$friend[1]??'unknown';
                    $SendName=$friend[2].'，';
                    $ChildName=$friend[3];
                    $Call=$friend[4];

                    if(!empty($ChildName)){
                         $ChildText="还有{$ChildName}小朋友在新的一年里幸福茁壮成长~";
                    }else{
                         $ChildText="小朋友茁壮成长！";
                    }
                   
                    $text="    Hello~{$SendName}\n    在这个夜深人静红包渐少的大年初一夜，帅帅的魏志伟同学机智地错过了群发高峰期来给{$Call}拜年了！\n    在这里祝{$Call}及{$Call}的家人在新的一年里身体健康、鸡祥如意、福星高照、财源广进！男的英俊潇洒，女的貌美如花，老人健康长寿，{$ChildText}\n 总而言之言而总之，新年新气象，鸡年大吉！";

                    $tofriend='';

                    /*确定是否存在相应昵称或备注的好友*/
                    if(isset($friend_list[$NickName])){
                        $tofriend=$friend_list[$NickName];
                    }else if(isset($friend_list[$RemarkName])){
                        $tofriend=$friend_list[$RemarkName];
                    }else{
                        
                    }

                    if(!isset($tofriend['UserName'])){
                        var_dump($tofriend);
                        var_dump($friend);
                    }

                    /*发送微信消息*/
                    $msg=$wx->webwxsendmsg($post, $post_url_header,$tofriend['UserName'],$text);
                    $log='';
                    if($msg['BaseResponse']['Ret']==0){
                        $log= "{$SendName}-{$UserName}-成功！\n";
                    }else{
                        $log="{$SendName}-{$UserName}-失败！！！！！！！！！！！！！！！！！！！！\n";
                    }
                    echo "{$log}";
                    file_put_contents('log.txt',$log,FILE_APPEND);
                    file_put_contents('log.txt',$text."\n\n",FILE_APPEND);
                }
                exit;
                break;   
            case '4':
                # code...
                break;    
            case '5':
                $wx->wxloginout($post, $post_url_header);
                break;
            default:
                # code...
                break;
        }
    }

  


   

   
   // file_put_contents('c.txt',$contact);
   //  var_dump($contact);
   //  
   