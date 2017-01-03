<?php	

/*-------------------------------------------------------------------------
	File Hosting Module for tout-debrid.eu for DownloadStation by Synology
 version 1.1
---------------------------------------------------------------------------
 v1.0 : initial release.
 v1.1 : add account type detection (free/premium)
---------------------------------------------------------------------------
 by Sylver (codeisalie@gmail.com)
 inspired from alldebrid plugin (by keltharak  keltharak@hotmail.com)
---------------------------------------------------------------------------*/
class SynoFileHostingToutDebrid
{	
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
    private $SessionID;
	private $TOUTDEBRID_COOKIE = '/tmp/toutdebrid.cookie';
	private $TOUTDEBRID_LOGIN_URL = 'http://tout-debrid.eu/login.php';
    private $TOUTDEBRID_DEBRID_URL = 'http://tout-debrid.eu/generateur-all.php';
    private $TOUTDEBRID_ACCOUNT_URL = 'http://tout-debrid.eu/compte';
    private $TOUTDEBRID_PREMIUM_ACCOUNT_KEYWORD = 'premium';

	
    public function __construct($url, $username, $password, $hostInfo) {
/*
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', '/tmp/php_error.log');
        ini_set('error_reporting', E_ALL);*/
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
    }

   
    // Main function called by DS to get a valid download link for an URL.
	// success : 
	//		return an array {
	//			DOWNLOAD_URL = premium url
	//			DOWNLOAD_FILENAME = filename
	// 			DOWNLOAD_COOKIE = path to toutdebrid cookie
	//			DOWNLOAD_COUNT = download count before start
	//		}
	// error :
	// 		return array {
	//			DOWNLOAD_ERROR = Synology error code (see common.php in synology documentation)
	//		}
    public function GetDownloadInfo() {
		$this->Log("Lancement du module");
		$VerifyRet = $this->Verify(FALSE);
		if (LOGIN_FAIL == $VerifyRet) {
			$this->Log("Login Failed");
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
			$ret = $DownloadInfo;
		} else {
			$ret = $this->DownloadPremium();
			//$ret[DOWNLOAD_COUNT] = 5;
			$this->Log("lien renvoye vers DlStation : ".$ret[DOWNLOAD_URL]);
		}
		$this->Log("Fin d'execution du module");
		return $ret;
    }
	
	//This function verifies and returns account type.
	//Should be called when click on Verify button but doesn't work yet
	// success :
	// 		return USER_IS_PREMIUM or USER_IS_FREE synology code (see common.php in synology documentation)
	// error :
	//		return LOGIN_FAIL synology code (see common.php in synology documentation)
	public function Verify($ClearCookie) 
	{
		$ret = $this->ToutDebridLogin($this->username, $this->password);
		if (FALSE == $ret) {
			return LOGIN_FAIL;
		} else if ($this->IsFreeAccount()) {
            return USER_IS_FREE;
        } else {
            return USER_IS_PREMIUM;
		}
	}

