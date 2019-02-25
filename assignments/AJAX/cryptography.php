<?php
  // https://stackoverflow.com/a/3406181/6828099
  // This is used to convert all warnings, errors, etc... into exceptions that I can handle.
  set_error_handler(
    function ($severity, $message, $file, $line) {
      // This if statement executes if the statement has an @ symbol in front of it.
      // http://php.net/manual/en/function.set-error-handler.php
      if (0 === error_reporting()) {
        //print("Help! Help! I'm Being Suppressed!!! Monty Python - https://www.youtube.com/watch?v=ZtYU87QNjPw");
        return false;
      }

      //print("$message, $severity, $file, $line");
      //if($message !== "openssl_encrypt(): Using an empty Initialization Vector (iv) is potentially insecure and not recommended")

      throw new ErrorException($message, $severity, $severity, $file, $line);
    }
  );

  $mainPage = new cryptography();

  $mainPage->readParameters();

  /* Encryption Method
   *
   * https://raymii.org/s/tutorials/Encrypt_and_decrypt_files_to_public_keys_via_the_OpenSSL_Command_Line.html - Not Asymmetric, but at least Non-Random
   * https://stackoverflow.com/a/7423796/6828099 - Default Cipher - DES-EDE3-CBC
   *
   * openssl enc -des-ede3-cfb -nosalt -in hello -out hello.enc -pass file:key.bin
   * Potential: openssl enc -des-ede3-cfb -nosalt -in hello -out hello.enc -K key.bin
   *
   * http://php.net/manual/en/function.openssl-encrypt.php
   *
   * Because this is a symmetric key and not asymmetric, use the same key to decrypt the data as used to encrypt it.
   * I was hoping to have a working implementation of GPG Asymmetric keypair generation while using reproducible, non-random, deterministic entropy.
   * I either found no implementation, the implementation was in C# (and not worth trying to cross-compile to my RPi for a class assignment),
   * or the implementation was broken. I also do not know enough about cryptography to build my own PGP solution.
   *
   * I hear that I can feed /dev/urandom or /dev/random by piping data into it. OpenSSH uses /dev/urandom.
   * I just don't want to introduce uncontrolled variables that can affect my experiment. These pools already generate
   * their own entropy that isn't from the binaries I retrieve from hotbits.
   *
   * I don't know what generator GPG uses, but using strace, I found out about "~/.gnupg/random_seed".
   * https://www.gnupg.org/documentation/manuals/gnupg/GPG-Configuration.html
   */

  /* Potential Alternate Generators
   *
   * https://serverfault.com/questions/707859/generate-entropy-for-pgp-key
   * https://crypto.stackexchange.com/a/26477/39179
   * https://davesteele.github.io/gpg/2014/09/20/anatomy-of-a-gpg-key/
   *
   * haveged
   * rng-tools
   *
   * /dev/random
   * /dev/urandom
   */

  /* Potential Entropy Fakers
   *
   * phidelius - https://dankaminsky.com/2012/01/03/phidelius/ - http://s3.amazonaws.com/dmk/phidelius-1.01.tgz - PRNG is not seeded
   * keygen - https://ritter.vg/blog-non_persistent_pgp.html - https://ritter.vg/resources/non-persistent-gpg-keys.tgz - MPI subsystem not initialized
   *
   * Both of these could allow replacing the pseudorandom generator with data from Hotbits (for some cryptographic strength testing without introducing uncontrolled variables).
   * Problem is they both are broken for the reasons tacked on to the end of each list item. For example PRNG is not seeded was outputted by ssh-keygen probably because data is
   * not being piped into /dev/urandom. SSH-Keygen grabs data from that device (as monitored by strace on my RPi). MPI subsystem... is outputted by keygen itself.
   *
   * https://www.ibm.com/developerworks/community/blogs/cgaix/entry/fatal_prng_is_not_seeded?lang=en - Potential Solution for PRNG is not seeded
   * phidelius has the most chance of working. It is possible that phidelius is not generating the devices when it executes the keygen programs.
   */

  /* Debug Trace - phidelius
   *
   * ➜  phidelius-1.01 ./phidelius -p "ax-op-nm-qw-yi" -d -e "ssh-keygen -f id_testme"
   * open64 /dev/null
   * poll
   * PRNG is not seeded
   *
   * ----------------------------------------------------------------------------------------------------------------------------------------------------------------
   * I now use https://github.com/robbyrussell/oh-my-zsh for my shell, so it is going to look different
   * from a typical bash shell (as it is not bash and is a modified zsh shell)
   */

  /* API Methods (POST)
   *
   * bytes(int) and generator(string)
   * retrieve(bool) and id(int)
   * analyze(bool) and id(int)
   * analyze(bool) and id(int) and count(bool)
   */

  class cryptography {
    public function readParameters() {
      // These all Return JSON Responses
      //print("GrabKey: " . $this->grabKey(93));
      //print("GrabNewKey (Pseudo): " . $this->grabNewKey(10, "pseudo"));
      //print("GrabNewKey (Real): " . $this->grabNewKey(10, "random_local"));
      //print("Analyze: " . $this->analyzeData(93, false));
      //print("Analyze (Count): " . $this->analyzeData(93, true));

      // GrabNewKey (Real): https://localhost/assignments/AJAX/cryptography.php?bytes=2048&generator=random_local
      // GrabNewKey (Pseudo): https://localhost/assignments/AJAX/cryptography.php?bytes=2048&generator=pseudo
      // GrabKey: https://localhost/assignments/AJAX/cryptography.php?id=1
      // Analyze:
      // Analyze (Count):

      //header("Content-Type: application/json");
      try {
        if(!empty($_GET)) {
          if(isset($_GET["id"])) {
            if(isset($_GET["analyze"]) && filter_var($_GET["analyze"], FILTER_VALIDATE_BOOLEAN)) {
              // I don't know why I added the analyze methods to the cryptography class.
              // Anyhoo, it exists now, so I am leaving it in.
              if(isset($_GET["count"]) && filter_var($_GET["count"], FILTER_VALIDATE_BOOLEAN)) {
                header("Content-Type: application/json");
                print($this->analyzeData($_GET["id"], true)); // Analyze (Count)
                die();
              } else {
                header("Content-Type: application/json");
                print($this->analyzeData($_GET["id"], false)); // Analyze
                die();
              }
            } else {
              $key = $this->grabBinary($this->grabKey($_GET["id"])); // GrabKey
            }
          } else if(isset($_GET["bytes"]) && isset($_GET["generator"])) {
              $key = $this->grabBinary($this->grabNewKey($_GET["bytes"], $_GET["generator"])); // GrabNewKey
          } else {
            header("Content-Type: application/json");

            $jsonArray = ["error" => "Sorry, but no valid request sent!"];
            $json = json_encode($jsonArray);
            print($json);
            die();
          }
        } else {
          header("Content-Type: application/json");

          $jsonArray = ["error" => "Please send a POST request!"];
          $json = json_encode($jsonArray);
          print($json);
          die();
        }
      } catch(Exception $e) {
        header("Content-Type: application/json");

        $jsonArray = ["error" => "Exception in POST: " . $e->getMessage()];
        $json = json_encode($jsonArray);
        print($json);
        die();
      }

      $encrypted = $this->encrypt($key, "des-ede3-cfb", "Encrypt Me");
      $decrypted = $this->decrypt($key, "des-ede3-cfb", $encrypted);
      print("Encrypted: \"$encrypted\" Decrypted: \"$decrypted\"");
    }

    private function grabBinary($json) {
      $decoded = json_decode($json, true);
      $data = $decoded["data"];

      return pack("C*", ...$data);
    }

    public function generateKey() {
      // Not Applicable
    }

    public function grabKey($id) {
      // setRequestNewData($bytes, $generator)
      // setRetrieveData($id)
      // setAnalyzeData($id, $count)
      return $this->requestData($this->setRetrieveData($id));
    }

    public function grabNewKey($bytes, $generator) {
      // setRequestNewData($bytes, $generator)
      // setRetrieveData($id)
      // setAnalyzeData($id, $count)
      return $this->requestData($this->setRequestNewData($bytes, $generator));
    }

    public function analyzeData($id, $count) {
      // setRequestNewData($bytes, $generator)
      // setRetrieveData($id)
      // setAnalyzeData($id, $count)
      return $this->requestData($this->setAnalyzeData($id, $count));
    }

    public function encrypt($key, $cipher, $message) {
      // openssl enc -des-ede3-cfb -nosalt -in hello -out hello.enc -pass file:key.bin

      // http://php.net/manual/en/function.openssl-get-cipher-methods.php
      $isValidCipher = false;
      foreach(openssl_get_cipher_methods(TRUE) as $checkCipher) {
        if($checkCipher === $cipher)
          $isValidCipher = true;
      }

      if(!$isValidCipher)
        throw new Exception("Please Specify a Valid Cipher! \"" . $cipher . "\" is not Valid!");

      // http://php.net/manual/en/function.openssl-encrypt.php - openssl_encrypt(...);
      // https://stackoverflow.com/a/43886617/6828099 - OPENSSL_RAW_DATA
      // https://stackoverflow.com/a/12486940/6828099 - What is an IV?
      // https://stackoverflow.com/a/21324063/6828099 - Should I use IV?
      // https://stackoverflow.com/a/1987588/6828099 - Skip Warning Messages

      /* Disable Warning Messages
       *
       * I know I can just an an IV using 'openssl_encrypt($message, $cipher, $key, "bytes-int-string-go-here");',
       * but I want to make sure there is no external influence on encryption and that de/encryption is 100% reproducible.
       *
       * I would already have to release the the salt (IV) to make sure the tests are reproducible, but I wanted to just remove them.
       */

      /* One Way to Temporarily Disable Warnings
       *
       * var_dump(error_reporting());
       * $savedReporting = error_reporting();
       * error_reporting(E_ALL ^ E_WARNING);
       * $result = openssl_encrypt($message, $cipher, $key, OPENSSL_RAW_DATA);
       * error_reporting($savedReporting); // https://stackoverflow.com/questions/10169761/save-and-restore-error-reporting-level-in-php
       */

      // It turns out you can just put the @ (at) symbol in front of the function being called. - https://stackoverflow.com/a/10169839/6828099
      return @openssl_encrypt($message, $cipher, $key, OPENSSL_RAW_DATA);
    }

    public function decrypt($key, $cipher, $message) {
      // http://php.net/manual/en/function.openssl-get-cipher-methods.php
      $isValidCipher = false;
      foreach(openssl_get_cipher_methods(TRUE) as $checkCipher) {
        if($checkCipher === $cipher)
          $isValidCipher = true;
      }

      if(!$isValidCipher)
        throw new Exception("Please Specify a Valid Cipher! \"" . $cipher . "\" is not Valid!");

      // http://php.net/manual/en/function.openssl-encrypt.php
      // https://stackoverflow.com/a/43886617/6828099
      return openssl_decrypt($message, $cipher, $key, OPENSSL_RAW_DATA);
    }

    private function setRequestNewData($bytes, $generator) {
      // bytes(int)
      $data = array('bytes' => $bytes, // Number of Bytes to Request
                    'generator' => $generator // Generator to Use
                   );

      return $data;
    }

    private function setRetrieveData($id) {
      // retrieve(bool) and id(int)
      $data = array('retrieve' => TRUE, // Retrieve is always true
                    'id' => $id, // rowID
                   );

      return $data;
    }

    private function setAnalyzeData($id, $count) {
      // analyze(bool) and id(int)
      // analyze(bool) and id(int) and count(bool)
      $data = array('analyze' => TRUE, // Analyze is always true
                    'id' => $id, // rowID
                    'count' => filter_var($count, FILTER_VALIDATE_BOOLEAN) // Whether or not to display byte counts
                   );

      return $data;
    }

    private function requestData($data) {
      try {
        // https://stackoverflow.com/a/6609181/6828099
        $url = getenv("alex.server.host") . '/assignments/AJAX/hotbits.php';

        $options = array(
          // https://stackoverflow.com/q/32211301/6828099
          'ssl' => array(
            // I cannot specify a self-signed cert to PHP, so I have to disable verification - https://serverfault.com/a/815795/379269
            'verify_peer' => filter_var(getenv("alex.server.host.verifycert"), FILTER_VALIDATE_BOOLEAN), // Set to false to disable checking certificate
            //'cafile' => '/usr/local/etc/nginx/certs/localhost'
          ),
          'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
          )
        );

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context); // http://php.net/manual/en/function.file-get-contents.php - string $filename, bool $use_include_path = FALSE, resource $context, ...
        //$result = false;

        if ($result === FALSE) {
          throw new Exception("Result Returned FALSE!!!");
        }

        return $result; // It is on the caller to anticipate the correct format. If needed, I could use an array to specify type and data ["type"->"json", "data"->"{}"];
      } catch(Exception $e) {
        throw $e; // $result === false calls here
      }
    }
  }
?>