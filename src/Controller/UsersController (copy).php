<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\I18n\Time;
use Cake\Core\Exception\Exception;
use Cake\Auth\DefaultPasswordHasher;
use Cake\Mailer\Email;
use Cake\Routing\Router;
use Cake\Datasource\ConnectionManager;

/**
 * Users Controller
 */

class UsersController extends AppController{  

    public function initialize(){
        parent::initialize();
       // $conn = ConnectionManager::get('default');
        $this->loadComponent('RequestHandler');
         $this->RequestHandler->renderAs($this, 'json');
    }


    /** Index method    */
    public function index(){
              
        $user = $this->Users->find('all')->contain(['UserDetails']);        
        $this->set(array(
            'data' => $user,
            '_serialize' => ['data']
        ));
    }


   /*
        ** U1 - IsUserExists
        ** Request – String <Email> / String <Username>;

     */
    public function isUserExists($param){

       // $userTable = TableRegistry::get('Users');       
       // $query = $userTable->findAllByUsernameOrEmail($param, $param);

        //or
        //#param is either id or email
        $query_userexist = $this->Users->findAllByUsernameOrEmail($param, $param)->count();

        if($query_userexist>0){
            $data['response'] = True;
        }
        else{
            $data['response'] = False;
        }        

        $this->set(array(
            'data' => $data,
            '_serialize' => array('data')
        ));

    }


    /** 
        *  U2- getUserDetails,         
        *  Request – Int <UUID>;
    */
    public function getUserDetails($id = null){        
        $user_record = $this->Users->find()->where(['Users.id' => $id])->count();
        if($user_record>0){
              $data['user'] = $this->Users->get($id);
              $this->set([
                'data' => $data,
                '_serialize' => ['data']
              ]);          

        }
        else{
            $data['response'] = "Record is not found";
            $this->set([
              'data' => $data,
              '_serialize' => ['data']
          ]);
        }       
    }

/*
        *  U3 - getUserProfilesByUUID 
        *   Request – String <UUID>;
*/
    public function getUserProfilesByUUID($id = null){
        //$user = $this->Users->get($id);
        $record=$this->Users->find('all')->contain(['UserDetails'])->where(['Users.id' => $id])->count();

         if($record>0){
            $data['user'] = $this->Users->find('all')->contain(['UserDetails'])->where(['Users.id' => $id]);
            $this->set([
                  'data' => $data,
                  '_serialize' => ['data']
              ]);
          }
          else{
              $data['message']='Record is not found';
              $this->set([
                    'data' => $data,
                    '_serialize' => ['data']
                ]);
            }        
    }



    /**  
        *  U4- setUserProfileByUUID
        *  Request – Int <uuid>; , String <firstName>; ,  String <lastName / Null>; , String <fatherName / Null>; , String <motherName / Null> 

    */
    public function setUserProfileByUUID($id = null){

        $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();

            if ($this->request->is(['post', 'put'])) {
                if($user_record>0){
                    $user = $this->Users->get($id);
                    $user=$this->Users->patchEntity($user, $this->request->data);
                    if($this->Users->save($user)){
                        //$userdetails = TableRegistry::get('UserDetails');
                        $this->loadModel('UserDetails');
                        $userdetail=$this->UserDetails->find('all')->where(['user_id' => $id])->first();
                       // $userdetail = $userdetails->get($id);
                        
                        $userdetail= $this->UserDetails->patchEntity($userdetail, $this->request->data());
                        //if($userdetails->save($userdetail)){
                        if ($this->UserDetails->save($userdetail)) {
                              $data['message'] ="User record has been update on this id $id";
                        }                        
                    }
                      else{
                          $data['message'] = 'Unable to update the records';
                      }

                }
                else{
                    $data['message'] ="Record is not found at this id $id.";
                }               
      }
      else{
           $data['message'] ='not data is set to add';
      }

        $this->set([
            'response' => $data,
            '_serialize' => ['response']
        ]);
    }

    /*U5 Service to update or set user’s password and return Boolean status. */
    public function setUserPassword($id) {
      $message = FALSE;
      if ($this->request->is(['post', 'put'])) {
        $user_validated = FALSE;
        $current_password = '';
        $default_hasher = new DefaultPasswordHasher();
        $old_password = $this->request->data['old_password'];
        $user_fields = $this->Users->find('all')->where(['Users.id' => $id])->toArray();
        foreach($user_fields as $field) {
          $current_password = $field->password;
        }
        if (!empty($current_password)) {
          $user_validated = !empty($default_hasher->check($old_password, $current_password)) ? TRUE : FALSE;
        }
        if ($user_validated) {
          if (isset($this->request->data['password'])) {
            $user = $this->Users->get($id);
            $user->password = $this->request->data['password'];
            if ($this->Users->save($user)) {
              $message = TRUE;
            }
          } else {
            $message = 'Password is not Set';
          }
        } else {
          $message = 'You have entered either wrong Id or password';
        }
        $this->set([
          'response' => $message,
          '_serialize' => ['response']
        ]);
      }
    }


