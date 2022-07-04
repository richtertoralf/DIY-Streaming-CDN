We are building our own content delivery network (CDN) for livestream delivery using only open source tools, such as OBS Studio for content creation and nginx as a restream server, web server and load balancer.
# DIY-Streaming-CDN
>Wir bauen uns ein eigenes Content Delivery Network (CDN) für die Auslieferung von Livestreams und nutzen dazu ausschließlich Open Source Tools, wie z.B. OBS Studio bzw. FFmpeg für die Inhaltserstellung und nginx als Restreamserver, Webserver und LoadBalancer und Keepalived als "Hochverfügbarkeitstool" für den LoadBalancer und den RestreamServer.    

```
# Prinzipskizze
                                 |--->  WebServer-01 <---|
                                 |--->  WebServer-02 <---|--- Loadbalancer-01 <---|
OBS-Studio --> RestreamServer -->|--->  WebServer-03 <---|                        |<-- DNS-Server
                                 |--->  WebServer-04 <---|--- Loadbalancer-02 <---|
                                 |--->  WebServer-05 <---|
```
Als RestreamServer und WebServer will ich virtuelle Cloud Server nutzen. Diese gibt es z.B. bei Hetzner schon ab 4,- Euro im Monat bzw. für sehr wenige Cent pro Stunde. Unser System soll für den jeweiligen Livestream jeweils neu hochgefahren und danach wieder gelöscht werden, um die virtuellen Maschinen nur dann zu nutzen, wenn sie benötigt werden. Dazu soll die Hetzner CLI zum Einsatz kommen.
Infos zu den Hetzner Cloud-Servern: https://www.hetzner.com/de/cloud?country=de und zur CLI: https://community.hetzner.com/tutorials/howto-hcloud-cli   
Load-Balancer gibt es bei Hetzner übrigens auch schon fertig und für kleines Geld: https://www.hetzner.com/de/cloud/load-balancer  

**Ich werde, die einzelnen Server aber zuerst lokal, mit VirtualBox, bauen und testen, bevor ich in die Cloud gehe und die Hetzner CLI nutze. Im Folgenden werden jeweils sehr einfache Konfigurationsbeispiele gezeigt. Vorausgesetzt wird ein grundlegendes Verständnis eines Linuxsystems.  
Ich werde nginx nicht erklären. Dafür gibt es z.B. hier aktuelle Infos:  
https://www.nginx.com/resources/library/complete-nginx-cookbook/   
Als Basis Betriebssystem für die WebServer, den RestreamServer und die Loadbalancer habe ich Ubuntu 20.04 LTS genutzt.**  
`cat /etc/*release` -> **PRETTY_NAME="Ubuntu 20.04.4 LTS"**  

## WebServer
>HTTP Live Streaming mit HLS und DASH  