    //This function checks if the account is paid or free.
    //return TRUE if free account, FALSE if premium account
    private function IsFreeAccount() {
        $ret = TRUE;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->TOUTDEBRID_COOKIE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $this->TOUTDEBRID_ACCOUNT_URL);
        $AccountRet = curl_exec($curl);
        curl_close($curl);
        preg_match('/Compte:.*<b>(.*)<\/b>/', $AccountRet, $match);
        if (isset($match[0])) {
            $compare = strtolower($match[0]);
            if (strstr($compare, $this->TOUTDEBRID_PREMIUM_ACCOUNT_KEYWORD)) {
                $ret = FALSE;
            }
        }
        return $ret;
    }

	//This function performs login action.
	// success : 
	// 		return toutdebrid authentication session ID value
	// error :
	//		return FALSE
	private function ToutDebridLogin($Username, $Password) {
		$this->Log("Login : ".$Username);
		$this->Log("Password : ".$Password);
		$ret = FALSE;

        $post = [
            'btnSubmited' => 'Se+connecter',
            'txtEmail' => $Username,
            'txtMdp'   => $Password,
        ];

		$queryUrl = $this->TOUTDEBRID_LOGIN_URL;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->TOUTDEBRID_COOKIE);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_URL, $queryUrl);
		$LoginInfo = curl_exec($curl);
		curl_close($curl);
		if (FALSE != $LoginInfo && file_exists($this->TOUTDEBRID_COOKIE)) {
			$ret = parse_cookiefile($this->TOUTDEBRID_COOKIE);
            $this->Log('Cookies : '.print_r($ret,1));
			if (!empty($ret['owner'])) {
				$this->Log("Login page successful");
                $ret = $ret['PHPSESSID'];
			} else {
				$this->Log("Login page failed");
				$ret = FALSE;
			}
		}
		return $ret;
	}
	
	// This function submit link to toutdebrid api
	// return alldebrid website string response in html format
	private function DownloadParsePage() {
        $this->Log("Debridage du lien : ".$this->url);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->TOUTDEBRID_COOKIE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $this->TOUTDEBRID_DEBRID_URL);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'urllist='.urlencode($this->url).'&captcha=none&');
		$ret = curl_exec($curl);
		$this->Log("Reponse tout-debrid : ".$ret);
		curl_close($curl);
		return $ret;
	}
	
	//This function get premium download url from.
	// success :
	//		return array {
	//			DOWNLOAD_URL = premium url
	//			DOWNLOAD_FILENAME = filename
	//		}
	// error : 
	//		return array {
	//			DOWNLOAD_ERROR = ERR_FILE_NO_EXIST synology code (see common.php in synology documentation)
	//		}
	private function DownloadPremium() {
		$this->Log("Recuperation du lien premium");
		$page = $this->DownloadParsePage();
		$DownloadInfo = array();

        // Find link of file to download
        preg_match('/href=\'(.*?)\'/', $page, $link);
        if (!empty($link[1])) {
            $returl = $link[1];
            $DownloadInfo[DOWNLOAD_URL] = $returl;
            $this->Log("Lien debride :  ".$DownloadInfo[DOWNLOAD_URL]);

            preg_match('/<font color=\'#00CC00\'>(.*?)<\/font>/', $page, $filename);
            if (!empty($filename[1])) {
                $retfilename = $filename[1];
                $DownloadInfo[DOWNLOAD_FILENAME] = $retfilename;
                $DownloadInfo[INFO_NAME] = "Tout-debrid";
                $DownloadInfo[DOWNLOAD_ISPARALLELDOWNLOAD] = TRUE;
                $this->Log("Nom du fichier :  ".$DownloadInfo[DOWNLOAD_FILENAME]);
            }

        } else {
            // Link not found, find error
            // <b><font color=red> Ressayer dans quelques minutes, merci. </font></b>
            // <div style="font-size:14px; color:white;background-color:#01A9DC;padding:5px;"><b>Lien mort ou invalide</b></div>
            preg_match('/Ressayer dans quelques minutes/', $page, $error);
            if (!empty($error[0])) {
                $DownloadInfo[DOWNLOAD_ERROR] = ERR_TRY_IT_LATER;
                $this->Log("Error : Try Later");
            } else {
                preg_match('/Lien mort ou invalide/', $page, $error);
                if (!empty($error[0])) {
                    $DownloadInfo[DOWNLOAD_ERROR] = ERR_BROKEN_LINK;
                    $this->Log("Error : Broken link");
                } else {
                    $DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
                    $this->Log("Unknown error : ".$page);
                }
            }
        }

		return $DownloadInfo;
	}
	
	// this function allow debug of the module in synology system journal
	// 'system' line should be commented in any official release.
	private function Log($msg) {
//        error_log( '[LOG] '.$msg);
	}
}
?>