    /* 
        ** U6- setUserMobile
        ** Request – Int &lt;UUID&gt; , Int &lt;mobileNumber&gt;
     */
    public function setUserMobile($id=null,$mobile=null) {
        if($id!=null && $mobile!=null ){
              $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();
            if($user_record>0){
                $user = $this->Users->get($id);
                if(preg_match('/^[0-9]{10}$/', $mobile) ){
                    $user->mobile = $mobile;
                    if ($this->Users->save($user))
                         $data['message'] = 'True';
                    else 
                        $data['message'] = 'False';
                }else{
                     $data['message'] = 'Mobile Number is not valid';
                  }
            }else{
                $data['message'] = "No user exist on this UID";
            }
        }else{
              $data['message'] = 'User ID and mobile may be null ';
        }       
        $this->set([
            'response' => $data,
            '_serialize' => ['response']
        ]);

    }


    /* 
        ** U7- setUserEmail 
        ** Request – Int <UUID> , Int <email>;
    */
    public function setUserEmail($id=null,$email=null) {              
       
       if($id!=null && $email!=null ){
            $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();
            if($user_record>0){
                $user = $this->Users->get($id);
                $email = filter_var($email, FILTER_SANITIZE_EMAIL);
                // Validate e-mail
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $user->email = $email;
                        if ($this->Users->save($user)) 
                              $data['message'] = 'True';
                        else
                              $data['message'] = 'False';                        
                    } else {
                              $data['message']="$email is not a valid email address";
                         }
            }else{
                $data['message'] = "No user exist on this UID";
              } 
      }else{
            $data['message'] = 'User ID and Email may be null ';
      }           
               
