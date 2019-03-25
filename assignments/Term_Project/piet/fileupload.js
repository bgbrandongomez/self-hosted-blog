window.onload = function() {
  /* https://stackoverflow.com/a/9546968 */
  document.getElementById('piet-file-input').onchange = function(object) {
    var file = document.getElementById('piet-file-input').value;
    var fileName = file.split("\\");
    //alert("FileName: " + fileName[fileName.length-1]);
    document.getElementById("piet-filename").innerHTML = "Selected: " + escapeHtml(fileName[fileName.length-1]);
  };
};

/* https://stackoverflow.com/a/4835406 */
function escapeHtml(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}