<?php
  function customPageHeader() {
    // The function has to be left outside the classes in order to get loaded by the header.php file.
    print('<!--This is here to bookmark how to load a custom page header!!!-->');
  }

  $loadPage = new loadPage();
  $getServerVars = new serverVars();

  $loadPage->loadHeader();
  $getServerVars->printServerName();
  $getServerVars->printServerType();
  $loadPage->loadFooter();

  class loadPage {
    public function loadHeader() {
      $PageTitle="Get Custom Server Variables";
      $root = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : $_SERVER['DOCUMENT_ROOT'];
      include_once($root . "/server-data/header.php");
    }

    public function loadFooter() {
      $root = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : $_SERVER['DOCUMENT_ROOT'];
      include_once($root . "/server-data/footer.php");
    }
  }

  class serverVars {
    public function printServerName() {
      print("<h1>The server's name is \"" . getenv('alex.server.name') . "\".</h1>");
    }

    public function printServerType() {
      print("<h1>The server's type is \"" . getenv('alex.server.type') . "\".</h1>");
    }

    public function printServerHost() {
      print("<h1>The recorded server's host is \"" . getenv('alex.server.host') . "\".</h1>");
    }
  }
?>