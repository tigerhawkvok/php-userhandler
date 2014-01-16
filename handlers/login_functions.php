<?php

class UserFunctions {


  public function microtime_float()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }


  
  public function createUser($username,$pw_in,$name,$dname,$zip=null)
  {
    // Send email for validation
    require_once('handlers/db_hook.inc');
    $user=sanitize($username); 
    $preg="/[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[A-Z]{2}|com|org|net|edu|gov|mil|biz|info|mobi|name|aero|asia|jobs|museum)\b/";
    /***
     * Uncomment the next line if strict username comparison is needed
     ***/
    //if($user!=$username) return array(false,'Your chosen email contained injectable code. Please try again.');
    if(preg_match($preg,$username)!=1) return array(false,'Your email is not a valid email address. Please try again.');
    else $username=$user; // synonymize
    $result=lookupItem($user,'username',null,null,false,true);
    if($result!==false) 
      {
        $data=mysqli_fetch_assoc($result);
        /*echo "<pre>";
          print_r($data);
          echo "\n" . microtime_float() . "\n";
          echo "</pre>";*/
        if($data['username']==$username) return array(false,'Your chosen username is already taken. Please try again.');
      }
    require_once('CONFIG.php');
    if(strlen($pw_in)<$minimum_password_length) return array(false,'Your password is too short. Please try again.');
    require_once('stronghash/php-stronghash.php');
    $hash=new Stronghash;
    $creation=$this->microtime_float();
    $pw1=$hash->hasher($pw_in);
    $pw_store=json_encode($pw1);
    $algo=$pw1['algo'];
    $salt=$pw1['salt'];
    if(!empty($pw1['rounds'])) $rounds="<rounds>".$pw1['rounds']."</rounds>";
    $data_init="<xml><algo>$algo</algo>$rounds</xml>";
    $ne=encryptThis($name,$pw_in,$salt); // only encrypt if requested, then put in secdata
    $sdata_init="<xml><name>".$ne[0]."</name></xml>";
    $names="<xml><fname>$name</fname><dname>$dname</dname></xml>";
    $hardlink=sha1($salt.$creation);
    //echo "<pre>Storing:\n";
    $fields=array('username','password','pass_meta','creation','status_tracker','name','flag','admin_flag','su_flag','disabled','dtime','auth_key','data','secdata','special_1','special_2','dblink','defaults','public_key','private_key');
    //print_r($fields);
    //echo "\nwith the following information\n";
    $store=array($user,$pw_store,'',$creation,'',$names,true,false,false,false,0,'',$data_init,$sdata_init,'','',$hardlink,'','',''); // set flag to FALSE if authentication wanted.
    //print_r($store);
    $test_res=addItem($fields,$store);
    if($test_res)
      {
        // Get ID value
        global $default_table;
        $result=lookupItem($user,'username',$default_table,null,false,true);
        //echo "\nLooking up $user ...obtained \n ";
        $data=@mysqli_fetch_assoc($result);
        $id=$data['id'];
        /*print_r($test_res);
          print_r($result);
          print_r($data);
          echo addItem($fields,$store,null,null,true);
          echo "</pre>";*/

        /* Uncomment if authentication has been requested */
        /*
        // Create hash - user + encrypted name + salt
        $ne=$ne[0];
        $hash=sha1($user.$ne.$salt);
        $validlink=$baseurl."/login.php?confirm=$hash&amp;token=$creation&amp;lookup=$id";
        $affix="&amp;email=".htmlentities($email_in);
        $validlink.=$affix;
        // email jen
        $email='blackhole@'.substr($baseurl,strpos($baseurl,'.'));
        $to='admin@'.substr($baseurl,strpos($baseurl,'.'));
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= "From: $name (via $title) <$email>";
        $subject="[User Signup] New User - $name";
        $body="<p>$name is requesting access to files for $title. You can click the following link to enable access to the files, and click the link later to disable access.</p>\n<p><a href='$validlink'>$validlink</a><p>\n<p>Thank you. For debugging purposes, the user was hashed with $algo.</p>";
        if(mail($to,$subject,$body,$headers))
        {
        //mail('support@velociraptorsystems.com',$subject,$body,$headers); // debugging confirmation
        return array(true,"Success! You will receive confirmation when your account has been activated.");
        }
        */
        if (is_numeric($id)) 
          {
            $res = $this->lookupUser($username, $pw_in,true);
            $userdata=$res[1];
            $id=$userdata['id'];

            /*echo "<pre>";
              print_r($userdata);
              echo "\nRes\n";
              print_r($res);
              echo "</pre>";*/
            //Set a cookie
            $cookiename=str_replace(" ","",$title);
            $domain=".".substr($baseurl,strpos($baseurl,'.'));
            $expire=time()+3600*24*7; // one week
            // Create a one-time key, store serverside
            $otsalt=$hash->genUnique();
            //store it
            $query="UPDATE $default_table SET auth_key='$otsalt' WHERE id='$id'";
            $l=openDB();
            mysqli_query($l,'BEGIN');
            $result=mysqli_query($l,$query);
            /*echo "<pre>Sent query: $query obtaining result:\n";
              print_r($result);
              echo "</pre>";*/
            if(!$result) 
              {
                $r=mysqli_query($l,'ROLLBACK');
                echo "<p class='error'>".mysqli_error($l)."<br/><br/>ERROR: Could not log in.</p>";
                if($r===false)
                  {
                    // error reporting
                  }
                mysqli_close($l);
              }
            else
              {
                $r=mysqli_query($l,'COMMIT');
                mysqli_close($l);
                $value_create=$userdata['salt'].$otsalt.$_SERVER['REMOTE_ADDR']; 
                // authenticated since last login. Nontransposable outside network.
                $value=sha1($value_create);
                $cookieuser=$cookiename."_user";
                $cookieauth=$cookiename."_auth";
                $cookiealg=$cookiename."_alg";
                $cookiepic=$cookiename."_pic";
                $cookiepic=$cookiename."_pic";
                setcookie($cookieauth,$value['hash'],$expire);
                setcookie($cookiealg,$value['algo'],$expire);//,null,$domain);
                setcookie($cookieuser,$userdata['username'],$expire);//,null,$domain);
                $path=$this->getUserPicture($userdata['id'],'userdata/profilepics');
                setcookie($cookiepic,$path,$expire);//,null,$domain);
                // some secure, easy way to access their name?
                // Need access -- name (id), email. Give server access?
                $logged_in=true;

                if(isset($_COOKIE[$cookieuser]) || $logged_in===true)
                  {
                    $cookiedebug.="cookie-enter";
                    // Cookies are set
                    $result=lookupItem($_COOKIE[$cookieuser],'username',null,null,false,true);
                    if($result!==false)
                      {
                        // good user
                        // Check auth
                        $cookiedebug.=' check-auth';
                        $userdata=mysqli_fetch_assoc($result);
                        $salt=$userdata['salt'];
                        $unique=$userdata['auth_key'];
                        $ip=$_SERVER['REMOTE_ADDR'];
                        $auth=sha1($salt.$unique.$ip);
                        if($auth['hash']==$_COOKIE[$cookieauth])
                          {
                            // Good cookie
                            $cookiedebug.=' good auth';
                            $logged_in=true;
                            $user=$_COOKIE[$cookieuser];
                          }
                        else
                          {
                            // bad cookie
                            $cookiedebug.=' bad-auth';
                            $domain=".".substr($baseurl,strpos($baseurl,'.'));
                            setcookie($cookieuser,false,time()-3600*24*365,null,$domain);
                            setcookie($cookieauth,false,time()-3600*24*365,null,$domain);
                            setcookie($cookiealg,false,time()-3600*24*365,null,$domain);
                            setcookie($cookiealg,false,time()-3600*24*365,null,$domain);
                          }
                      }
                    else
                      {
                        // bad user
                        $cookiedebug.=' bad-user';
                        $domain=".".substr($baseurl,strpos($baseurl,'.'));
                        setcookie($cookiuser,false,time()-3600*24*365,null,$domain);
                        setcookie($cookieauth,false,time()-3600*24*365,null,$domain);
                        setcookie($cookiealg,false,time()-3600*24*365,null,$domain);
                        setcookie($cookiealg,false,time()-3600*24*365,null,$domain);
                      }
                  }
                else 
                  {
                    $logged_in=false;
                    $cookiedebug.='cookies not set for '.$cookiename;
                  }
                ob_end_flush();
              }

            return array_merge(array(true,'Sucess!'),$userdata);
          }
        else return array(false,'Failure: Unable to verify user creation');
      }
    else return array(false,'Failure: unknown database error. Your user was unable to be saved.');
  }

  function lookupUser($username,$pw,$return=false)
  {
    // check it's a valid email! validation skipped.
    $xml=new Xml;
    $result=lookupItem($username,'username',null,null,false);
    $userdata=mysqli_fetch_assoc($result);
    if($result!==false && is_numeric($userdata['id']))
      {
        /*    echo "<pre>Lookup User Function for '$username' ..\n";
              print_r($userdata);
              echo "\nResult data:\n";
              print_r($result);
              echo "</pre>";*/
        // check password
        require_once('stronghash/php-stronghash.php');
        $hash=new Stronghash;
        $data=json_decode($userdata['password'],true);
        if($hash->verifyHash($pw,$data))
          {
            if($userdata['flag'] && !$userdata['disabled']) 
              {
                //This user is OK and not disabled, nor pending validation
                if(!$return) 
                  {
                    //Return decrypted userdata, if applicable
                    $decname=decryptThis($userdata['name'],$pw,$salt);
                    if(empty($decname))$decname=$userdata['name'];
                    return array(true,$decname);
                  }
                else 
                  {
                    $decname=decryptThis($userdata['name'],$pw,$salt);
                    if(empty($decname))$decname=$userdata['name'];
                    $userdata['dec_name']=$decname;
                    $returning=array(true,$userdata);
                    return $returning;
                  }
              }
            else 
              {
                if(!$userdata['flag'])return array(false,'Your login information is correct, but your account is still being validated, or has been disabled. Please try again later.');
                if($userdata['disabled'])
                  {
                    // do a time check
                    if($userdata['dtime']+3600>$this->microtime_float()) 
                      {
                        $rem=intval($userdata['dtime'])-intval($this->microtime_float())+3600;
                        $min=$rem%60;
                        $sec=$rem-60*$min;
                        return array(false,'Your account has been disabled for too many failed login attempts. Please try again in '.$min.' minutes and '.$sec.' seconds.');
                      }
                    else 
                      {
                        // Clear login disabled flag
                        global $default_table;
                        $query1="UPDATE `$default_table` SET disabled=false WHERE id=".$userdata['id'];
                        $res1=openDB();
                        $result=execAndCloseDB($query1);
                      }
                  }
                // All checks passed.
                if(!$return) 
                  {
                    //echo "<p>Return was false.</p>";
                    $decname=decryptThis($userdata['name'],$pw,$salt);
                    if(empty($decname))$decname=$userdata['name'];
                    return array(true,$decname);
                  }
                else 
                  {
                    $decname=decryptThis($userdata['name'],$pw,$salt);
                    if(empty($decname))$decname=$userdata['name'];
                    $userdata['dec_name']=$decname;
                    $returning=array(true,$userdata);
                    return $returning;
                  }
              }
          }
        else
          {
            return array(false,'Bad password');
          }
        // end good username loop 
      }
    else return array(false,'Unrecognized username');
  }


  public function getUserPicture($id,$path)
  {
    if(substr($path,-1)!="/") $path=$path."/";
    $valid_ext=array('jpg','jpeg','png','bmp','gif');
    foreach($valid_ext as $ext)
      {
        $file=$id.".".$ext;
        if(file_exists($path.$file)) return $path.$file;
      }
    return $path."default.jpg";
  }

  public function validateUser($userid,$hash,$strong=false,$detail=false)
  {
    // return true or false based on user validation. 
    // Prevent ajax or post call manually -- has to be called from a webpage
    // maybe this should check the cookies?
    /*
      Notes: This does not prevent inspected HTML data on a hacked account. This ensures that either:
      1) The person gained access to the account
      2) The person hacked both the database and the webserver
    */
    $result=lookupItem($userid,'hardlink',null,null,false);
    if($result!==false)
      {
        global $authsalt;
        $userdata=mysqli_fetch_assoc($result);
        $salt=$userdata['salt'];
        $data=$authsalt.$userid.$salt;
        $conf=sha1($data);
        // possibly compare to the cookie
        //$retval = $strong!==false ? true && doesThis($strong,$userid):true;
        if($conf==$hash) return $retval;
      }
    if($detail) return array("uid"=>$userid,"auth"=>$authsalt,"salt"=>$salt,"conf"=>$conf,"given_conf"=>$hash);
    return false;
  }

  public function createCookieTokens($userdata,$title) {
    $id=$userdata['id'];
    //Set a cookie
    $baseurl=$_SERVER['HOST_NAME'];
    $base=array_slice(explode(".",$baseurl),-2);
    $cookiename=implode(".",$base);
    $domain=".".substr($baseurl,strpos($baseurl,'.'));
    $expire=time()+3600*24*7; // one week
    // Create a one-time key, store serverside
    require_once('stronghash/php-stronghash.php');
    require_once('handlers/db_hook.php');
    $hash=new Stronghash;
    $otsalt=$hash->genUnique();
    //store it
    global $default_table;
    $query="UPDATE `$default_table` SET cookie_key='$otsalt' WHERE id='$id'";
    $l=openDB();
    $result=mysqli_query($l,$query);
    if(!$result) return array(false,'status'=>false,'error'=>"<p>".myslqi_error($l)."<br/><br/>ERROR: Could not log in.</p>");
    $value_create=$userdata['salt'].$otsalt.$_SERVER['REMOTE_ADDR']; 
    // authenticated since last login. Nontransposable outside network.
    $value=sha1($value_create);
    $cookieuser=$cookiename."_user";
    $cookieauth=$cookiename."_auth";
    $cookiealg=$cookiename."_alg";
    $cookiepic=$cookiename."_pic";
    /*echo "<pre>";
      echo "Cookie Info: $cookieuser $cookiealg $cookiepic \n $cookieauth :";
      print_r($value);
      echo "/<pre>";*/
    setcookie($cookieauth,$value['hash'],$expire);
    setcookie($cookiealg,$value['algo'],$expire);//,null,$domain);
    setcookie($cookieuser,$userdata['username'],$expire);//,null,$domain);
    $path=$this->getUserPicture($userdata['id'],'userdata/profilepics');
    setcookie($cookiepic,$path,$expire);//,null,$domain);
    return array(true,'status'=>true,'user'=>"{ $cookieuser :".$userdata['username']."}",'auth'=>"{ $cookieauth :".$value['hash']."}",'algo'=>"{ $cookiealg :".$value['algo']."}");
  }
  
}
?>