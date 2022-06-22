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
## WebServer
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
```
sudo mkdir /var/www/html/rtmp
sudo gunzip -c /usr/share/doc/libnginx-mod-rtmp/examples/stat.xsl.gz > /var/www/html/rtmp/stat.xsl
sudo ln -s /etc/nginx/sites-available/rtmp /etc/nginx/sites-enabled/rtmp
sudo mkdir /var/www/html/stream
```
anschließend:
`sudo systemctl reload nginx`  

## RestreamServer 
