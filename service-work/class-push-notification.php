<?php
class pushNotification{
     public function __construct() {   
                          
     }        
     public function pwaforwp_push_notification_hooks(){
         
            add_action('publish_post', array($this, 'pwaforwp_send_notification_on_post_save'));                 
         
            add_action('wp_head', array($this, 'pwaforwp_load_pushnotification_script'), 35);                 
            add_action('wp_ajax_nopriv_pwaforwp_store_token', array($this,'pwaforwp_store_token')); 
            add_action('wp_ajax_pwaforwp_store_token', array($this, 'pwaforwp_store_token'));             
            add_action('wp_ajax_pwaforwp_send_notification_manually', array($this, 'pwaforwp_send_notification_manually'));
                           
     }

     public function pwaforwp_send_notification_manually(){                  
            if ( ! isset( $_POST['pwaforwp_security_nonce'] ) ){
            return; 
            }
            if ( !wp_verify_nonce( $_POST['pwaforwp_security_nonce'], 'pwaforwp_ajax_check_nonce' ) ){
               return;  
            }         
            $body    = sanitize_text_field($_POST['message']);                        
            $message['title'] = 'Manual';
            $message['body'] = $body;
            $message['url'] = site_url();
            $result = $this->pwaforwp_send_push_notification($message);  
            $result = json_decode($result, true);                         
            if(!empty($result)){             
            echo json_encode(array('status'=>'t', 'success'=> $result['success'], 'failure'=> $result['failure']));    
               }else{
            echo json_encode(array('status'=>'f', 'mesg'=> esc_html__('Notification not sent. Something went wrong','pwa-for-wp')));    
           }
           wp_die();
     }
     
     public function pwaforwp_send_notification_on_post_save(){
            global $post;              
            $settings = pwaforwp_defaultSettings();  
            
            switch ($post->post_type) {
                case 'post':
                    
                    if( strtotime($post->post_modified_gmt) == strtotime($post->post_date_gmt) ){
                    
                      if(isset($settings['on_add_post'])){
                        $message['title'] = esc_html__('New Post', 'pwa-for-wp');
                        $message['body']  = esc_html__('Check out our new post', 'pwa-for-wp');
                        $message['url']   = the_permalink ($post);
                        $this->pwaforwp_send_push_notification($message);	           
                        }                        
                    }else{
                        
                       if(isset($settings['on_update_post'])){
                        $message['title'] = esc_html__('Post Updated', 'pwa-for-wp');
                        $message['body']  = esc_html__('Check out our updated post', 'pwa-for-wp');
                        $message['url']   = the_permalink ($post);
                        $this->pwaforwp_send_push_notification($message);	           
                        }
                    }
                    
                    break;
                case 'page':
                    
                    if( strtotime($post->post_modified_gmt) == strtotime($post->post_date_gmt) ){
                    
                      if(isset($settings['on_add_page'])){
                        $message['title'] = esc_html__('New Page', 'pwa-for-wp');
                        $message['body']  = esc_html__('Check out our new page', 'pwa-for-wp');
                        $message['url']   = the_permalink ($post);
                        $this->pwaforwp_send_push_notification($message);	           
                        }                        
                    }else{
                        
                       if(isset($settings['on_update_page'])){
                        $message['title'] = esc_html__('Page Updated', 'pwa-for-wp');
                        $message['body']  = esc_html__('Check out our updated page', 'pwa-for-wp');
                        $message['url']   = the_permalink ($post);
                        $this->pwaforwp_send_push_notification($message);	           
                        }
                    }

                    break;

                default:
                    break;
            }
                              
     }
     
     public function pwaforwp_load_pushnotification_script(){	
            $url 	  = pwaforwp_front_url();
            $settings = pwaforwp_defaultSettings();                        
            $server_key = $settings['fcm_server_key'];
            $config = $settings['fcm_config'];
            $multisite_filename_postfix = '';
                    if ( is_multisite() ) {
            $multisite_filename_postfix = '-' . get_current_blog_id();
                    }        
            if($server_key !='' && $config !=''){
             echo '<script src="https://www.gstatic.com/firebasejs/5.5.4/firebase-app.js"></script>';	
             echo '<script src="https://www.gstatic.com/firebasejs/5.5.4/firebase-messaging.js"></script>';	
             echo '<script src="https://www.gstatic.com/firebasejs/5.5.4/firebase.js"></script>';
             echo '<link rel="manifest" href="'. esc_url($url.PWAFORWP_FILE_PREFIX.'-push-notification-manifest'.$multisite_filename_postfix.'.json').'">';	
            }                    
     }         
     public function pwaforwp_store_token(){
            $token   = sanitize_text_field($_POST['token']);             
            $get_token_list = array();  
            $result = false;
            if($token){
                $get_token_list = (array)json_decode(get_option('pwa_token_list'), true);               
                array_push($get_token_list, $token);                
                $result = update_option('pwa_token_list', json_encode($get_token_list));
            } 
            if($result){
            echo json_encode(array('status'=>'t', 'mesg'=> esc_html__('Token Saved Successfully','pwa-for-wp')));    
            }else{
            echo json_encode(array('status'=>'f', 'mesg'=> esc_html__('Token Not Saved','pwa-for-wp')));    
            }
             wp_die();
      }
      public function pwaforwp_send_push_notification($message){
          
            $settings = pwaforwp_defaultSettings();                        
            $server_key = $settings['fcm_server_key'];           
            $tokens = (array)json_decode(get_option('pwa_token_list'), true);             
            if(empty($tokens) || $server_key ==''){
                return;
            }            
            $header = [
                    'Authorization: Key='. $server_key,
                    'Content-Type: Application/json'
            ];
            $msg = [
                    'title' => $message['title'],
                    'body'  => $message['body'],
                    'icon'  => PWAFORWP_PLUGIN_URL.'/images/notification_icon.jpg',
                    'url'  => $message['url'],
                    'primarykey'  => uniqid(),
                    'image' => '',
            ];             
            $payload = [
                    'registration_ids' => $tokens,
                    'data'             => $msg  
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $header,
            )
            );
            $response = curl_exec($curl);            
            $err = curl_error($curl);
            curl_close($curl);
            if($err){
              return $err;
            }else{
              return $response;
            }              
      }
                 
}
if (class_exists('pushNotification')) {
	$object = new pushNotification;
        $object->pwaforwp_push_notification_hooks();
};