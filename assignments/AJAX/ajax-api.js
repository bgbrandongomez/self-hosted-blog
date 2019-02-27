// https://raw.githubusercontent.com/bgbrandongomez/blog/master/sitedata/javascripts/github/ajax-api.js

function table(json) {
  $(document).ready(function() {
    $('.index-table').remove();
    $('.item-table').remove();
    jQuery.each(json, function(index, item) {
        if(item instanceof Object) {
          recurseTable(index, item);
        } else {
          $('#ajax-table-body').append("<tr><td class=\"index-table\" style=\"text-align: left\">" + index + "</td>" +
          "<td class=\"item-table\" style=\"text-align: left\">" + item + "</td></tr>");
        }
    });
  });
}

//Document Necessary Functions

$(document).ready(function() {
  $('#submit').click(function() {
    //var url = "/api/cryptography";
    //var json = {"download": true, "retrieve": true, "id": 99};

    /*
    var oReq = new XMLHttpRequest();
    oReq.open("GET", "/api/cryptography?id=99&download=true&retrieve=true", true);
    oReq.responseType = "arraybuffer";

    oReq.onload = function(oEvent) {
      var arrayBuffer = oReq.response;

      // if you want to access the bytes:
      //var byteArray = new Uint8Array(arrayBuffer);

      //var blob = new Blob([byteArray], {type: 'application/zip'});
      //var url = window.URL.createObjectURL(blob);

      // If you want to use the image in your DOM:
      //var blob = new Blob(arrayBuffer, {type: "image/png"});
      //var url = URL.createObjectURL(blob);
      //someImageElement.src = url;

      var blob = new Blob([arrayBuffer], {type: "application/zip"});
      var url = window.URL.createObjectURL(blob);

      this.href = url;
      this.target = '_blank';
      this.download = 'cryptography-xhr.zip';

      alert(this.href);
    };

    oReq.send();
    */

    var rawData = lookup();
    if(rawData[1] === "json") {
      $("#response-table").show();
      raw(syntaxHighlight(rawData[0]));
      table($.parseJSON(rawData[0]));
    } else if(rawData[1] === "html") {
      $("#response-table").hide();
      raw(rawData[0]);
    } else if(rawData[1] === "csv") {
      //alert("CSV");
      $("#response-table").hide();
      raw(rawData[0]);
    } else if(rawData[1] === "zip") {
      //raw(rawData[0]);

      //alert("Bytes: " + rawData[0].length);
      //alert(typeof(rawData[0])); // object

      // AJAX cannot download binary data (without corrupting it), so I have to encode it to base64 on the server first
      var decoded = atob(rawData[0]); // https://stackoverflow.com/a/2820329/6828099

      array = new Uint8Array(decoded.length);
      for (var i = 0; i < decoded.length; i++){
        array[i] = decoded.charCodeAt(i);
      }

      var blob = new Blob([array], {type: 'application/zip'});
      var url = window.URL.createObjectURL(blob);

      //alert("AJAX URL: " + url);

      this.href = url;
      this.target = '_blank';
      this.download = 'cryptography.zip';
      //window.URL.revokeObjectURL(url);

      // This isn't actually AJAX downloading the file,
      // but I cannot get AJAX to download it without corrupting the file.
      //this.href = "https://localhost/api/cryptography?id=99&download=true&retrieve=true";
      //this.target = '_blank';
      //this.download = 'cryptography.zip';
      //alert(this.href);
    } else {
      $("#response-table").hide();
      raw(rawData[0]);
    }

    rawData = undefined;
    delete(rawData);
  });
});

function raw(json) {
  $(document).ready(function() {
    $("#ajax-output-debug").html(json); //.text(json);
  });
}

function recurseTable(key, value) {
  $(document).ready(function() {
    if(value instanceof Object) {
      $.each(value, function(k, v) {
        if(v instanceof Object) {
          recurseTable(k, v);
        } else {
          $('#ajax-table-body').append("<tr><td class=\"index-table\" style=\"text-align: left\">" + key + " --> " + k + "</td>" +
          "<td class=\"item-table\" style=\"text-align: left\">" + v + "</td></tr>");
        }
      });
    }
  });
}

function lookup() {
  //var url = "https://localhost/assignments/AJAX/hotbits.php";
  //var data = {"retrieve": true, "id": 1}; //{retrieve: true, id: 1}; also works here, but not in JSON.parse(...);

  var url = $("#url").val(); // https://localhost/assignments/AJAX/hotbits.php
  var json = JSON.parse($("#data").val());

  //var url = "/api/cryptography";
  //var json = {"download": "base64", "retrieve": true, "id": 99};

  var method = "POST";

  //alert("URL: \"" + url + "\" Data: \"" + data + "\"");

  var returnvalue;
  /*$.ajaxSetup({
    beforeSend: function (jqXHR, settings) {
      //settings.xhr().responseType = 'arraybuffer';
      if (settings.dataType === 'binary') {
        settings.xhr().responseType = 'arraybuffer';
      }
    }
  });*/

  $.ajax({
    url: url,
    type: method,
    //dataType: 'binary', // No Conversion From Text to Binary
    async: false,
    data: json,
    beforeSend: function(xhr) {
      /* xhr means XMLHttpRequest */
      //xhr.setRequestHeader("Accept", "application/vnd.github.v3+json");
    }, error: function(data, status, thrown) {
      /* data is the exact same thing as data in complete, but with bad error codes
       * status throws out error, just like how status in complete throws out success
       * thrown tells what type of error it is */

      alert("Error Bytes: " + data.length + " Status: " + status + " Error: " + thrown);
      //returnvalue = [data, "zip"];
      returnvalue = [JSON.stringify(data, null, 2), "json"];
    }, success: function(data, status, xhr) {
      //alert("Success!"); //print("Success!");
      //alert(typeof(data)); // string
      // https://stackoverflow.com/a/3741604/6828099
      var ct = xhr.getResponseHeader("content-type") || "";
      if (ct.indexOf('html') > -1) {
        returnvalue = [data, "html"];
      } else if (ct.indexOf('json') > -1) {
        returnvalue = [JSON.stringify(data, null, 2), "json"];
      } else if (ct.indexOf('csv') > -1) {
        returnvalue = [data, "csv"];
      } else if(ct.indexOf('zip') > -1) {
        //this.href = "https://localhost" + url + "/api/cryptography?id=99&download=true&retrieve=true";
        //this.target = '_blank';
        //this.download = 'cryptography-temp.zip';

        //alert("Bytes: " + data.length); // {"download": true, "retrieve": true, "id": 99}
        returnvalue = [data, "zip"];
      } else {
        returnvalue = [data, "unknown"];
      }
    }, complete: function(data, status) {
      /* data is same as data in success, but with error codes and status messages thrown in with it
       * status is the status message without any other data. status is by default a string, not json */
      /* alert(JSON.stringify(data) + " | " + status); */
    }
  });
  return returnvalue;
}

// https://stackoverflow.com/a/7220510/6828099
function syntaxHighlight(json) {
    if (typeof json != 'string') {
         json = JSON.stringify(json, undefined, 2);
    }
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}