![Alt Screenshot WebServer](https://github.com/richtertoralf/DIY-Streaming-CDN/blob/5f4263d7f8adb5243a7afe338c76b493c946d272/VideoJS-Testscreen_2022-06-29%20205310.png "Screenshot WebServer")
Ich fange zuerst mit einem Webserver an.  

Quellen und weitere Anleitungen zur Inspiration:  
https://www.digitalocean.com/community/tutorials/how-to-set-up-a-video-streaming-server-using-nginx-rtmp-on-ubuntu-20-04  
https://github.com/arut/nginx-rtmp-module  
https://nginx.org/  
https://bitkeks.eu/blog/2020/03/desktop-video-streaming-server-obs-studio-nginx-rtmp-hls-videojs.html  
```
sudo apt install nginx
sudo apt install libnginx-mod-rtmp
```
Per `sudo nano /etc/nginx/nginx.conf` an die Standardkonfigurationsdatei am Ende folgenden Teil hinzufügen:
```
rtmp {
        server {
                listen 1935;
                chunk_size 4096;

                application live {
                        live on;
                        record off;

                        hls on;
                        hls_path /var/www/html/stream/hls;

                        dash on;
                        dash_path /var/www/html/stream/dash;
                }
        }
}
```
Mit diesem Modul warten wir am rtmp-Standartport 1935 auf eingehende rtmp-Streams und erstellen in der Anwendung (application) "live" HLS und DASH Videoelemente, die wir an den angegebenen Speicherorten ablegen.  
Zusätzlich könnte ich mit
```
                allow publish 127.0.0.1;
                allow publish 192.168.55.0/24;
                deny publish all;
```
im Server-Block festlegen, das nur Streams aus einem bestimmten Netzwerkbereich oder von einem bestimmten Rechner akzeptiert und alle anderen Streams abgelehnt werden.  
>Das macht aus Sicherheitsgründen Sinn, da ich es selbst schon mal erlebt habe, das mir Jemand einen Stream zu meinem RestreamingServer geschickt hat, den ich nicht wollte. Deswegen sollte übrigens auch der Anwendungsname "live" geändert werden, wenn der Server über das Internet erreichbar ist, denn Jeder, der die öffentliche IP-Adresse des Servers kennt, könnte sich mit `rtmp://<öffentliche IP>/live` den Stream z.B. im VLC-Player anzeigen lassen, wenn `live on;` eingeschaltet ist.  

Weitere Infos zu den HLS-Direktiven: https://github.com/arut/nginx-rtmp-module/wiki/Directives#hls  
und den DASH-Direktiven: https://github.com/arut/nginx-rtmp-module/wiki/Directives#mpeg-dash  

Dann per `sudo nano /etc/nginx/sites-available/rtmp` eine neue Datei mit folgendem Inhalt erstellen:
```
server {
    listen 8080;
    server_name  localhost;

    # rtmp stat
    location /stat {
        rtmp_stat all;
        rtmp_stat_stylesheet stat.xsl;
    }
    location /stat.xsl {
        root /var/www/html/rtmp;
    }

    # rtmp control
    location /control {
        rtmp_control all;
    }
}

server {
    listen 8088;

    location / {
        add_header Access-Control-Allow-Origin *;
        root /var/www/html/stream;
    }
}

types {
    application/dash+xml mpd;
}
```
danach:
`sudo mkdir /var/www/html/stream`  

Zusätzlich kann das Statistikmodul für Testzwecke aktiviert werden: 
```
sudo -i
mkdir /var/www/html/rtmp
gunzip -c /usr/share/doc/libnginx-mod-rtmp/examples/stat.xsl.gz > /var/www/html/rtmp/stat.xsl
ln -s /etc/nginx/sites-available/rtmp /etc/nginx/sites-enabled/rtmp
```
anschließend:
`sudo systemctl reload nginx` oder besser erst mit `sudo nginx -t` die Konfiguration testen.  

#### Rechte für den Webserver anpassen (einfaches Beispiel)
Unter Ubuntu laufen nginx und auch php mit dem Benutzer/User "www-data". Um als Benutzer selbst die Dateien bearbeiten zu können, z.B. mit VisualStudio Code per SSH Remote-Zugriff, füge ich mich der Gruppe "www-data" hinzu.  
`sudo addgroup $USER www-data`  
Dem User www-data sollten die Rechte an den statischen Webdateien auch tatsächlich gehören:  
Schau mal mit `ls -al` nach und korrigiere gegebenenfalls mit:  
`chown -R www-date:www-date /var/www/html`  
Die Dateien sollten dem User "www-data" gehören und nicht "root". Das passiert, wenn du nicht als "normaler" User, sondern als "root" arbeitest.      
Normalerweise sind die Rechte nach der Installation richtig gesetzt. Um sie im nachhinein zu korregieren, kannst du so die Rechte für die Verzeichnisse und die Dateien ändern.
```
cd /var/www/
# Rechte für die Verzeichnisse setzen
sudo find . -type d -exec chmod -v 755 {} \;
# Rechte für die Dateien setzen 
sudo find . -type f -exec chmod 644 {} \;
```
**Zur Erinnerung, `chmod`und Rechte unter Linux:**   
>Oktal-Modus: Die Rechte werden für den Dateibesitzer, die Dateigruppe und andere separat addiert. An jeder Stelle bedeutet ein gesetzter Wert, dass die Rechte gewährt und eine 0, dass sie entzogen werden. Die Rechte werden somit durch drei Stellen vollständig beschrieben; die erste Stelle gibt die Rechte des Eigentümers, die zweite die Rechte der Gruppe und die letzte Stelle die Rechte für alle übrigen an.  

|Zahl:| 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 |
|-|-|-|-|-|-|-|-|-|
|Rechte:|keine|x|w|w+r|r|r+x|r+w|r+w+x|  

x...*ausführen*  
w...*schreiben*  
r...*lesen*  

**Beispiel:**  
**755**	Alle dürfen lesen und ausführen, der Eigentümer auch schreiben.  
**644** Der Eigentümer darf lesen und schreiben, alle anderen nur lesen.  

weitere Infos, siehe: https://wiki.ubuntuusers.de/chmod/  

### Webseite zur Auslieferung des Videostreams
Mit `nano /var/www/html/index.html` eine Webseite erstellen, indem du das Folgende in diese Datei einfügst:  
```
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://vjs.zencdn.net/7.19.2/video-js.css" rel="stylesheet" />
    <title>HTTP Live Streaming Example</title>
    <style>
        body {
            background: #4C4C4C;
            color: white;
            font-family: Verdana, Geneva, sans-serif;
        }
        section {
            background-color: #464546;
        }
        .video-js {
            width: 95vw;
            height: 90vh;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>

<body>
    <section>
        <video id="my-player" class="video-js" data-setup='{"controls": true, "preload": "auto", "autoplay": "muted", "liveui": true, "fluid": true}'>
            <source src="http://192.168.55.101/stream/hls/.m3u8" type="application/x-mpegURL">
            <!-- <source src="http://192.168.55.101/stream/dash/.mpd" type="application/dash+xml"> -->
            </source>
            <p class="vjs-no-js">
                To view this video please enable JavaScript, and consider upgrading to a
                web browser that
                <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
            </p>
        </video>
        <script src="https://vjs.zencdn.net/7.19.2/video.js"></script>
    </section>
</body>

</html>

```

#### Testen
Mein WebServer-01 in VirtualBox hat die IP-Adresse **192.168.55.101**  
Die Statistikdaten kannn ich im Browser so abrufen: **http://192.168.55.101:8080/stat** 
Die Webseite mit dem Videostream kann ich mir so anzeigen lassen: **http://192.168.55.101/**   

Dazu benötige ich aber erst noch einen Videostream. Zum Testen kann ich mir dazu z.B. per **OBS Studio** einen Stream zum Webserver schicken. Dazu öffne ich in OBS-Studio die Einstellungen -> Stream -> und gebe hinter Server die Adresse unseres Webservers und den Namen unserer rtmp-Application ein: **rtmp://192.168.55.101/live** Danach starte ich den Stream in OBS und rufe im Webbrowser meinen WebServer **http://192.168.55.101/** auf.  

Alternativ könnten wir uns auch mit ffmpeg einen Teststream erstellen. Hier ein aufwendigeres Beispiel für ein Testbild mit eingeblendeter Uhrzeit (localtime), Dauer des Streams (pts) und einem Stereo Sinuston (sine) und einem Beep jede Sekunde:
```
ffmpeg -loglevel info -re -f lavfi -i smptehdbars=size=1920x1080:rate=60 -f lavfi -i sine=frequency=1000:sample_rate=48000:beep_factor=4 -ac 2 -vf "drawtext=fontsize=140:fontcolor=white:x=1000:y=870:text='%{localtime\:%T}' , drawtext=fontsize=50:fontcolor=white:x=1000:y=1000:text='%{pts\\:hms}'" -c:a aac -c:v libx264 -g 60 -sc_threshold 0 -f flv rtmp://192.168.55.101/live
```
>Damit HLS funktioniert, muss das Audio im Format aac und das Video im Format h264 geliefert werden. Das erreich ich mit ffmpeg, indem ich **-c:a aac -c:v libx264** einfüge. In OBS-Studio sind dafür keine extra Einstellungen notwendig.  

Zum Testen macht es Sinn, einen Teststream zu generieren. Hier eine Idee dazu: https://github.com/richtertoralf/testStreamGenerator  

Da wir aber mehrere WebServer nutzen wollen, benötigen wir davorgeschaltet noch einen RestreamServer, welcher unseren Stream von OBS Studio vervielfältigt und dann zu mehreren Webservern (per push) senden kann.  

## RestreamServer 
![Screenshot example](https://github.com/richtertoralf/DIY-Streaming-CDN/blob/34e2c02c928d1720c32cad2ae74f9b211770fc94/RestreamServer_example_2022-06-29%20212322.png)
```
sudo apt install nginx
sudo apt install libnginx-mod-rtmp
```
Per `sudo nano /etc/nginx/nginx.conf` ergänze ich die Standardkonfigurationsdatei gleich am Anfang hinter `include /etc/nginx/modules-enabled/*.conf;` um folgende Zeile:
`include /etc/nginx/rtmp.conf;`  
und füge eine eigene Konfigurationsdatei im Verzeichnis `/etc/nginx/` mit `sudo nano /etc/nginx/rtmp.conf mit Folgendem Inhalt hinzu. 
```
rtmp {
  server {
    listen 1935;
    chunk_size 4096;
    application restream {
      live on;
      wait_video on;
      publish_notify on;
      drop_idle_publisher 10s;
      record off;
      push rtmp://192.168.55.101/live;
      push rtmp://192.168.55.102/live;
      push rtmp://192.168.55.103/live;
    }
  }
```
Der Name der Application ist freigewählt. Im Produktiveinsatz verwende ich hier jeweils zufällig generierte Strings um die Sicherheit zu erhöhen. 
**Hinter push füge ich jeweils die IP-Adressen meiner Webserver ein.**  
Im Produktiveinsatz werden hier per Script automatisch weitere Server eingebunden, wenn z.B. die Zuschauerzahl steigt.  
Ich kann grundsätzlich in zwei Richtungen Skalieren. Ich kann z.B. die Leistung der einzelnen Webserver vergrößern, indem ich z.B. mehr Kerne hinzufüge. Das Hauptproblemm ist vermutlich aber die Bandbreite. Üblicherweise ist die Bandbreite pro virtuellem Server beschränkt. Indem ich mehrere Webserver hochfahre und die Useranfragen auf die Webserver gleichmäßig verteile, kann ich den Bandbreitenflaschenhals umgehen. Hetzner betreibt z.B. in Europa an drei verschiedenen Standorten Rechenzentren. Dort könnte ich jeweils Webserver hochfahren und per RestreamServer mit dem Content versorgen.    
PS: Wir reden hier übrigens erstmal von wenigen Hundert Zuschauern. Wenn du viele Tausend Zuschauer gleichzeitig erwartest, sind paar grundsätzliche Infrastrukturthemen mit dem Hoster zu klären.  

>Das Thema Sicherheit ist nicht Bestandteil dieser Anleitung. Skalierung und Hochverfügbarkeit (für eine Hobbynutzung - z.B. Livestream für einen Verein, ohne YouTube) will ich aber schon ansprechen!  

## PHP auf dem Webserver und RestreamServer installieren und aktivieren
PHP können wir in unserer Testumgebung nutzen, um mittels eigener Skripte Daten vom Server abzufragen und auf unseren Webseiten anzuzeigen. PHP kann aber auch genutzt werden, um z.B. unserem RestreamsServer über eine Weboberfläche zu konfigurieren und ihm z.B. die PUSH Adressen, nicht nur zu den WebServern, sondern auch zu YouTube mitzuteilen.  
```
# Installieren
sudo apt install php-fpm
```
und aktivieren:  
Die Datei mit `nano /etc/nginx/sites-enabled/default` öffnen und innerhalb des Server-Blocks die Auskommentierung vor "location ~ \.php$" ... entfernen. So sollte es dann aussehen.  
```
        # pass PHP scripts to FastCGI server
        #
        # pass PHP scripts to FastCGI server
        #
        location ~ \.php$ {
                include snippets/fastcgi-php.conf;

                # With php-fpm (or other unix sockets):
                fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        #       # With php-cgi (or other tcp sockets):
        #       fastcgi_pass 127.0.0.1:9000;
        }
```
und in der folgenden Zeile "index.php" hinzufügen:
```
        # Add index.php to the list if you are using PHP
        index index.html index.htm index.nginx-debian.html;
```
Danach die Konfiguration testen mit `nginx-t` und anschließend `nginx -s reload`  

### Webserver um PHP-Skripte ergänzen
Im Folgenden folgen paar Spielereien mit PHP und JavaScript, die für unser "DIY-Streaming-CDN" nicht unbedingt notwendig sind, aber Spaß machen.
#### IP-Adresse des Servers auf der Webseite anzeigen
Ich erstelle ein zusätzliches Verzeichnis und eine php-Datei:  
```
mkdir /var/www/html/php
nano /var/www/html/php/serverADDR.php
```
mit folgendem Inhalt:
```
<?php
$ip_server = $_SERVER['SERVER_ADDR'];
echo "Die Server IP Adresse ist: $ip_server";
?>
```
Damit kann ich mir jetzt aus der Ferne z.B. mit dem Linux-Tool `curl` so die IP Adresse des Webservers anzeigen lassen: `curl 192.168.55.101/php/serverADDR.php`  

Außerdem kann ich mir die jeweilige IP-Adresse des Webservers auch direkt im Browser anzeigen lassen.  
Dazu ergänze ich unsere **index.html Datei** mit `nano /var/www/html/index.html` um die Zeile: `<p> <?php include '/var/www/html/php/serverADDR.php';?> </p>`. Das sieht dann so aus:  
```
<body>
  <section>
    <p> <?php include '/var/www/html/php/serverADDR.php';?> </p>
    <script src="https://vjs.zencdn.net/7.19.2/video.js"></script>
    <video id="my-player" class="video-js" controls="true" preload="true" autoplay="any" width="auto" height="auto" data-setup='{}'>
      <source src="http://192.168.55.101/stream/hls/.m3u8" type="application/x-mpegURL"></source>
    </video>
  </section>
</body>
```
**Damit die PHP-Zeile funktioniert, benenne ich unsere index.html noch in index.php um: `mv index.html index.php`**  
#### Serverauslastung (CPU Average) auf der Webseite anzeigen
Wenn wir uns auch die aktuelle CPU-Auslastung z.B. aller 2 Sekunden anzeigen lassen wollen, benötigen wir zusätzlich ein weiteres PHP-Skript und noch paar Zeilen JavaScript.  
Zuerst ein kleines PHP-Skript:
`nano /var/www/html/php/loadtimeCPU.php`  
```
<?php
$loadtime = sys_getloadavg();
echo 'CPU-AVG: ', $loadtime[0], ' -> ';
if ($loadtime[0] >= 0.80) {
  echo 'Achtung, der Server ist komplett ausgelastet.';
}
elseif ($loadtime[0] >= 0.50 && $loadtime[0] < 0.80) {
  echo 'Der Server läuft langsam heiß.';
}
elseif ($loadtime[0] >= 0.30 && $loadtime[0] < 0.50) {
  echo 'Alles ist im grünen Bereich.';
}
else {
  echo 'Dem Server ist es langweilig.';
}
?>
```
Um zu testen, ob das Skript funktioniert, kann ich es mit `watch`und `curl` aufrufen: `watch curl 192.168.55.101/php/loadtimeCPU.php`. Damit sehe ich aller zwei Sekunden die Serverauslastung.  

Um die CPU-Auslastung auf die Webseite zu bekommen, ergänze ich in unserer `index.php` unseres WebServers die Zeile, in der ich das PHP-Skript aufrufe: `<p> <?php include '/var/www/html/php/serverADDR.php';?> </p>` wie folgt:  
`sudo nano /var/www/html/index.php`  
```
 <p> <?php include '/var/www/html/php/serverADDR.php'; echo " / "; include '/var/www/html/php/loadtimeCPU.php'; ?> </p>
```
Damit rufe ich hintereinander die beiden PHP-Skripte auf. Auf diese Weise bekomme ich aber auch nur einmal die CPU-Auslastung angezeigt.  

Um die CPU-Auslastung aller zwei Sekunden zu bekommen, nutze ich in Folge paar Zeilen JavaScript und Ajax, also Oldscool der späten Neunziger Jahre ;-) 
Auch aus Effizienzgründen und um die Auslastung des WebServers gering zu halten, ist es aber in unserem Fall besser, diese Auswertelogik auf die Clientseite zu verschieben und mit z.B. JavaScript abzubilden und nicht mit PHP auf dem Server abzuarbeiten.   

Wir brauchen ein kleines Stück Java-Skript:  
```
mkdir /var/www/html/scripts
nano /var/www/html/scripts/loadtimeCPU.js
```
```
'use strict';
(function () {

    function loadtimeCPU() {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById("loadtimeCPU").innerHTML =
                    this.responseText;
            }
        };
        xhttp.open("GET", "./php/loadtimeCPU.php", true);
        xhttp.send();
        setTimeout(loadtimeCPU, 1000);
    }
    document.addEventListener('DOMContentLoaded', loadtimeCPU);
}());
```
Und eine Änderung in unserer `index.php`.  
```
    <header class="boxes">
        <p>IPv4 address of the server: <?php include './php/serverADDR.php'; ?> </p>
        <p id="loadtimeCPU">  </p>
        <p id="currentTime"> 00:00:00 </p>
    </header>
```
Dazu noch etwas CSS-Code:
```
        .boxes {
            display: flex;
            margin-left: 20px;
        }

        .boxes>p {
            width: 33vw;
            font-size: small;
        }
```
Mit dem Skript `loadtimeCPU.js` rufe ich jede Sekunde (aller 1000 Milisekunden) das PHP-Skript loadtimeCPU.php auf und bekomme damit die durchschnittliche CPU-Auslastung des Servers der letzten Minute. Diesen Wert schreibe ich jede Sekunde in das HTML-Element mit der ID "loadtimeCPU".  

Alternativ kann ich die Datei `/var/www/html/php/loadtimeCPU.php` so kürzen:
```
<?php
$loadtime = sys_getloadavg();
echo $loadtime[0];
```
und den Client arbeiten lassen, indem ich die Datei `/var/www/html/scripts/loadtimeCPU.js` wie folgt abändere:
```
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
```
#### aktuelle Uhrzeit auf der Webseite anzeigen
Wenn ich mir so, mit ffmpeg ein Testbild sende:
```
ffmpeg -loglevel error -re -f lavfi -i smptehdbars=size=1920x1080:rate=60 -f lavfi -i sine=frequency=1000:sample_rate=48000:beep_factor=4 -ac 2 -vf "drawtext=fontsize=140:fontcolor=white:x=1000:y=870:text='%{localtime\:%T}' , drawtext=fontsize=50:fontcolor=white:x=1000:y=1000:text='%{pts\\:hms}'" -c:a aac -c:v libx264 -g 60 -sc_threshold 0 -f flv rtmp://192.168.55.101/live
```
bekomme ich im Testbild die Uhrzeit angezeigt, wann der Stream erstellt wurde.  
Mit der Uhrzeit im Webbrowser kann ich dann sofort die Latenz zwischen Streamproduktion und Anzeige im Browser per HLS sehen.  
Folgendes kleines Skript bring mir die Uhrzeit des Clientcomputers in den Browser:
`nano /var/www/html/scripts/currentTime.js`  
```
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
```
## LoadBalancer
>Hochverfügbarkeit: Beispiel mit drei WebServern und zwei LoadBalance-Servern (Master und Backup). Als Loadbalancer will ich **Nginx** und für die bessere Verfügbarkeit der LoadBalance-Server **Keepalived** nutzen. Später soll auch noch die Funktion des RestreamServer "Hochverfügbar" gemacht werden.  

### 3 WebServer
Dazu klonen wir unseren WebServer-1. Ich nutze für dieses Beispiel virtuelle Maschinen auf meinem PC. Dazu nutze ich VirtualBox. Das klonen geht über das Linux Terminal oder per Windows Powershell, je nachdem welches Betriebssystem als Host genutzt wird oder über die GUI. Dazu gibt es im Netz jede Menge Anleitungen, deshalb gibt es dazu hier keine weiteren Infos. 
Wichtig sind beim Klonen mit VirtualBox, nachdem die Maschinen erstellt wurden, aber noch folgende vier Schritte:
- IP-Adressen anpassen
- Hostname anpassen
- Maschinen-ID anpassen
- SSH-Schlüssel anpassen
##### IP-Adressen anpassen
Meine Webserver befinden sich bei mir alle in einem separatem virtuellen internen Netzwerk und sollen die IP-Adressen 192.168.55.101 bis .103 haben. Ich habe in diesen Netzwerksegment keinen DNS Server. Mein Router/Gateway hat die IP 192.168.55.1  
Bei Ubuntu-Server 20.04 und 22.04 wird für die Netzwerkverwaltung Netplan genutzt. Auf meinen Rechnern befinden sich die Einstellungen hier: `/etc/netplan/00-installer-config.yaml`  
So sieht der Dateiinhalt aus:
```
network:
  ethernets:
    enp0s3:
      dhcp4: true
    enp0s8:
      addresses:
        - 192.168.55.101/24
      gateway4: 192.168.55.1
      nameservers:
        addresses:
        - 8.8.8.8
  version: 2
```
Es müssen auf den Maschinen jeweils nur die IP-Adressen angepasst werden.
##### Hostname anpassen
zuerst `hostnamectl set-hostname webServer-1`  
und dann in `/etc/hosts`
```
127.0.0.1 localhost
127.0.1.1 webServer-1
```
##### Maschinen-ID anpassen
```
# Variante für Debian
rm -f /etc/machine-id
rm -f /var/lib/dbus/machine-id
systemd-machine-id-setup
ln -sf /etc/machine-id /var/lib/dbus/
```
##### SSH-Schlüssel korrigieren
rm -f /etc/ssh/ssh_host_*
dpkg-reconfigure openssh-server
#### Webserver testen
Auf unserem RestreamServer haben wir in der `/etc/nginx/rtmp.conf` bereits folgende Befehle eingefügt:
```
      push rtmp://192.168.55.101/live;
      push rtmp://192.168.55.102/live;
      push rtmp://192.168.55.103/live;
```
Wir müssten jetzt per Browser alle drei WebServer aufrufen können und müssten jeweils den Teststream sehen.
![Screenshot WebServer](https://github.com/richtertoralf/DIY-Streaming-CDN/blob/1c3ba02b2d6566a4b6f04d5d318376250b9f7266/VideoJS-Testscreens_2022-06-30.png)  

### nginx LoadBalancer
Infos: https://docs.nginx.com/nginx/admin-guide/load-balancer/http-load-balancer/  
Ich fahre eine weitere frische Ubuntu-Server Maschine hoch. Für solche Testzwecke habe ich in VirtualBox eine Auswahl frischer Linux Maschinen, die ich mir bei Bedarf klone und dann anpasse.  
*Warum Ubuntu und nicht Debian oder RockyLinux/CentOS? ... Dafür gibt es keinen besonderen Grund. Für die tägliche Arbeit ist es aber einfacher, nur eine Linux-Distribution zu benutzen.*

### Hochverfügbarkeit per Keepalived
https://www.keepalived.org/
