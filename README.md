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

**Ich empfehle, die einzelnen Server zuerst lokal, z.B. mit VirtualBox, zu testen, anzupassen und zu optimieren. Im Folgenden werden jeweils sehr einfache Konfigurationsbeispiele gezeigt. Vorausgesetzt wird ein grundlegendes Verständnis eines Linuxsystems. Ich werde nginx nicht erklären. Dafür gibt es hier Infos:  https://www.nginx.com/resources/library/complete-nginx-cookbook/   
Ich habe als Basis für die WebServer und RestreamServer Ubuntu 20.04 LTS genutzt.**  
`cat /etc/*release` -> **PRETTY_NAME="Ubuntu 20.04.4 LTS"**  

## WebServer
>HTTP Live Streaming mit HLS  

Quellen:  
https://www.digitalocean.com/community/tutorials/how-to-set-up-a-video-streaming-server-using-nginx-rtmp-on-ubuntu-20-04  
https://github.com/arut/nginx-rtmp-module
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
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>

<body>
    <script src="https://vjs.zencdn.net/7.19.2/video.js"></script>
    <video id="my-player" class="video-js" controls="true" preload="auto" auto="true" width="960" height="540" data-setup='{}'>
      <source src="http://192.168.55.101/stream/hls/.m3u8" type="application/x-mpegURL"></source>
    </video>
</body>
```

Mein WebServer-01 in VirtualBox hat die IP-Adresse **192.168.55.101**  
Die Statistikdaten kannn ich im Browser so abrufen: **http://192.168.55.101:8080/stat** 
Die Webseite mit dem Videostream könnte ich mir so anzeigen lassen: **http://192.168.55.101/**  

## RestreamServer 
