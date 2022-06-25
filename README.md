# DIY-Streaming-CDN
>Wir bauen uns ein eigenes Content Delivery Network (CDN) für die Auslieferung von Livestreams und nutzen dazu ausschließlich Open Source Tools, wie z.B. OBS Studio für die Inhaltserstellung und nginx als Restreamserver, Webserver und Load Balancer.  
>We are building our own content delivery network (CDN) for livestream delivery using only open source tools, such as OBS Studio for content creation and nginx as a restream server, web server and load balancer.
```
# Prinzipskizze
                                 |--->  WebServer-01 <---|
                                 |--->  WebServer-02 <---|--- Loadbalancer-01 <---|
OBS-Studio --> RestreamServer -->|--->  WebServer-03 <---|                        |<-- DNS-Server
                                 |--->  WebServer-04 <---|--- Loadbalancer-02 <---|
                                 |--->  WebServer-05 <---|
```
Als RestreamServer und WebServer will ich virtuelle Cloud Server nutzen. Diese gibt es z.B. bei Hetzner schon ab 4,- Euro im Monat bzw. für sehr wenige Cent pro Stunde. Unser System soll für den jeweiligen Livestream jeweils neu hochgefahren und danach wieder gelöscht werden, um die virtuellen Maschinen nur dann zu nutzen, wenn sie benötigt werden. 
Infos zu den Hetzner Cloud-Servern: https://www.hetzner.com/de/cloud?country=de  
Load-Balancer gibt es bei Hetzner auch schon fertig: https://www.hetzner.com/de/cloud/load-balancer  

**Ich empfehle, die einzelnen Server zuerst lokal, z.B. mit VirtualBox, zu testen, anzupassen und zu optimieren. Im Folgenden werden jeweils sehr einfache Konfigurationsbeispiele gezeigt. Vorausgesetzt wird ein grundlegendes Verständnis eines Linuxsystems.  
Ich werde nginx nicht erklären. Dafür gibt es z.B. hier aktuelle Infos:  
https://www.nginx.com/resources/library/complete-nginx-cookbook/   
Ich habe als Basis für die WebServer und RestreamServer Ubuntu 20.04 LTS genutzt.**  
`cat /etc/*release` -> **PRETTY_NAME="Ubuntu 20.04.4 LTS"**  

## WebServer
Ich fange zuerst mit einem Webserver an.  
>HTTP Live Streaming mit HLS  

Quellen:  
https://www.digitalocean.com/community/tutorials/how-to-set-up-a-video-streaming-server-using-nginx-rtmp-on-ubuntu-20-04  
https://github.com/arut/nginx-rtmp-module  
https://nginx.org/
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
                        hls_fragment 3;
                        hls_playlist_length 60;

                        dash on;
                        dash_path /var/www/html/stream/dash;
                }
        }
}
```
Per `sudo nano /etc/nginx/sites-available/rtmp` eine neue Datei mit folgendem Inhalt erstellen:
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
sudo mkdir /var/www/html/rtmp
sudo gunzip -c /usr/share/doc/libnginx-mod-rtmp/examples/stat.xsl.gz > /var/www/html/rtmp/stat.xsl
sudo ln -s /etc/nginx/sites-available/rtmp /etc/nginx/sites-enabled/rtmp
```
anschließend:
`sudo systemctl reload nginx`  

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
        h1 {
            background-color: #1F1E1F;
        }
        h2 {
            background-color: #3A393A;
        }
        section {
            background-color: #464546;
        }
        .video-js {
            width: 95vw;
            height: 95vh;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>

<body>
    <script src="https://vjs.zencdn.net/7.19.2/video.js"></script>
    <video id="my-player" class="video-js" controls="true" preload="true" autoplay="any" width="auto" height="auto" data-setup='{}'>
      <source src="http://192.168.55.101/stream/hls/.m3u8" type="application/x-mpegURL"></source>
    </video>
</body>

```

Mein WebServer-01 in VirtualBox hat die IP-Adresse **192.168.55.101**  
Die Statistikdaten kannn ich im Browser so abrufen: **http://192.168.55.101:8080/stat** 
Die Webseite mit dem Videostream kann ich mir so anzeigen lassen: **http://192.168.55.101/**   
Dazu benötige ich aber erst noch einen Videostream. Zum Testen kann ich mir dazu z.B. per **OBS Studio** einen Stream zum Webserver schicken. Dazu öffne ich in OBS-Studio die Einstellungen -> Stream -> und gebe hinter Server die Adresse unseres Webservers und den Namen unserer rtmp-Application ein: **rtmp://192.168.55.101/live** Danach starte ich den Stream in OBS und rufe im Webbrowser meinen WebServer **http://192.168.55.101/** auf.  

Alternativ könnten wir uns auch mit ffmpeg einen Teststream erstellen. Hier ein aufwendigeres Beispiel für ein Testbild mit eingeblendeter Uhrzeit (localtime), Dauer des Streams (pts) und einem Sinuston (sine) und einem Beep jede Sekunde:
```
ffmpeg -loglevel error -re -f lavfi -i smptehdbars=size=1920x1080:rate=60 -f lavfi -i sine=frequency=1000:sample_rate=48000:beep_factor=4 -ac 2 -vf "drawtext=fontsize=140:fontcolor=white:x=1000:y=870:text='%{localtime\:%T}' , drawtext=fontsize=50:fontcolor=white:x=1000:y=1000:text='%{pts\\:hms}'" -c:v libx264 -g 60 -sc_threshold 0 -f flv rtmp://192.168.55.101/live
```
Quelle: https://github.com/richtertoralf/testStreamGenerator


Da wir aber mehrere WebServer nutzen wollen, benötigen wir davorgeschaltet noch einen RestreamServer, welcher unseren Stream von OBS Studio vervielfältigt und zu mehreren Webservern sendet (per push).
## RestreamServer 
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

## PHP installieren und aktivieren
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

### Webserver um PHP ergänzen
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
Damit kann ich mir jetzt aus der Ferne z.B. mit dem Linux-Tool `curl` so die CPU-Auslastung anzeigen: `curl 192.168.55.101/php/serverADDR.php`  

Damit kann ich mir zum Testen die jeweilige IP-Adresse des Webservers auch direkt im Browser anzeigen lassen.  
Anschließend ergänze ich unsere **index.html Datei** mit `nano /var/www/html/index.html` um die Zeile: `<p> <?php include '/var/www/html/php/serverADDR.php';?> </p>`. Das sieht dann so aus:  
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
`sudo nano /var/www/html/php/loadtimeCPU.php`  
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
**Aus Effizienzgründen ist es aber besser, diese Auswertelogik auf die Clientseite zu verschieben und mit z.B. JavaScript abzubilden, um die Auslastung des WebServers gering zu halten.**  
Um zu testen, ob das Skript funktioniert, kann ich es mit `watch`und `curl` aufrufen: `watch curl 192.168.55.101/php/loadtimeCPU.php`. Damit sehe ich aller zwei Sekunden die Serverauslastung.
