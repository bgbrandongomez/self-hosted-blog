<!DOCTYPE html>
<html>
  <head>
    <title>APC UPS Battery Stats</title>
    <link rel="stylesheet" href="/HTML-CSS/assignment1.css">
    <link rel="icon" href="/images/svg/SenorContento.svg">
    <link rel="icon" href="/images/png/SenorContento-1024x1024.png">
  </head>
  <body>
      <!--If only inheritance was a part of the css standard-->
      <pre><code style="color: #90EE90"><?php system('/sbin/apcaccess');

      print('</br>');
      system('/bin/date -u'); ?></code></pre>
  </body>
</html>