        $this->set([
            'response' => $data,
            '_serialize' => ['response']
        ]);

    }


    /** U8-registerUser
     * Request –  Strting <firstName>, String <lastName>, (Int <mobile / Null> , String <EmailID / Null>) , Int <roleID>
     */
    public function registerUser() {
      try {
        $message = '';
        $data['response'] = FALSE;
        header("HTTP/1.1 500 ERROR");
        if ($this->request->is(['post', 'put'])) {
          $user = $this->Users->newEntity($this->request->data);

          if (!preg_match('/^[A-Za-z]+$/', $user['first_name'])) {
            $message = 'First name required';
            throw new Exception('Pregmatch not matched for first name');
          }

          if (!preg_match('/^[A-Za-z]+$/', $user['last_name'])) {
            $message = 'Last name required';
            throw new Exception('Pregmatch not matched for last name');
          }
           if (empty($user['username'])) {
            $message = 'Userame required';
            throw new Exception('Pregmatch not matched for Username');
          }

          $username_exist = $this->Users->find()->where(['Users.username' => $user['username']])->count();
          if ($username_exist) {
            $message = 'Username already exist';
            throw new Exception($message);
          }

          $username_email = $this->Users->find()->where(['Users.email' => $user['email']])->count();
          if ($username_email) {
            $message = 'Email already exist';
            throw new Exception($message);
          }

          if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $message = 'Email is not valid';
            throw new Exception($message);
          }

          if (empty($user['password'])) {
            $message = 'password required';
            throw new Exception('Pregmatch not matched for Username');
          }

          if (empty($user['repass'])) {
            $message = 'Enter re-password';
            throw new Exception('Pregmatch not matched for Username');
          }

          $password_hasher = new DefaultPasswordHasher();

          if ($password_hasher->check($user['repass'], $user['password'])!=1) {
            $message = 'password not match';
            throw new Exception('Pregmatch not matched for Username');
          }
          // if (!preg_match('/^[0-9]{10}$/', $user['mobile'])) {
          //   $message = 'Mobile number is not valid';
          //   throw new Exception($message);
          // }
          $user['status'] = 0;
          $user['created'] = $user['modfied'] = time();
          $userroles = TableRegistry::get('UserRoles');
          $userdetails = TableRegistry::get('UserDetails');

          if ($new_user = $this->Users->save($user)) {
            //save into user role table
            $userinfo = $this->Users->find()->select('Users.id')->where(['Users.username' => $user['username']])->limit(1);
            foreach ($userinfo as $row) {
              $user_id = $row->id;
            }
            $new_user_role = $userroles->newEntity(array('role_id' => $user['role_id'] , 'user_id' => $user_id));
             $new_user_detail = $userdetails->newEntity(array('user_id' => $user_id));
             $userdetails->save($new_user_detail);
            if ($userroles->save($new_user_role)) {
              $to = $user['email'];
              $from = 'logicdeveloper7@gmail.com';
              $subject = 'Signup: mylearinguru.com';

              $email_message = 'Dear ' . $user['first_name'] . ' ' . $user['last_name'] . "\n";
              $email_message.= 'Your username is: ' . $user['username'] . "\n";

              $source_url = isset($user['source_url']) ? $user['source_url'] : '';
              $email_message.= "\n Please activate using following url \n" . $source_url . 'parent_confirmation/' . $user_id;

              $this->sendEmail($to, $from, $subject, $email_message);

              header("HTTP/1.1 200 OK");
              $message = 'User registered successfuly';
              $data['response'] = TRUE;
            } else {
              $message = 'Some error occured during registration';
              throw new Exception('Unnable to save user roles');
            }
          } else {
            $message = 'Some error occured during registration';
          }
        }
      } catch (Exception $e) {
        $this->log($e->getMessage() .'(' . __METHOD__ . ')', 'error');
      }
      $this->set([
        'message' => $message,
        'data' => $data,
        '_serialize' => ['message', 'data']
      ]);
    }


    /**U9-Service to update status of user to Active or Inactive  */
    public function setUserStatus() {
      try {
        $message = '';
        $success = FALSE;
        if ($this->request->is('post')) {
          $id = isset($this->request->data['id']) ? $this->request->data['id'] : null;
          $status = isset($this->request->data['status']) ? $this->request->data['status'] : 1;
          if ($id != null) {
            $user = $this->Users->get($id);
            $user = $this->Users->patchEntity($user, array('id' => $id, 'status' => $status));
            if ($this->Users->save($user)) {
                $message = 'Status Changed';
                $success = TRUE;
            } else {
              $message = 'Some Error occured';
              throw new Exception('Unable to change status');
            }
          } else {
            $message = 'Please enter the User Id';
          }
        }
      } catch (Exceptio $e) {
        $this->log($e->getMessage() .'(' . __METHOD__ . ')', 'error');
      }
      $this->set([
        'status' => $success,
        'message' => $message,
        '_serialize' => ['status', 'message']
      ]);
    }

    /**U11 – Service to check that user still logged in or not.
      * basically to check his active session  */
    public function isUserLoggedin() {
      $this->loadComponent('Auth', [
          'authenticate' => [
            'Form' => [
              'fields' => [
                'username' => 'username',
                'password' => 'password',
              ]
            ]
          ],
  //          'loginAction' => [
  //            'controller' => 'Users',
  //            'action' => 'login'
  //          ]
        ]);
      $response = $status = FALSE;
      $user_info = $this->Auth->user();
      $token=null;
      if (!empty($user_info)) {
        $response = $status = TRUE;
        $token = $this->request->session()->id();
      }
      $this->set([
         'status' => $status,
         'response' => $response,
         'user_info' => $user_info,
         'token' => $token,
         '_serialize' => ['status', 'response', 'user_info', 'token']
      ]);
    }

    /** UB10 – getUserCourses  */
    public function getUserCourses($id = null) {

       $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();

      if($user_record>0){
          $usercourses = TableRegistry::get('UserCourses');
          //$menus = TableRegistry::get('Menus');
          $usercourse_records=$usercourses->find('all')->where(['user_id' => $id])->contain('Courses')->count();

            if($usercourse_records>0){
              $usercourse=$usercourses->find('all')->where(['user_id' => $id])->contain('Courses')->toArray();
              foreach($usercourse as $uc){ 
                  $ucourse['courseID']    =  $uc['course']['id'];
                  $ucourse['courseName']  = $uc['course']['course_name'];
                  $ucourse['level']       = $uc['course']['level_id'];
                  $ucourse['role']        = $uc['course']['author'];
                
                    $data['courses'][] = $ucourse;
                 } 
            }
            else{
              $data['message']= "No Courses available for this user";
            }          
      }
      else{ 
         $data['message'] = "No user exist on this id";       
      }

        $this->set([
               'data' => $data,
              '_serialize' => ['data']
            ]);
        }


    /**U11 – login User
     * loadComponent is defiened in this function for a time being. */
    public function login() {
      try {
        $this->loadComponent('Auth', [
          'authenticate' => [
            'Form' => [
              'fields' => [
                'username' => 'username',
                'password' => 'password',
              ]
            ]
          ],
  //          'loginAction' => [
  //            'controller' => 'Users',
  //            'action' => 'login'
  //          ]
        ]);
        $status = 'false';
        $token = $message = '';
        if ($this->request->is('post')) {
          $user = $this->Auth->identify();
          if ($user) {
            if ($user['status'] != 0) {
              $this->Auth->setUser($user);
              $token = $this->request->session()->id();
              $status = 'success';
            } else {
              $message = 'Please activate your account';
            }
          } else {
            $message = 'You entered either wrong email id or password';
          }
        }
      } catch (Exception $ex) {
        $this->log($ex->getMessage() . '(' . __METHOD__ . ')');
      }
      $this->set([
        'user' => $user,
        'status' => $status,
        'response' => ['secure_token' => $token],
        'message' => $message,
        '_serialize' => ['user', 'status', 'response', 'message']
      ]);
    }

  /*
        U12 – getUserRoles ( or profile you can say )
        Request – Int <UUID>
   */
     public function getUserRoles($id=null) { 
      $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();

      if($user_record>0){
          $userroles = TableRegistry::get('UserRoles');
          //$roles = TableRegistry::get('Roles');
          $userrole_records=$userroles->find('all')->where(['user_id' => $id])->contain('Roles')->count();

            if($userrole_records>0){
                $userrole=$userroles->find('all')->where(['user_id' => $id])->contain('Roles')->toArray();
                foreach($userrole as $ur){ $data['roles'][] = $ur->role;  }
            }
            else{
              $data['message']='No Roles is assigned to the user';
            }
           
      }
      else{ 
         $data['message'] = "Record is not found";       
      }

      $this->set([
             'data' => $data,
            '_serialize' => ['data']
          ]);

     }



      /*
        UB13 -  getUserServices ( may be few other services he availed )
        Request – Int <UUID>
   */
     public function getUserServices($id=null) { 
      $user_record = $this->Users->find()->where(['Users.id'=>$id])->count();

      if($user_record>0){
          $usermenus = TableRegistry::get('UserMenus');
          //$menus = TableRegistry::get('Menus');
          $menu_records=$usermenus->find('all')->where(['user_id' => $id])->contain('Menus')->count();

            if($menu_records>0){
              $usermenu=$usermenus->find('all')->where(['user_id' => $id])->contain('Menus')->toArray();
              foreach($usermenu as $um){ 
                  $menu['id']= $um->menu['id'];
                  $menu['name']= $um->menu['name'];
                  $menu['validity']=  $um['validity'] ;

                  $data['services'][] = $menu;


                 } 
            }
            else{
              $data['message']= "No Service available for this user";
            }          
      }
      else{ 
         $data['message'] = "Record is not found";       
      }

        $this->set([
               'data' => $data,
              '_serialize' => ['data']
            ]);

     }


      /*
        UB14 – getUserPurchaseHistory ( user_orders) order_date
        Request -  Int<UUID> , String<startDate / Null> , String <endDate / Null>
   */
     public function getUserPurchaseHistory($id=null) { 

          $userorder_records=$this->Users->find('all')->where(['id' => $id])->contain('UserOrders')->count();
          $userorders=$this->Users->find('all')->where(['id' => $id])->contain('UserOrders')->toArray();
          
        if($userorder_records>0){
              foreach ($userorders as $userorder) {
                  $orders=$userorder->user_orders;
                  if( isset($this->request->data['start_date']) && isset($this->request->data['end_date']) ){
                        $startdate = $this->request->data['start_date'];
                        $enddate = $this->request->data['end_date'];

                        foreach ($orders as $order) {
                            $orderdate = (new Time($order->order_date))->format('Y-m-d');
                            $odt=strtotime(date($orderdate));  
                            $start_date = strtotime(date($startdate)); 
                            $end_date = strtotime(date($enddate));             

                             if($start_date >= $odt || $end_date < $odt){
                                //$data['purchases'][]= $userorder->user_orders;
                              $data['message']= "All Recors of between dates are shown";
                              $data['purchases'][]= $order;
                             }
                             else{
                                $data['message'] = "Records are not available for selected date";
                             }                      
                      } 

                      }
                      else{
                            $data['message']= "All Recors are shown";
                            $data['purchases'][]= $userorder->user_orders;

                      }

              }

         }
         else{
            $data['message'] = "No Purchase Record is found";
         }
         $this->set([
               'data' => $data,
              '_serialize' => ['data']
            ]);
     }

        
     /**
        * UB15 – setUsercourse
        * Request –  String<CourseCode>
     **/
         public function setUsercourse($uid=null,$currentcourseid=null,$newcourseid=null) { 
           //$currentcourseid= $this->request->data('current_course_id');
           //$newcourseid= $this->request->data('new_course_id');
            if($uid!=null && $currentcourseid!=null && $newcourseid!=null){

                $this->loadModel('UserCourses');
                $ucourse_count = $this->UserCourses->find()->where([
                    'UserCourses.user_id' => $uid,
                    'UserCourses.course_id' => $currentcourseid
                   ])->count();
                if($ucourse_count>0){
                      $ucourse = $this->UserCourses->find()->where([
                        'UserCourses.user_id' => $uid,
                        'UserCourses.course_id' => $currentcourseid
                       ])
                      ->first();
                      $ucourse->course_id = $newcourseid;                    
                      if ($this->UserCourses->save($ucourse)) {
                          $data['message'] = 'saved';
                      }
                      else{
                          $data['message'] = 'not saved';
                      }
                }else{
                    $data['message'] = "No user course record exist in table";
                }

            }else{
              $data['message'] = "uid,current course id or new course id cannot be null ";
            }             

              $this->set([
               'data' => $data,
              '_serialize' => ['data']
            ]);

            }



     /**
        * UB19 – getMaxUserCountbyCourseCode
        * Request –  String<CourseCode>
     **/
      public function getMaxUserCountbyCourseCode($coursecode=null) {
        $usercourses = TableRegistry::get('UserCourses');
        if($coursecode!=null){             
            $usercourses = TableRegistry::get('UserCourses');
            $uc_records=$usercourses->find('all')
                         ->contain('Courses')
                         ->select(['course_id', 'registeredUsers' => 'count(*)' ])
                         ->where(['course_code' => $coursecode])
                         ->group('course_id');

                    foreach ($uc_records as $row) {
                       $data['course'][]=$row;
                    }
                    if(empty($data['course'])){
                              $data['message'] = "No Record found for this course code";
                          }

        }
        else{            
            $uc_records=$usercourses->find('all')
                         ->contain('Courses')
                         ->select(['course_id', 'registeredUsers' => 'count(*)' ])                         
                         ->group('course_id');

                         foreach ($uc_records as $row) {
                              $data['course'][]=$row;
                          }                          

        }
          $this->set([
               'data' => $data,
              '_serialize' => ['data']
            ]);

      }

      /*U17 Request – String <CourseCode>, String<username> , Int <roleID> */
       public function setUserRoleByuserName() {
         $status = $response = FALSE;
         if ($this->request->is('post')) {
           $user_id = '';
           $username = $this->request->data['username'];
           $role_id = $this->request->data['role_id'];
           $user = $this->Users->find()->select('Users.id')->where(['Users.username' => $username])->limit(1);
           foreach ($user as $row) {
             $user_id = $row->id;
           }
           $userroles = TableRegistry::get('UserRoles');
           $new_user_role = $userroles->newEntity(array('role_id' => $role_id , 'user_id' => $user_id));
           if ($userroles->save($new_user_role)) {
             $status = 'success';
             $response = TRUE;
           }
         }
         $this->set([
           'status' => $status,
           'response' => $response,
           '_serialize' => ['status', 'response']
         ]);

       }


       /**
        * function sendEmail().
        *
        * @param String $to
        *   contains the email to whom need to send.
        *
        * @param String $from
        *   contains seders email.
        *
        * @param String $subject
        *   contains the subject.
        *
        * @param String $email_message
        *   contains the email message.
        */
       protected function sendEmail($to, $from, $subject = null, $email_message = null) {
          try {
            $status = FALSE;
            //send mail
            $email = new Email();
            $email->to($to)->from($from);
            $email->subject($subject);
            if ($email->send($email_message)) {
              $status = TRUE;
            }
          } catch (Exception $ex) {
            $this->log($ex->getMessage());
          }
          return $status;
       }

       /**
        * function setUserPreference().
        *
        * setting user prefernce under Daily, Weekly or Fornightly basis.
        */
       public function setUserPreference() {
         try {
           $warning = $status = FALSE;
           $message = '';
           if ($this->request->is('post')) {
             $post_data = $this->request->data;
             $frequency = isset($post_data['frequency']) ? $post_data['frequency'] : '';
             $mobile = isset($post_data['mobile']) ? $post_data['mobile'] : '';
             $sms_subscription = isset($post_data['sms']) ? $post_data['sms'] : 0;
             $user_id = isset($post_data['user_id']) ? $post_data['user_id'] : 0;
             if (!empty($frequency)) {
               if (!empty($mobile)) {
                 if (preg_match('/^[0-9]{10}$/', $mobile)) {
                   $user_preference = TableRegistry::get('UserPreferences');
                   $preference_data = $user_preference->newEntity();
                   $preference_data['mobile'] = $mobile;
                   $preference_data['user_id'] = $user_id;
                   $preference_data['frequency'] = $frequency;
                   $preference_data['sms_subscription'] = $sms_subscription;
                   $preference_data['time'] = time();
                   if ($user_preference->save($preference_data)) {
                     $status = TRUE;
                     $username = 'abhishek@apparrant.com';
                     $api_hash = '623e0140ced100da648065a6583b6cfccf29d5fb16c024be9d5723ea2fe6adf3';
                     $sms_msg = 'Your Preferences are saved successfully @team MLG';
                     $sms_response = $this->sendSms($username, $api_hash, array($mobile), $sms_msg);
                     if ($sms_response['status'] == 'failure') {
                       if (isset ($sms_response['warnings'][0]['message'])) {
                         if ($sms_response['warnings'][0]['message'] == 'Number is in DND') {
                           $sms_response['warnings'][0]['message'].= '. Please Remove DND to receive our messages';
                         }
                         $warning = TRUE;
                         $message = $sms_response['warnings'][0]['message'];
                       } else {
                         $message = 'Unable to send message, Kindly contact to the administrator';
                         throw new Exception('Error code:' . $sms_response['errors'][0]['code'] . ' Message:' .  $sms_response['errors'][0]['message']);
                       }
                     }
                   } else {
                     $message = 'Some error occured';
                     throw new Exception('Unable to save data');
                   }
                 } else {
                   $message = 'Please enter valid mobile number';
                   throw new Exception('not valid mobile number');
                 }
               } else {
                 $message = 'Please enter mobile number';
                 throw new Exception('Mobile number can not be blank');
               }
             } else {
               $message = 'Please choose the frequency for the report';
             }
           }
         } catch (Exception $ex) {
           $this->log($ex->getMessage());
         }
         $this->set([
           'status' => $status,
           'warning' => $warning,
           'message' => $message,
           '_serialize' => ['status', 'warning',  'message']
         ]);
       }

       /**
        * function sendSms().
        */
       protected function sendSms($username, $hash, $numbers, $message) {
         try {
           // Message details
           $sender = urlencode('TXTLCL');
           $message = rawurlencode($message);
           $numbers = implode(',', $numbers);
           // Prepare data for POST request
           $data = array('username' => $username, 'hash' => $hash,
             'numbers' => $numbers, "sender" => $sender, "message" => $message);
           // Send the POST request with cURL
           $ch = curl_init('http://api.textlocal.in/send/');
           curl_setopt($ch, CURLOPT_POST, true);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           $json_response = curl_exec($ch);
           curl_close($ch);
           $response = json_decode($json_response, TRUE);
           if ($response['status'] == 'failure') {
             throw new Exception('unable to send message. Response: ' . $json_response);
           }
         } catch (Exception $ex) {
           $this->log($ex->getMessage() . '(' . __METHOD__ . ')');
         }
         return $response;
       }

       /**
        * funnction setStaticContents().
        */
       public function setStaticContents() {
         $status = FALSE;
         try {
          if ($this->request->is('post')) {
            $static_contents = TableRegistry::get('StaticContents');
            $title = $this->request->data['title'];
            $description = addslashes($this->request->data['description']);
            $created = $modified = time();
            $contents = $static_contents->find()->where(['title' => $title]);
            if ($contents->count()) {
              foreach($contents as $content) {
                $content->description = $description;
                $content->modified = $modified;
              }
            } else {
              $content = $static_contents->newEntity(array(
                'title' => $title,
                'description' => $description,
                'created' => $created,
                'modified' => $modified
                )
              );
            }

            if ($static_contents->save($content)) {
              $status = TRUE;
              $id = $content->id;
            }
          }
         } catch (Exception $ex) {
           $this->log($ex->getMessage(). '(' . __METHOD__ . ')');
         }

         $this->set([
           'status' => $status,
           'id' => $id,
           '_serialize' => ['status', 'id']
         ]);
       }

       /**
        * funnction getStaticContents().
        */
       public function getStaticContents() {
         $content = array();
         $status = FALSE;
         try {
           if ($this->request->is('post')) {
             $title = $this->request->data['title'];
             $static_contents = TableRegistry::get('StaticContents');
             $static_data = $static_contents->find('all')->where(['title' => $title])->toArray();
             if ($static_data) {
              foreach($static_data as $data) {
                $content['title'] = $data->title;
                $content['description'] = stripslashes($data->description);
                $content['created'] = $data->created;
                $content['modified'] = $data->modified;
              }
             }
           }
         } catch (Exception $ex) {
           $this->log($ex->getMessage(). '(' . __METHOD__ . ')');
         }

         $this->set([
           'content' => $content,
           '_serialize' => ['content']
         ]);
       }

       /**
        * function paymentbrief().
        */
       public function getPaymentbrief() {
         $status = FALSE;
         $message = '';
         $child_info = array();
         $total_amount = 0;
         if ($this->request->is('post')) {
          try {
            $parent_id = isset($this->request->data['user_id']) ? $this->request->data['user_id'] : '';
            if (!empty($parent_id)) {

              $user_details = TableRegistry::get('UserDetails');
              $user_info = $user_details->find()->select('user_id')->where(['parent_id' => $parent_id]);
              $parent_children = array();
              foreach ($user_info as $user) {
                $parent_children[] = $user->user_id;
              }

              $connection = ConnectionManager::get('default');
              $sql = "SELECT users.first_name as user_first_name, users.last_name as user_last_name,"
                . " user_purchase_items.amount as purchase_amount, packages.name as package_subjects, plans.name as plan_duration"
                . " FROM users"
                . " INNER JOIN user_purchase_items on user_purchase_items.user_id=users.id"
                . " INNER JOIN packages ON user_purchase_items.package_id=packages.id"
                . " INNER JOIN plans ON user_purchase_items.plan_id=plans.id"
                . " WHERE user_purchase_items.user_id IN (" . implode(',', $parent_children) . ")";
              $user_detail_result = $connection->execute($sql)->fetchAll('assoc');
              $results = $connection->execute($sql)->fetchAll('assoc');
              if (!empty($results)) {
                $status = TRUE;

                foreach ($results as $result) {
                  $child_info[] = array(
                    'child_name' => $result['user_first_name'] . ' ' . $result['user_last_name'],
                    'package_subjects' => $result['package_subjects'],
                    'package_amount' => $result['purchase_amount'],
                    'plan_duration' => $result['plan_duration'],
                  );
                  $total_amount += $result['purchase_amount'];
                }
              } else {
                $message = 'No record found';
                throw new Exception($message);
              }
            } else {
              $message = 'Parent id cannot be blank';
              throw new Exception('Parent id is null');
            }
          } catch (Exception $ex) {
            $this->log($ex->getMessage() . '(' . __METHOD__ . ')');
          }
         }

         $this->set([
           'status' => $status,
           'message' => $message,
           'data' => $child_info,
           'total_amount' => $total_amount,
           '_serialize' => ['status', 'data', 'total_amount', 'message']
         ]);
       }

   

       public function getGradeList() {
          $levels = TableRegistry::get('Levels')->find('all');
          foreach ($levels as $level) {
              $data['Grades'][]= $level;
          }
          $this->set([           
           'response' => $data,
           '_serialize' => ['response']
         ]);

       }

       public function getPlanList() {
          $plans = TableRegistry::get('Plans')->find('all');
          foreach ($plans as $plan) {
              $data['plans'][]= $plan;
          }
          $this->set([           
           'response' => $data,
           '_serialize' => ['response']
         ]);
       }

        public function getPackageList() {
            $packages = TableRegistry::get('Packages')->find('all');
            foreach ($packages as $package) {
                $data['package'][]= $package;
            }
            $this->set([           
             'response' => $data,
             '_serialize' => ['response']
           ]);
       }

       public function logout() {
            $this->loadComponent('Auth', [
          'authenticate' => [
            'Form' => [
              'fields' => [
                'username' => 'username',
                'password' => 'password',
              ]
            ]
          ],
            
        ]);
            $this->Auth->logout();

            $this->set([           
             'response' => true,
             '_serialize' => ['response']
           ]);
       }

      
     

      public function setCountOfChildrenOfParent($id,$child_count){
          if(isset($id) && isset($child_count) ){            
              $user_details = TableRegistry::get('UserDetails');
              $query = $user_details->query();
              $result=  $query->update()
                      ->set(['no_of_children' => $child_count])
                      ->where(['user_id' => $id])
                      ->execute();
              $affectedRows = $result->rowCount();

              if($affectedRows>0)
                $data['status']="True";
              else
                $data['status']="False";

              $this->set([           
             'response' => $data,
             '_serialize' => ['response']
           ]);
          }
      }

      public function getCountOfChildrenOfParent($pid){
        if(isset($pid)){
          $user_details = TableRegistry::get('UserDetails')->find('all')->where(['user_id'=>$pid]);
          //$rowcounts=$user_details->rowCount();
          $data['number_of_children']=0;
          foreach($user_details as $user_detail){            
            $data['number_of_children'] = $user_detail['no_of_children'];
          }
          $this->set([           
             'response' => $data,
             '_serialize' => ['response']
           ]);
        }
      }


      public function getChildrenListOfParent($pid){
            if(isset($pid)){
                $added_children = TableRegistry::get('UserDetails')->find('all')->where(['parent_id'=>$pid])->count();
               // $rowcounts=$user_details->rowCount();
                $data['added_children'] = $added_children;                 
            }else{
              $data['message'] = 'Set parent_id';
            }

        $this->set([           
                 'response' => $data,
                 '_serialize' => ['response']
               ]);

      }

      public function getChildrenDetails($pid){
          $childRecords=TableRegistry::get('UserDetails')->find('all')->where(['parent_id'=>$pid])->contain(['Users']);
          foreach ($childRecords as $childRecord) {
            $fname=$childRecord->user['first_name'];
            $lname=$childRecord->user['last_name'];
            $data['children_name'][]=$fname.' '.$lname;          
          }

          $this->set([           
                 'response' => $data,
                 '_serialize' => ['response']
               ]);

      }

       public function addChildren() {
          $postdata[]=$this->request->data;

           $users=TableRegistry::get('Users');
           $user_details=TableRegistry::get('UserDetails');
           $user_roles=TableRegistry::get('UserRoles');
           $user_courses=TableRegistry::get('User_Courses');
           $user_purchase_items=TableRegistry::get('User_purchase_items');

           $subtotal=0;


            if ($this->request->is('post')) { 

                // to find discount in selected package
                $packs= TableRegistry::get('Packages')->find('all')->where(["id"=>$this->request->data['package_id'] ]);
                foreach ($packs as $pack) {
                    $upurchase['discount']=$pack['discount'];
                    $discount_type=$pack['type'];                    
                } 

                // find number of month in selected plan
                $plans= TableRegistry::get('Plans')->find('all')->where(["id"=>$this->request->data['plan_id'] ]);
                foreach ($plans as $plan) {
                    $num_months=$plan['num_months'];                                     
                }          
              
                

                $new_user = $this->Users->newEntity($this->request->data);   
                if ($result=$users->save($new_user)) {
                      $udetails['user_id'] = $result->id;
                      $udetails['parent_id'] = $this->request->data['parent_id'];
                      $udetails['dob']=$this->request->data['dob'];
                      $udetails['school']=$this->request->data['school'];                     
                      $new_user_details = $user_details->newEntity($udetails);
                    if ($user_details->save($new_user_details)) {
                          $urole['user_id']=$result->id;
                          $urole['role_id']=$this->request->data['role_id'];
                          $new_user_roles = $user_roles->newEntity($urole);                        
                      if ($user_roles->save($new_user_roles)) {
                          $courses=$this->request->data['courses'];

                          foreach ($courses as $course_id => $name) {
                            $ucourse['user_id']= $result->id;
                            $ucourse['course_id']= $course_id;
                            $new_user_courses = $user_courses->newEntity($ucourse);
                            if ($user_courses->save($new_user_courses)) {
                                $upurchase['user_id']=$result->id;
                                $upurchase['course_id']=$course_id;
                                $upurchase['plan_id']= $this->request->data['plan_id'];
                                $upurchase['package_id']=$this->request->data['package_id'];
                                $upurchase['level_id']=$this->request->data['level_id'];

                                $courseamount=TableRegistry::get('Courses')->find('all')->where(['id'=>$course_id]);
                                foreach ($courseamount as $camount) {
                                    $cramount=$camount['price']; 
                                    $upurchase['course_price']=$camount['price'];
                                                                                       
                                }

                                if($discount_type=="fixed"){
                                   $upurchase['amount']=($cramount-$upurchase['discount'])*($num_months);
                                }
                                if($discount_type=="percent"){
                                  $upurchase['amount']=($cramount-($cramount*($upurchase['discount'])*0.01))*($num_months);
                                }

                                $data[]=$upurchase;
                                $new_user_purchase_items = $user_purchase_items->newEntity($upurchase);

                              if ($user_purchase_items->save($new_user_purchase_items)) {
                                  $data['message']='The child has been saved';
                              }
                              else{ $data['message']='Purchase history of child is not saved'; }
                            //$data['message']='The child courses are saved';
                            }
                            else{ $data['message']='The child course is not saved';}
                          }                         
                        //$data['message']= 'user role has been saved';
                         
                      }
                      else{
                        $data['message']= 'User Role detail is not saved';
                      }
                    
                                         
                    }
                    else{
                      $data['message']= 'User Details is not saved';
                    }
                   
                    
                    
                }
                
                
                
                
            }
          else{
            $data['message']='No data to save';
          }

         $this->set([           
                 'response' => $data,
                 '_serialize' => ['response']
               ]);

       }       


       public function priceCalOnCourse(){
        $postdata = file_get_contents("php://input");
        $request = json_decode($postdata);
        $id_list="0";
        foreach ($request as $key => $value) {
          if($value!=""){
              $id_list=$id_list.','.$key;
              //$data['value'][]=$value;
            }
        }
        $course_ids='('.$id_list.')';        
        $connection = ConnectionManager::get('default');
        $sql = "SELECT sum(price) as amount From courses where id IN $course_ids";
            
          $results = $connection->execute($sql)->fetchAll('assoc');
            foreach ($results as $result) { 
                if($result['amount']!=null){$data['amount']=$result['amount'];}
                else{$data['amount']=0;}              
            }
        $this->set([           
                 'response' => $data,
                 '_serialize' => ['response']
               ]);       

       }





}
