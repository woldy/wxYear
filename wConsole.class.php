<?php
	class wConsole{
		/**
		 * 输出警告
		 * @Author   Woldy
		 * @DateTime 2016-05-13T13:18:41+0800
		 * @param    [type]                   $str [description]
		 * @return   [type]                        [description]
		 */
		public  function warning($str){
			$this->output($str,'yellow');
		}

		/**
		 * 输出提示
		 * @Author   Woldy
		 * @DateTime 2016-05-13T13:18:56+0800
		 * @param    [type]                   $str [description]
		 * @return   [type]                        [description]
		 */
		public  function tip($str){
			$this->output($str,'white');
		}

		/**
		 * 输出成功
		 * @Author   Woldy
		 * @DateTime 2016-05-13T13:18:56+0800
		 * @param    [type]                   $str [description]
		 * @return   [type]                        [description]
		 */
		public  function success($str){
			$this->output($str,'green');
		}

		/**
		 * 输出错误并退出程序
		 * @Author   Woldy
		 * @DateTime 2016-05-13T13:18:56+0800
		 * @param    [type]                   $str [description]
		 * @return   [type]                        [description]
		 */
		public  function error($str,$exit=true){
			$this->output($str,'red');
			if($exit){
				exit();
			}
		}

		
		public  function output($text,$color=''){
			if(!empty($this->server)){
				$text="server[$this->server] ".$text;
			}
			if(!empty($this->taskid)){
				$text="task[$this->taskid] ".$text;
			}

			$color_list=array(
				'black'=>'30',
				'red'=>'31',
				'green'=>'32',
				'yellow'=>'33',
				'blue'=>'34',
				'purple'=>'35',
				'cyan'=>'36',
				'white'=>'37'
			);
			 if(!empty($color) && array_key_exists ($color, $color_list)){
			 	$text= "\033[0;".$color_list[$color]."m$text \x1B[0m";
			 }else{
			 	$text= $text;
			 }

			$fd = fopen('php://stdout', 'w');
   			if ($fd) {
        		fwrite($fd, $text);
        		fclose($fd);
    		}
		}

		public  function input($tip='input:',$color='white'){
			$this->output($tip,$color);
	        return rtrim(fgets(STDIN));
		}
	}