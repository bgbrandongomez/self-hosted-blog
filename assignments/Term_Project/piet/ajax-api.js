$(document).ready(function() {
  //$("#ajax-output-debug").css("max-width", ($("#ajax-table").width() - 20) + "px");
  //$("select").css("max-width", $("#highlight").width() + "px");
  //checkScan("5ca11aadc7743");
});

var SCANID = SCANID || (function(){
    var _args = {}; // private

    return {
        init : function(Args) {
            _args = Args;
            // some other initialising
        },
        setData : function() {
            programid = _args[0];
            //alert("Program ID: " + programid);
            checkScan(programid);
        }
    };
}());

function checkScan(lookupid) {
  var rawData = lookup(lookupid); // Failed: 5ca11aadc7741; Success: 5ca11aadc7742

  if(rawData[1] === "json") {
    if(!rawData[0]["error"]) {
      var rowid = rawData[0]["id"];
      var programid = rawData[0]["programid"];
      var failed = rawData[0]["failed"];

      //alert(failed);

      $("#scanresults").removeClass("hidden");
      $("#scanresults").removeClass("warning");
      $("#scanresults-newline").removeClass("hidden-newline");

      if(failed === "1") {
        // Failed Virus Scan
        $("#scanresults").text("The program " + programid + " failed the virus scan!!!");
        $("#scanresults").addClass("error");
      } else {
        // Passed Virus Scan
        $("#scanresults").text("The program " + programid + " passed the virus scan!!!");
        $("#scanresults").addClass("success");
      }
    } else {
      // Executes if Still Scanning For Viruses
      $("#scanresults").removeClass("hidden");
      $("#scanresults-newline").removeClass("hidden-newline");

      // https://javascript.info/settimeout-setinterval
      setTimeout(checkScan, 1000, lookupid);
    }
  } else if(rawData[1] === "html") {
    // This only shows up on 200 Response.
    //alert(rawData[0]);
    alert("Received HTML Response!!!");
  } else {
    // Never Runs even on 404!!!
    // For Unknown Responses (e.g. Glitched Proxy or Excessive Firewall)
  }
}

function lookup(programid) {
  var url = "/api/antivirus";
  var json = {"programid": programid};

  var method = "POST";

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

      //alert("Error Bytes: \"" + data.length + "\" Status: \"" + status + "\" Error: \"" + thrown + "\"");
      returnvalue = [JSON.stringify(data, null, 2), "json"];
    }, success: function(data, status, xhr) {
      // https://stackoverflow.com/a/3741604/6828099
      var ct = xhr.getResponseHeader("content-type") || "";
      if (ct.indexOf('html') > -1) {
        //alert("HTML");
        returnvalue = [data, "html"];
      } else if (ct.indexOf('json') > -1) {
        returnvalue = [data, "json"]; //[JSON.stringify(data, null, 2), "json"];
      } else {
        // Never Runs even on 404!!!
        alert("Unknown Data Type!!! Are you behind a proxy???");
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