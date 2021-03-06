<?php
  function customPageHeader() {
    print("\n\t\t" . '<link rel="stylesheet" href="piet.css">');
    //print("\n\t\t" . '<link rel="stylesheet" href="assignment8.css">');

    print("\n\t\t" . '<script src="/js/jquery-3.3.1.min.js"></script>');
  }

  function customPageFooter() {
    //print("\n\t\t" . '<script src="/js/jquery-3.3.1.min.js"></script>');

    print("\n\t\t" . '<script src="stylize.js"></script>');
    print("\n\t\t" . '<script src="fileupload.js"></script>');
    print("\n\t\t" . '<script src="ajax-api.js"></script>');
  }

  // https://stackoverflow.com/a/2397010/6828099
  define('INCLUDED', 1);
  require_once 'mysql.php';

  $loadPage = new loadPage();
  $sqlCommands = new sqlCommands();
  $mainPage = new PietUploader();

  $loadPage->loadHeader();

  // While the PHP form uses the web user, the bash script uses the piet user.
  // The piet user will only have read permission while PHP will get read/write.
  // I created a whole separate database for the piet project.
  $sqlCommands->setLogin(getenv('alex.server.phpmyadmin.host'),
                          getenv('alex.server.phpmyadmin.username'),
                          getenv('alex.server.phpmyadmin.password'),
                          getenv('alex.server.piet.database'));

  $sqlCommands->testConnection();
  $sqlCommands->connectMySQL();
  $sqlCommands->createTable();

  $mainPage->setVars();

  $mainPage->printSourceCodeLink();
  $upload_metadata = $mainPage->checkUpload();

  //print("Metadata: $upload_metadata");

  if(is_array($upload_metadata)) {
    $mainPage->verifyMySQLVars($upload_metadata);
    $mainPage->printVirusScanResults();
  }

  $mainPage->printUploadForm();

  $loadPage->loadFooter();

  class PietUploader {
    public $piet_upload_path;
    #public $antivirus_log_path;

    public $exec_virusscan_path;
    #public $exec_maldet_path;
    public $exec_echo_path;

    public $piet_launcher;
    //public $piet_url;
    //public $piet_arguments;

    function setVars() {
      $this->piet_launcher = "/piet/launcher/";

      if(getenv('alex.server.type') === "production") {
        # The below variables are for the production server
        $this->piet_upload_path = "/var/web/term-uploads/";
        #$this->exec_maldet_path = "/usr/local/sbin/maldet";
        $this->exec_virusscan_path = "/home/admin/automated/VirusScan.sh";
        $this->exec_echo_path = "/bin/echo";
        #$this->antivirus_log_path = "/var/log/web/antivirus/";
      } else if(getenv('alex.server.type') === "development") {
        # The below variables are for testing on localhost
        $this->piet_upload_path = "./uploads/";
        #$this->exec_maldet_path = "/bin/echo"; // Response to &&
        $this->exec_virusscan_path = "/bin/echo";
        $this->exec_echo_path = "/bin/echo";
        #$this->antivirus_log_path = "./uploads/antivirus/";
      }
    }

    public function printSourceCodeLink() {
      // I had to manually specify the source URL as the term project being on it's own domain messed up the link - /blob/master/assignments/Term_Project/index.php
      print('<a class="source-code-link" href="' . getenv('alex.github.project') . '/tree/' . getenv('alex.github.branch') . "/assignments/Term_Project" . $_SERVER['SCRIPT_NAME'] . '">View Source Code</a><br>');
    }

    public function printVirusScanResults() {
      print("<div class=\"results-container\"><div id=\"scanresults\" class=\"warning hidden\">Scanning for Viruses!!!<div class=\"loader\"></div></div></div><span id=\"scanresults-newline\" class=\"hidden-newline hidden-newline-mobile\"><br></span>");
      //print("<div class=\"results-container\"><div id=\"scanresults\" class=\"warning hidden\">Scanning for Viruses!!!<div class=\"loader\"></div></div></div><span id=\"scanresults-newline\" class=\"hidden-newline\"></span>");
    }

    public function printUploadForm() {
      // https://stackoverflow.com/a/23706177/6828099
      print('
      <!--This Pre Tag Exists to Help With Javascript Resizing-->
      <pre class="sizing-tag-hidden" id="sizing-tag"></pre>

      <form method="post" enctype="multipart/form-data">
        <div class="minified" data-tip="Limited to 20 Characters!!!">
          <div class="div-name-input minified">
            <label for="program_name" class="name">Program Name: </label>
            <input class="name-input" id="program_name" name="program_name" type="text" maxlength="20" required>
          </div>
        </div>

        <div class="minified" data-tip="Limited to 365 Characters!!!">
          <div class="about-textarea minified">
            <label for="program_about" class="about">About Program: </label><br>
            <textarea class="textarea" id="program_about" name="program_about" maxlength="365" required></textarea><br>
          </div>
        </div>

        <div class="file-input">
          <label for="piet-file-input" class="upload">Select image to upload: </label>
          <span class="upload-break">
            <label class="file-button"><span id="piet-filename" class="select-file-message">No File Selected</span><input type="file" accept=".png,.gif,image/png,image/gif" name="piet-image" class="upload-box" id="piet-file-input"></label>
            <input type="submit" class="submit-button" value="Upload Image" name="submit">
          </span>
        </div>
      </form>
      ');
    }

    public function verifyMySQLVars($metadata) {
        // I could refuse to add this to MySQL if the value is not set. It is not set up this way though.
        if(is_array($metadata)) { // Verifies a File Was Actually Uploaded
          $programid = explode("_", $metadata[0])[1]; // Name looks like "piet_5c92bb736591c", I want "5c92bb736591c" out of it.

          $programname = $metadata[6];
          $programabout = $metadata[7];

          $filename = $metadata[1];
          $ipaddress = $_SERVER["REMOTE_ADDR"];
          $checksum = $metadata[2];
          $allowed = $metadata[3];
          $banreason = $metadata[4]; // Just Setting Up for manual intervention later if needed and also automated fail ban message.
          $dateadded = $metadata[5]; // Helps determine when to autodelete banned images if not cleared.

          //$sqlCommands = new sqlCommands(); // I cannot set this unless I want to specify the auth multiple times.
          global $sqlCommands;
          $sqlCommands->insertData($programid, $programname, $filename, $ipaddress, $programabout, $checksum, $allowed, $banreason, $dateadded);
        }
    }

    public function getValue($value) {
      $return_me = '';

      //print("Trim: '" . trim($_REQUEST[$value]) . "'<br>");

      if(isset($_REQUEST[$value]) && trim($_REQUEST[$value]) !== '') {
        $return_me = $_REQUEST[$value];
      } else {
        //$return_me = "Not Set";
        throw new Exception("$value");
      }

      return $return_me;
    }

    public function checkImageAllowed($randomid) {
      // Check If Image Is ALLOWED!!!
      // I could restrict the color palette
      // to only what is expected in a Piet Program.
      // http://www.dangermouse.net/esoteric/piet.html
      //return [0, "Test Ban!!!"];

      return [1, Null];
    }

    public function scanForViruses($uploaded_file, $randomid) {
      #$command = $this->exec_maldet_path . ' --scan-all "' . $uploaded_file . '" &';
      #$log = $this->antivirus_log_path . $randomid . ".scan";
      // I am not using the log method anymore now I have maldet set to email me about failed scans!!!
      $command = $this->exec_virusscan_path . " " . "$randomid" . " " . getenv('alex.server.piet.database') . " &"; // piet_dev
      //print("Command: $command!!!");

      // https://stackoverflow.com/a/4626970/6828099
      $descriptorspec = array(
        array('pipe', 'r'), // stdin
        #array('file', $log, 'a'), // stdout
        #array('file', $log, 'w'), // stderr
      );

      $proc = proc_open($command, $descriptorspec, $pipes);
      //proc_close($proc); // Don't Activate This Otherwise The Script Will Hang Until Process Is Finished!!!

      //print('<div class="warning">Command "' . $command . '"!!!</div><br>');

      // This works, but it slightly slows down the response of the page.
      // I am going to see if I cannot figure out how to asynchronously scan the file and send the user the response.
      // https://stackoverflow.com/a/222445/6828099
      // This Was Originally In checkImageAllowed(...);
      //exec($command, $antivirus, $antivirus_return);
      /*if($antivirus_return) {
        //print("<div class=\"error\">Failed Antivirus!!!</div><br>");
        //return [0, "Failed Antivirus Scan"];
      }*/
    }

    public function checkUpload() {
      // https://www.w3schools.com/php/php_file_upload.asp
      if(isset($_FILES["piet-image"])) {
        try {
          $textbox_limit = 20;
          $textarea_limit = 365;

          $programname = htmlspecialchars(substr($this->getValue('program_name'), 0, $textbox_limit), ENT_QUOTES, 'UTF-8');
          $programabout = htmlspecialchars(substr($this->getValue('program_about'), 0, $textarea_limit), ENT_QUOTES, 'UTF-8');
        } catch(Exception $e) {
          if($e->getMessage() === "program_name") {
            print('<div class="error">Program Name is Not Set!!!</div><br>');
          } else if($e->getMessage() === "program_about") {
            print('<div class="error">About Program is Not Set!!!</div><br>');
          } else {
            print('<div class="error">"' . $e->getMessage() . '" is Not Set!!!</div><br>');
          }

          return -1;
          //die();
        }

        //$id = isset($_REQUEST["id"]) ? (int) $_REQUEST["id"] : NULL; // Single Line If Statement
        $randomid = uniqid('piet_');
        $target_dir = $this->piet_upload_path;
        $target_file = $target_dir . $randomid . ".png";
        $uploaded_file_name = htmlspecialchars($_FILES["piet-image"]["name"], ENT_QUOTES, 'UTF-8');
        $uploaded_file = $_FILES["piet-image"]["tmp_name"];

        if(empty($uploaded_file)) {
          print("<div class=\"error\">No File Uploaded!!!</div><br>");
          return -5;
        }

        $imageFileType = mime_content_type($uploaded_file);
        $checksum = hash_file("sha256", $uploaded_file);

        // Check If File Size Is Under 1 MB (1024 KB)
        if($_FILES["piet-image"]["size"] > 1024000) {
          print("<div class=\"error\">Image Cannot Be Over 1 MB (1024 KB)!!!</div><br>");
          return -1;
        }

        global $sqlCommands;
        // Check If Image Already Exists By Comparing Checksum
        $verifyExists = $sqlCommands->readChecksum($checksum);
        if($verifyExists[0]) {
          if($verifyExists[3]) {
            $piet_url = $verifyExists[1];
            $piet_launcher = $this->piet_launcher . "?piet-url=" . $piet_url;
            print("<div class=\"warning\">Image Already Exists!!! Check It Out With Program ID <a class=\"warning-link\" href='" . $piet_launcher . "'>$piet_url</a>!!!</div><br>");
          } else {
            print("<div class=\"error\">Image Was Previously Banned!!!</div><br>");
          }
          return -4;
        }

        // Check If PNG or GIF Format
        if($imageFileType != "image/png" && $imageFileType != "image/gif") {
          print("<div class=\"error\">Image Has to Be A PNG Or GIF File!!! It is a $imageFileType file!!!</div><br>");
          return -2;
        }

        // Loop Until File Doesn't Exist
        while(file_exists($target_file)) {
          // This could be made more efficient with the original randomid and $target_file
          $randomid = uniqid('piet_');
          $target_file = $target_dir . $randomid . ".png";
        }

        // Check against porn or other content not allowed
        $explodedRandomID = explode("_", $randomid)[1];
        $isallowed = $this->checkImageAllowed($explodedRandomID);
        $allowed = $isallowed[0];
        $banreason = $isallowed[1];

        if(!$allowed) {
          $issues=getenv('alex.github.project') . "/issues";

          if("$banreason" == "Failed Antivirus Scan") {
            print('<div class="error">Image "' . $uploaded_file_name . '" Failed The Automated Check for the reason "' . $banreason . '"!!!</div><br>');
            return -6;
          } else {
            print('<div class="error">Image "' . $uploaded_file_name . '" Failed The Automated Check for the reason "' . $banreason . '"!!!' .
            ' If you believe this is in error, contact me on <a href="' . $issues . '">Github Issues</a> with the Program ID "' . $explodedRandomID . '"!!!</div><br>');
          }
        }

        date_default_timezone_set("UTC"); // Set Time To UTC Format
        $dateadded = time(); // Get Current Server Time

        // To Debug Upload Problems on New Server
        //ini_set('display_errors',1);
        //error_reporting(E_ALL);

        if(move_uploaded_file($uploaded_file, $target_file)) {
          $piet_url = $explodedRandomID;
          $piet_launcher = $this->piet_launcher . "?piet-url=" . $piet_url;
          print('<div class="success">Uploaded: ' . $uploaded_file_name . '!!! ');
          print('The Program\'s ID is: <a class="success-link" href="' . $piet_launcher . '">' . $explodedRandomID . '</a>!!!</div>'); //<br>');

          print('<script type="text/javascript">
                  $(document).ready(function() {
                    SCANID.init(["' . $explodedRandomID . '"]);
                    SCANID.setData();
                  });
                </script>');

          $isallowed = $this->scanForViruses($target_file, $explodedRandomID);

          return [$randomid, basename($uploaded_file_name), $checksum, $allowed, $banreason, $dateadded, $programname, $programabout];
        } else {
          print("<div class=\"error\">Failed To Move File To Storage Directory!!!</div><br>");
          return -3;
        }
      }

      return 0;
    }
  }

  class loadPage {
    public function loadHeader() {
      $PageTitle="Piet Uploader!!!";
      $root = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : $_SERVER['DOCUMENT_ROOT'];
      include_once($root . "/server-data/header.php");
    }

    public function loadFooter() {
      $root = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : $_SERVER['DOCUMENT_ROOT'];
      include_once($root . "/server-data/footer.php");
    }
  }
?>