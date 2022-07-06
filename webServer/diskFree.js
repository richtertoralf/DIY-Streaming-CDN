'use strict';
(function () {
    function load_diskInfo() {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                let request = JSON.parse(this.responseText);
                let requestTXT = 'Used disk space: No value available';
                // console.log(request);
                // console.log(request.disk_free_space);
                // console.log(request.disk_total_space);
                // console.log(request.disk_use_space);
                // console.log(request.disk_use_space_pct);
                request.disk_use_space_pct = `Disk space in use: ${request.disk_use_space_pct}`;
                document.getElementById("disk_use_space_pct").innerHTML = request.disk_use_space_pct;
            }
        };
        xhttp.open("GET", "./php/diskFree.php", true);
        xhttp.send();
        setTimeout(load_diskInfo, 10000);
    }
    document.addEventListener('DOMContentLoaded', load_diskInfo);
}());
