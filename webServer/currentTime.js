'use strict';
(function () {

    function time() {
        var now = new Date(),
            h = now.getHours(),
            m = now.getMinutes(),
            s = now.getSeconds();
        m = leadingNull(m);
        s = leadingNull(s);
        document.getElementById('currentTime').innerHTML = `current time: ${h}:${m}:${s}`;
        setTimeout(time, 500);
    }

    function leadingNull(number) {
        number = (number < 10 ? '0' : '') + number;
        return number;
    }
    document.addEventListener('DOMContentLoaded', time);
}());
