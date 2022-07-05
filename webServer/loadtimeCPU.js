'use strict';
(function () {

  function loadtimeCPU() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
      if (this.readyState == 4 && this.status == 200) {
        let request = this.responseText;
        let requestTXT = 'CPU-AVG: No values available';
        console.log(request);
        if (isNaN(parseFloat(request))) {
          document.getElementById("loadtimeCPU").innerHTML = 'CPU-AVG: De#e*vIthlapnISpu';
        } else {
          if (request >= 0.80) {
            requestTXT = 'Attention, the server is totally busy.';
          }
          else if (request >= 0.50 && request < 0.80) {
            requestTXT = 'Attention, the server is running hot.';
          }
          else if (request >= 0.30 && request < 0.50) {
            requestTXT = 'Everything is in the green zone.';
          }
          else {
            requestTXT = 'The server is bored.';
          }
          request = `CPU-AVG: ${(request * 100).toFixed(0)} % ... ${requestTXT}`;
          document.getElementById("loadtimeCPU").innerHTML = request;
        }
      }
    };
    xhttp.open("GET", "./php/loadtimeCPU.php", true);
    xhttp.send();
    setTimeout(loadtimeCPU, 1000);
  }
  document.addEventListener('DOMContentLoaded', loadtimeCPU);
}());

// "De#e*vIthlapnISpu" klingonisch = "Diese Info hätte ich gern."
