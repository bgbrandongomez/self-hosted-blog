<?php
  // https://stackoverflow.com/a/3406181/6828099
  // This is used to convert all warnings, errors, etc... into exceptions that I can handle.
  set_error_handler(
    function ($severity, $message, $file, $line) {
      throw new ErrorException($message, $severity, $severity, $file, $line);
    }
  );

  $mainPage = new HotbitsAPI();
  $sqlCommands = new sqlCommands();
  $manager = new databaseManager();

  $mainPage->setVars();

  $sqlCommands->setLogin(getenv('alex.server.phpmyadmin.host'),
                          getenv('alex.server.phpmyadmin.username'),
                          getenv('alex.server.phpmyadmin.password'),
                          getenv('alex.server.phpmyadmin.database'));

  $sqlCommands->testConnection();
  $sqlCommands->connectMySQL();
  $sqlCommands->createTable();
  $sqlCommands->createSettingsTable(); // For Tracking Rate Limit

  $mainPage->printAPI();

  class HotbitsAPI {
    public $exec_ent_path;
    public $exec_cat_path;
    public $api_real_hotbits_path;
    public $api_hotbits_path;
    public $url_ent_path;
    //public $exec_mkdir_path;
    //public $hotbits_tmp_path;

    function setVars() {
      $this->api_hotbits_path = "/api/hotbits";
      $this->url_ent_path = "https://www.fourmilab.ch/random/random.zip";
      $this->api_real_hotbits_path = "https://www.fourmilab.ch/cgi-bin/Hotbits.api";

      if(getenv('alex.server.type') === "production") {
        # The below variables are for the production server
        $this->exec_ent_path = "/home/web/programs/ent";
        $this->exec_cat_path = "/bin/cat";
        //$this->exec_mkdir_path = "/bin/mkdir";
        //$this->hotbits_tmp_path = "/tmp/hotbits/";
      } else if(getenv('alex.server.type') === "development") {
        # The below variables are for testing on localhost
        $this->exec_ent_path = "/Users/senor/Documents/.Programs/ent";
        $this->exec_cat_path = "/bin/cat";
        //$this->exec_mkdir_path = "/bin/mkdir";
        //$this->hotbits_tmp_path = "/tmp/hotbits/";
      }
    }

    public function printAPI() {
      global $manager;

      try {
        if(!empty($_REQUEST)) {
          $id = isset($_REQUEST["id"]) ? (int) $_REQUEST["id"] : NULL;
          $bytes = isset($_REQUEST["bytes"]) ? (int) $_REQUEST["bytes"] : NULL;
          $analyze = isset($_REQUEST["analyze"]) ? filter_var($_REQUEST["analyze"], FILTER_VALIDATE_BOOLEAN) : false;
          $retrieve = isset($_REQUEST["retrieve"]) ? filter_var($_REQUEST["retrieve"], FILTER_VALIDATE_BOOLEAN) : false;
          $format = isset($_REQUEST["format"]) ? $_REQUEST["format"] : "json";

          if(isset($bytes)) {
            header("Content-Type: application/json");

            $generator = isset($_REQUEST["generator"]) ? $_REQUEST["generator"] : "pseudo";
            print($manager->formatForSQL($this->grabData($bytes, $generator))); // To specify a custom generator
            die();
          }

          if($retrieve && $id) {
            header("Content-Type: application/json");
            print($manager->readSQLToJSON($id));
            die();
          }

          // Now that I know about shorthand, I can greatly improve the readability of the code - https://stackoverflow.com/a/5972529/6828099
          if($analyze && $id) {
            $count = isset($_REQUEST["count"]) ? filter_var($_REQUEST["count"], FILTER_VALIDATE_BOOLEAN) : false;
            //$terse = isset($_REQUEST["terse"]) ? filter_var($_REQUEST["terse"], FILTER_VALIDATE_BOOLEAN) : false;

            if($format === "json") {
              header("Content-Type: application/json");
              print($this->getRandomness($id, $manager->readSQLToJSON($id), false, $count));
            } else if($format === "html") {
              $json = $this->getRandomness($id, $manager->readSQLToJSON($id), false, $count);
              $decoded = json_decode($json, true);
              $response = $decoded["response"];

              $this->convertToHTML($response, $id, $count); // Analyze (Normal and Count)
            } else if($format === "csv") {
                $json = $this->getRandomness($id, $manager->readSQLToJSON($id), true, $count);
                $decoded = json_decode($json, true);
                $response = $decoded["response"];

                header("Content-Type: text/csv");
                header("Content-length: " . strlen($response));

                if(!$count) {
                  header("Content-Disposition: attachment; filename=\"Hotbits-Analysis-$id.csv\"");
                } else {
                  header("Content-Disposition: attachment; filename=\"Hotbits-Analysis-$id-Count.csv\"");
                }
                print($response);
                die();
            }

            die();
          }

          header("Content-Type: application/json");

          $jsonArray = ["error" => "Sorry, but no valid request sent!"];
          $json = json_encode($jsonArray);
          print($json);
        } else {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Please send a POST or GET request!"];
          $json = json_encode($jsonArray);
          print($json);
        }
      } catch(Exception $e) {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "Request Error! Exception: " . $e->getMessage()];
        $json = json_encode($jsonArray);
        print($json);
        die();
      }
    }

    // I really just need a PHP file I can include all my standard functions from
    // Perhaps I can create a "Standard Functions" class and just include the file to use it.
    private function boolToString($bool) {
      return $bool ? 'true' : 'false';
    }

    private function convertToHTML($string, $id, $count) {
      header("Content-Type: text/html");

      print('<img width="50px" src="/images/png/SenorContento-1024x1024.png" align="left"></img>');
      print(' ' . "<b style='color: red;'>I am HTML Output!!! Bow Down To My Master HTML Skills!!! Lol...</b>");
      print('<br><br>'); // https://www.uvm.edu/~bnelson/computer/html/wrappingtextaroundimages.html
      print(' ' . "<a style=\"text-decoration: underline red;\" href=\"" . $this->url_ent_path . "\"><b style='color: red;'>Entropy Program's Source Code</b></a>");

      // https://codepen.io/vidhill/pen/bNPEmX
      // https://developers.google.com/web/updates/2012/06/Don-t-Build-Blobs-Construct-Them
      print(' ' . "<a id=\"download-as-csv\" style=\"text-decoration: underline red; cursor: pointer;\"><b style='color: red;'>Download As CSV</b></a>");
      print("<script>
            var request = {\"analyze\": true, \"id\": $id, \"count\": " . $this->boolToString($count) . ", \"format\": \"csv\"};
            var url = \"" . $this->api_hotbits_path . "\";

            document.getElementById('download-as-csv').onclick = function(event){
              var csv;
              $.ajax({
                url: url,
                type: 'POST',
                async: false,
                data: request,
                success: function(data, status, xhr) {
                  csv = data;
                }
              });

              /* A blob cannot be made inside an Ajax Request */
              var blob = new Blob([csv], {type: 'text/csv'});
              url = window.URL.createObjectURL(blob);

              this.href = url;
              this.target = '_blank';
              ");

              if(!$count) {
                print("this.download = 'Hotbits-Analysis-$id.csv';");
              } else {
                print("this.download = 'Hotbits-Analysis-$id-Count.csv';");
              }

              print("
            }
            </script>");

      print("<br clear=\"left\">");

      print("<h3>");
      print($string);
      print("</h3>");
    }

    public function grabData($bytes, $generator) {
      //getenv('alex.server.api.hotbits');
      try {
        if(!is_int($bytes) || $bytes > 2048 || $bytes < 1)
          throw new Exception("InvalidByteCount"); // Too many, too few, or not even a number (integer)!!!

        if($generator === "pseudo" || $generator === "pseudorandom")
          return $this->requestData($this->setParameters("pseudo", "json", $bytes));

        $this->checkRateLimit($bytes);

        return $this->requestData($this->setParameters(getenv('alex.server.api.hotbits'), "json", $bytes));
      } catch(Exception $e) {
        throw $e; // Pass the exception upwards!
      }
    }

    public function grabDataOffline($bytes, $generator) {
      // This function exists purely for testing with the data on localhost while offline
      if(!is_int($bytes) || $bytes > 2048 || $bytes < 1)
        throw new Exception("InvalidByteCount"); // Too many, too few, or not even a number (integer)!!!

      //$this->checkRateLimit($bytes);
      //header("Content-Type: application/json");

      /*
       * It appears I get 12,288 total bytes to download and 120 total requests.
       * I do not know when the counter resets, but I am hoping it is daily.
       * I calculated this out by adding the 2,048 bytes I requested plus the 10,240
       * bytes from quotaBytesRemaining at debug-real.json. This was my first non-pseudo
       * request in a while (around a month). Also, there are 119 requests left and I only
       * used one request in over a month.
       *
       * The page: https://www.fourmilab.ch/fourmilog/archives/2017-06/001684.html
       * says that there are 12,208 bytes total per 24 hours period. There's still the
       * 120 total request limit (which is also part of the 24 hour period).
       *
       * Looking at the randomX Hotbit's source code, it appears that the ratelimit is detected
       * by a downloadable buffer length that is bigger than an internal buffer. Not even an error code to
       * track when the ratelimit is reached. Good thing is, the remaining ratelimit is specified with every
       * JSON (and XML) response. Those responses provide both the remaining request and byte limit. I want
       * to internally track the rate limits so I can choose when to cut people off as opposed to when I am cut off.
       *
       * Also, Hotbits gives you an HTML exceeded message if you request more bytes than your key allows.
       * The HTML message won't tell you how many bytes are left.
       */

      //$file = file_get_contents("responses/debug-random.json");
      //$file = file_get_contents("responses/debug-pseudo.json");

      //$file = file_get_contents("responses/debug-hotbits-exceeded-max-bytes-request.json");

      //$file = file_get_contents("responses/debug-right-before-exceeding-rate-limit.json");
      $file = file_get_contents("responses/debug-exceeded-rate-limit.html");

      return $file; //file_get_contents("debug.json");
    }

    private function checkRateLimit($requestedBytes) {
      global $sqlCommands;

      list($generationTime, $quotaRequestsRemaining, $quotaBytesRemaining) = $sqlCommands->readConfigData(1);

      // The rate limit ends after 24-hours-ish. So, I am only allowing it to check again after 24 hours (once the limit is reached).
      // You can check it here: https://www.fourmilab.ch/fourmilog/archives/2017-06/001684.html

      // https://daveismyname.blog/quick-way-to-add-hours-and-minutes-with-php
      // http://php.net/manual/en/function.date.php
      //$timezone = date_default_timezone_set('GMT');
      $collectGO = date('Y-m-d H:i:s T',strtotime('+24 hours',strtotime($generationTime))); // Format 2019-02-23T06:09:06Z
      $now = date('Y-m-d H:i:s T', time());

      // https://stackoverflow.com/a/32642436/6828099
      /*if($now > $collectGO) {
        print("Reset Counter: "); //filter_var((), FILTER_VALIDATE_BOOLEAN)
      }*/

      //print("Quota Requests Remaining: " . $quotaRequestsRemaining);
      if(((int) $requestedBytes > (int) $quotaBytesRemaining) || ((int) $quotaRequestsRemaining === 0) && ($now < $collectGO)) // Maybe turn these zeros into variables to kill the rate limit before it actually ends
        throw new Exception("Exceeded Rate Limit! Wait until $collectGO! Current Time is $now! (Requests: $quotaRequestsRemaining) (Bytes: $quotaBytesRemaining) (Requested Bytes: $requestedBytes)");
    }

    public function getRandomness($id, $result, $terse, $count) {
      try {
        //header("Content-Type: text/plain");
        $randomness = $this->checkRandomness($this->convertToArray($result), $terse, $count);

        // https://stackoverflow.com/a/28131159/6828099 - json_encode() just returns false "bool(false)" if it fails to convert the array to json
        $result = preg_replace_callback('/[\x80-\xff]/',
                  function($match) {
                      return '\x'.dechex(ord($match[0]));
                  }, $randomness);

        $jsonArray = ["rowID" => (int) $id,
                      "download" => "Specify POST or GET request argument, format=\"csv\", to download analysis as CSV file!!!",
                      "response" => $result];

        //var_dump(json_encode($result));
        return json_encode($jsonArray);
      } catch(Exception $e) {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "Exception: " . $e->getMessage()];
        $json = json_encode($jsonArray);
        print($json);
        die();
      }
    }

    private function setParameters($apikey, $fmt, $nbytes) {
      /*
       * These below values are the default values assumed by Hotbit's Server.
       * If they are not explicitly specified, this is what they default to.
       */

      /*
          $data = array('nbytes' => '128', // Maximum # of Bytes: 2048 - Not Active When fmt = password
                        'fmt' => 'hex', // Available Formats: hex, bin, c, xml, json, password

                        // This Section is Only Active when fmt = password
                        'npass' => '1', // Number of Passwords
                        'lpass' => '8', // Length of Each Password
                        'pwtype' => '3', // 0=>lowercase, 1=>mixed-case, 2=>letters-and-numbers, 3=>2-and-punctuation

                        // Only one of these options can be active
                        'apikey' => '', // API Key: Needed to Get Real Data from Geiger Counter
                        'pseudo' => 'pseudo' // Flag for Fake Data: Best for Testing - No Rate Limit/Banning
                  );
      */

      if($apikey === "pseudo") {
        $data = array('nbytes' => $nbytes, // Maximum # of Bytes: 2048 - Not Active When fmt = password
                      'fmt' => $fmt, // Available Formats: hex, bin, c, xml, json, password
                      'pseudo' => 'pseudo' // Flag for Fake Data: Best for Testing - No Rate Limit/Banning
                    );
      } else {
        $data = array('nbytes' => $nbytes, // Maximum # of Bytes: 2048 - Not Active When fmt = password
                      'fmt' => $fmt, // Available Formats: hex, bin, c, xml, json, password

                      // Only one of these options can be active
                      'apikey' => $apikey, // API Key: Needed to Get Real Data from Geiger Counter
                    );
      }

      return $data;
    }

    private function setParametersPassword($apikey, $lpass, $npass, $pwtype) {
      if($apikey === "pseudo") {
        $data = array('fmt' => 'password', // Available Formats: hex, bin, c, xml, json, password

                      'npass' => $npass, // Number of Passwords
                      'lpass' => $lpass, // Length of Each Password
                      'pwtype' => $pwtype, // 0=>lowercase, 1=>mixed-case, 2=>letters-and-numbers, 3=>2-and-punctuation

                      'pseudo' => 'pseudo' // Flag for Fake Data: Best for Testing - No Rate Limit/Banning
                    );
      } else {
        $data = array('fmt' => 'password', // Available Formats: hex, bin, c, xml, json, password

                      'npass' => $npass, // Number of Passwords
                      'lpass' => $lpass, // Length of Each Password
                      'pwtype' => $pwtype, // 0=>lowercase, 1=>mixed-case, 2=>letters-and-numbers, 3=>2-and-punctuation

                      'apikey' => $apikey, // API Key: Needed to Get Real Data from Geiger Counter
                    );
      }

      return $data;
    }

    private function requestData($data) {
      try {
        // https://stackoverflow.com/a/6609181/6828099
        $options = array(
          'http' => array(
            'user_agent' => getenv('alex.server.user_agent'),
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
          )
        );

        $context = stream_context_create($options);
        $result = file_get_contents($this->api_real_hotbits_path, false, $context); // http://php.net/manual/en/function.file-get-contents.php - string $filename, bool $use_include_path = FALSE, resource $context, ...
        //$result = false;
        //var_dump($result);

        if ($result === FALSE) {
          throw new Exception("Result Returned FALSE!!!");
        }

        global $sqlCommands;
        if($this->isHtml($result)) {
          list($generationTime, $quotaRequestsRemaining, $quotaBytesRemaining) = $sqlCommands->readConfigData(1);

          /*
           * IDEA: What happens if I legitimately run down the quotaRequestsRemaining and not quotaBytesRemaining?
           * Can I replace faking 0 quotaRequestsRemaining with a rateLimitUp boolean?
           *
           * TODO: Run down quotaRequestsRemaining to 0 and save the JSON response to the responses folder.
           */
          $now = date('Y-m-d H:i:s T', time());
          $sqlCommands->updateRateLimit($now, 0, $quotaBytesRemaining); // The byte count didn't go down this request

          if(isset($data['nbytes'])) {
            $this->checkRateLimit((int) $data['nbytes']); // This should use generic quota reached message
          } else {
            throw new Exception("Password Rate Limiting Not Supported Yet!");
          }
          //throw new Exception("HTML Detected!");
        }

        /*
         * Will be in HTML format unless fmt is bin, xml, or json.
         *
         * Bin is Raw
         * XML is Raw
         * JSON is Raw
         *
         * Will be in HTML format if hex, c, or password.
         *
         * Hex is in an HTML pre tag
         * C is in an HTML pre tag
         * Password is in an HTML textarea
         */

        return $result; // It is on the caller to anticipate the correct format. If needed, I could use an array to specify type and data ["type"->"json", "data"->"{}"];
      } catch(Exception $e) {
        throw $e; // $result === false calls here
      }
    }

    public function convertToArray($result) {
      // http://php.net/manual/en/function.json-decode.php
      $array = null;
      if($this->isJson($result)) {
        $array = json_decode($result, true)["data"];
      } else {
        throw new Exception("Format Not Supported!!!");// Supported Formats are JSON!!!");
      }

      return $array;
    }

    // https://stackoverflow.com/a/6041773/6828099
    private function isJson($string) {
      json_decode($string);
      return (json_last_error() == JSON_ERROR_NONE);
    }

    function checkRandomness($array, $terse, $count) {
      // pipe-data-to | /home/web/programs/ent
      // http://www.fourmilab.ch/random/random.zip
      $binary = pack("C*", ...$array);
      // var_dump(unpack("C*", $binary));
      // print(bin2hex($binary));
      // https://stackoverflow.com/a/49409847/6828099

      // Done: Replace TMPfile with a stream
      // Or https://secure.php.net/tmpfile

      //exec($this->exec_mkdir_path . ' -p ' . $this->hotbits_tmp_path);
      //file_put_contents($File = $this->hotbits_tmp_path . uniqid(), $binary);

      $fp = tmpfile();
      $stream = stream_get_meta_data($fp);
      $filename = $stream['uri'];

      file_put_contents($File = $filename, $binary);

      if($count) {
        //print($this->exec_cat_path . ' ' . escapeshellarg($File) . ' | ' . $this->exec_ent_path . ' -c');
        if($terse) {
          $results = shell_exec($this->exec_cat_path . ' ' . escapeshellarg($File) . ' | ' . $this->exec_ent_path . ' -tc');
        } else {
          $results = shell_exec($this->exec_cat_path . ' ' . escapeshellarg($File) . ' | ' . $this->exec_ent_path . ' -c');
        }
      } else {
        if($terse) {
          $results = shell_exec($this->exec_cat_path . ' ' . escapeshellarg($File) . ' | ' . $this->exec_ent_path . ' -t');
        } else {
          $results = shell_exec($this->exec_cat_path . ' ' . escapeshellarg($File) . ' | ' . $this->exec_ent_path);
        }
      }

      //fwrite($fp, "string");
      //fseek($fp, 0);
      //echo fread($fp, 1024);
      fclose($fp);

      //print("Results: " . $results);
      return $results;
    }

    // https://stackoverflow.com/a/18339022/6828099
    private function isHtml($string) {
        if ( $string != strip_tags($string) )
        {
            return true; // Contains HTML
        }
        return false; // Does not contain HTML
    }
  }

  class sqlCommands {
    private $server, $username, $password, $database;

    public function setLogin($server, $username, $password, $database) {
      $this->server = $server;
      $this->username = $username;
      $this->password = $password;
      $this->database = $database;
    }

    public function testConnection() {
      if($this->server === NULL || $this->username === NULL || $this->password === NULL || $this->database === NULL) {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "Sorry, but you are missing a value to connect to the MySQL server! Not Attempting Connection!!!"];
        $json = json_encode($jsonArray);
        print($json);
        die();
      }

      $return_response = $this->connectMySQL();
      if(gettype($return_response) === "string") {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "Connection to MySQL Failed: " . $return_response . "!"];
        $json = json_encode($jsonArray);
        print($json);
        die();
      } else {
        /* //object
         *
         * $jsonArray = ["error" => "Connected to MySQL Successfully!!!"];
         * $json = json_encode($jsonArray);
         * print($json);
        */
      }
    }

    public function connectMySQL() {
      try {
        $conn = new PDO("mysql:host=$this->server;dbname=$this->database", $this->username, $this->password);

        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;
      }
      catch(PDOException $e) {
        return $e->getMessage();
      }
    }

    public function createTable() {
      try {
        $conn = $this->connectMySQL();

        // https://stackoverflow.com/a/8829122/6828099
        $checkTableSQL = "SELECT count(*)
          FROM information_schema.TABLES
          WHERE (TABLE_SCHEMA = '$this->database') AND (TABLE_NAME = 'Hotbits')
        ";

        /*
         * "version": string,
         * "schema": string,
         * "status": int,
         * "requestInformation": {
         *    "serverVersion": string,
         *    "generationTime": string,
         *    "bytesRequested": int,
         *    "bytesReturned": int,
         *    "quotaRequestsRemaining": int,
         *    "quotaBytesRemaining": int,
         *    "generatorType": string
         * },
         * "data": [ int ];
         */

        // https://stackoverflow.com/a/5562383/6828099 - INT(6) - Display Width
        // https://dev.mysql.com/doc/refman/5.7/en/json.html - MySQL JSON Format (MySQL 5.7.8+)
        // Aparently schema is a special keyword that cannot be used in normal tables
        $sql = "CREATE TABLE Hotbits (
          id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          version TEXT NOT NULL,
          jsonSchema TEXT NOT NULL,
          status INT NOT NULL,
          serverVersion TEXT NOT NULL,
          generationTime TEXT NOT NULL,
          bytesRequested INT NOT NULL,
          bytesReturned INT NOT NULL,
          quotaRequestsRemaining INT NOT NULL,
          quotaBytesRemaining INT NOT NULL,
          generatorType TEXT NOT NULL,
          data TEXT NOT NULL
        )";
        // data JSON NOT NULL - Turns out my RPI Server does not support this format for MySQL.

        $tableExists = false;
        // http://php.net/manual/en/pdo.query.php
        foreach ($conn->query($checkTableSQL) as $row) {
          if($row['count(*)'] > 0)
            $tableExists = true;
        }

        if(!$tableExists) {
          // use exec() because no results are returned
          $conn->exec($sql);
        }
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          //echo $sql;
          $jsonArray = ["error" => "Create Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json);
          die();
      }
    }

    public function createSettingsTable() {
      try {
        $conn = $this->connectMySQL();

        // https://stackoverflow.com/a/8829122/6828099
        $checkTableSQL = "SELECT count(*)
          FROM information_schema.TABLES
          WHERE (TABLE_SCHEMA = '$this->database') AND (TABLE_NAME = 'Hotbits_Config')
        ";

        $sql = "CREATE TABLE Hotbits_Config (
          id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          generationTime TEXT NOT NULL,
          quotaRequestsRemaining INT NOT NULL,
          quotaBytesRemaining INT NOT NULL
        )";

        $conn = $this->connectMySQL();
        $statement = $conn->prepare("INSERT INTO Hotbits_Config (generationTime, quotaRequestsRemaining, quotaBytesRemaining)
                                     VALUES (:generationTime, :quotaRequestsRemaining, :quotaBytesRemaining)");

        $tableExists = false;
        // http://php.net/manual/en/pdo.query.php
        foreach ($conn->query($checkTableSQL) as $row) {
          if($row['count(*)'] > 0)
            $tableExists = true;
        }

        if(!$tableExists) {
          // use exec() because no results are returned
          $conn->exec($sql);

          $statement->execute([
            'generationTime' => '1970-01-00T01:00:00Z', // TEXT
            'quotaRequestsRemaining' => -1, // INT
            'quotaBytesRemaining' => -1, // INT
          ]);
        }
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Create Settings Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json);
          die();
      }
    }

    public function updateRateLimit($generationTime, $quotaRequestsRemaining, $quotaBytesRemaining) {
      try {
        // https://stackoverflow.com/a/8334972/6828099
        $conn = $this->connectMySQL();
        $statement = $conn->prepare("UPDATE Hotbits_Config
                                     SET generationTime=:generationTime, quotaRequestsRemaining=:quotaRequestsRemaining, quotaBytesRemaining=:quotaBytesRemaining
                                     WHERE id=1");

        $statement->execute([
          'generationTime' => $generationTime, // TEXT
          'quotaRequestsRemaining' => $quotaRequestsRemaining, // INT
          'quotaBytesRemaining' => $quotaBytesRemaining // INT
        ]);

        // https://stackoverflow.com/a/9753751/6828099
        return $conn->lastInsertId(); // Should remain at row 1
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Insert Data into Config Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json);
          die();
      }
    }

    public function insertData($version, $schema, $status, $serverVersion, $generationTime, $bytesRequested, $bytesReturned, $quotaRequestsRemaining, $quotaBytesRemaining, $generatorType, $data) {
      try {
        $conn = $this->connectMySQL();
        $statement = $conn->prepare("INSERT INTO Hotbits (version, jsonSchema, status, serverVersion, generationTime, bytesRequested, bytesReturned, quotaRequestsRemaining, quotaBytesRemaining, generatorType, data)
                                     VALUES (:version, :jsonSchema, :status, :serverVersion, :generationTime, :bytesRequested, :bytesReturned, :quotaRequestsRemaining, :quotaBytesRemaining, :generatorType, :data)");

        $statement->execute([
          'version' => $version, // TEXT
          'jsonSchema' => $schema, // TEXT
          'status' => $status, // INT

          'serverVersion' => $serverVersion, // TEXT
          'generationTime' => $generationTime, // TEXT

          'bytesRequested' => $bytesRequested, // INT
          'bytesReturned' => $bytesReturned, // INT

          'quotaRequestsRemaining' => $quotaRequestsRemaining, // INT
          'quotaBytesRemaining' => $quotaBytesRemaining, // INT

          'generatorType' => $generatorType, // TEXT

          'data' => $data,  // JSON
        ]);

        // https://stackoverflow.com/a/9753751/6828099
        return $conn->lastInsertId();
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Insert Data into Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json);
          die();
      }
    }

    public function readConfigData($id) {
      try {
        $conn = $this->connectMySQL();

        $statement = $conn->prepare("SELECT * FROM Hotbits_Config WHERE id=(:rowID)");
        $statement->execute(['rowID' => $id]);

        $rows = $statement->fetchAll();

        if(sizeof($rows) === 0)
          throw new Exception("Invalid rowID!!!");

        // http://php.net/manual/en/pdostatement.fetchall.php
        foreach ($rows as $row) {
          // This is intentionally supposed to run only one iteration.
          // https://stackoverflow.com/a/3579950/6828099
          return [$row['generationTime'], $row['quotaRequestsRemaining'], $row['quotaBytesRemaining']];
        }
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Read Data from Config Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json); // IDEA: Do I pass the exception upwards or do I handle it here?
          die();
      }
    }

    public function readData($id) {
      try {
        $conn = $this->connectMySQL();

        $statement = $conn->prepare("SELECT * FROM Hotbits WHERE id=(:rowID)");
        $statement->execute(['rowID' => $id]);

        $rows = $statement->fetchAll();

        if(sizeof($rows) === 0)
          throw new Exception("Invalid rowID!!!");

        // http://php.net/manual/en/pdostatement.fetchall.php
        foreach ($rows as $row) {
          // This is intentionally supposed to run only one iteration.
          // https://stackoverflow.com/a/3579950/6828099
          return [$row['id'], $row['version'], $row['jsonSchema'], $row['status'], $row['serverVersion'], $row['generationTime'], $row['bytesRequested'], $row['bytesReturned'], $row['quotaRequestsRemaining'], $row['quotaBytesRemaining'], $row['generatorType'], $row['data']];
        }
      } catch(PDOException $e) {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Read Data from Table Failed: " . $e->getMessage()];
          $json = json_encode($jsonArray);
          print($json);
          die();
      }
    }
  }

  class databaseManager {
    public function readSQLToJSON($id) {
      global $sqlCommands;
      list($id, $version, $schema, $status, $serverVersion, $generationTime, $bytesRequested, $bytesReturned, $quotaRequestsRemaining, $quotaBytesRemaining, $generatorType, $data) = $sqlCommands->readData($id);
      //print("Data: " . $id);

      // https://stackoverflow.com/a/8529687/6828099
      $jsonArray = ["rowID" => (int) $id,
                    "version" => $version,
                    "schema" => $schema,
                    "status" => (int) $status,

                    "requestInformation" => ["serverVersion" => $serverVersion,
                                             "generationTime" => $generationTime,

                                             "bytesRequested" => (int) $bytesRequested,
                                             "bytesReturned" => (int) $bytesReturned,

                                             "quotaRequestsRemaining" => (int) $quotaRequestsRemaining,
                                             "quotaBytesRemaining" => (int) $quotaBytesRemaining,

                                             "generatorType" => $generatorType
                                            ],

                    "data" => json_decode($data, true)
                   ];

      // https://stackoverflow.com/a/30315200/6828099
      $json = json_encode($jsonArray, JSON_UNESCAPED_SLASHES);
      //$json = json_encode($jsonArray, JSON_PRETTY_PRINT);

      //header("Content-Type: application/json");
      return $json;
    }

    public function formatForSQL($json) {
      try {
        $decoded = json_decode($json, true);

        $version = $decoded["version"]; // TEXT
        $schema = $decoded["schema"]; // TEXT
        $status = $decoded["status"]; // INT

        $serverVersion = $decoded["requestInformation"]["serverVersion"]; // TEXT
        $generationTime = $decoded["requestInformation"]["generationTime"]; // TEXT

        $bytesRequested = $decoded["requestInformation"]["bytesRequested"]; // INT
        $bytesReturned = $decoded["requestInformation"]["bytesReturned"]; // INT

        $quotaRequestsRemaining = $decoded["requestInformation"]["quotaRequestsRemaining"]; // INT
        $quotaBytesRemaining = $decoded["requestInformation"]["quotaBytesRemaining"]; // INT

        $generatorType = $decoded["requestInformation"]["generatorType"]; // TEXT

        // http://php.net/manual/en/function.json-encode.php
        //$data = "{}"; //$decoded["data"]; // JSON
        //$data = $decoded["data"]; // JSON
        $data = json_encode($decoded["data"]);

        //print("Data: $version, $schema, $status, $serverVersion, $generationTime, $bytesRequested, $bytesReturned, $quotaRequestsRemaining, $quotaBytesRemaining, $generatorType, $data");

        //$sqlCommands = new sqlCommands(); // I cannot set this unless I want to specify the auth multiple times.
        global $sqlCommands;
        $id = $sqlCommands->insertData($version, $schema, $status, $serverVersion, $generationTime, $bytesRequested, $bytesReturned, $quotaRequestsRemaining, $quotaBytesRemaining, $generatorType, $data);

        if($generatorType !== "pseudorandom") {
          $sqlCommands->updateRateLimit($generationTime, $quotaRequestsRemaining, $quotaBytesRemaining);
        }

        // http://php.net/manual/en/function.array-push.php
        // https://stackoverflow.com/a/13638998/6828099 - Pretty Print JSON
        //print("Last ID: " . $id);
        //array_push($decoded, ["rowID" => $id]);
        //$decoded[] = ["rowID" => $id];
        //$decoded["rowID"] = (int) $id;

        // http://php.net/manual/en/function.array-unshift.php
        //array_unshift($decoded, (int) $id);
        $decoded = array("rowID"=>(int) $id) + $decoded;
        $json = json_encode($decoded, JSON_UNESCAPED_SLASHES);
        //$json = json_encode($decoded, JSON_PRETTY_PRINT);

        return $json;
      } catch(Exception $e) {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "databaseManager->formatForSQL: " . $e->getMessage()];
        $json = json_encode($jsonArray);
        print($json);
        die();
      }
    }
  }
?>