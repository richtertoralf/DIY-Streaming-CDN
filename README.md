# DIY-Streaming-CDN
>Wir bauen uns ein eigenes Content Delivery Network (CDN) für die Auslieferung von Livestreams und nutzen dazu ausschließlich Open Source Tools, wie z.B. OBS Studio für die Inhaltserstellung und nginx als Restreamserver, Webserver und Load Balancer.  
>We are building our own content delivery network (CDN) for livestream delivery using only open source tools, such as OBS Studio for content creation and nginx as a restream server, web server and load balancer.
```
                                 |--->  WebServer-01 <---|
                                 |--->  WebServer-02 <---|--- Loadbalancer-01 <---|
OBS-Studio --> RestreamServer -->|--->  WebServer-03 <---|                        |<-- DNS-Server
                                 |--->  WebServer-04 <---|--- Loadbalancer-02 <---|
                                 |--->  WebServer-05 <---|
```
