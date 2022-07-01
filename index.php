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

        /*
        h1 {
            background-color: #1F1E1F;
        }

        h2 {
            background-color: #3A393A;
        }

        section {
            background-color: #464546;
        }
        */

        .boxes {
            display: flex;
            margin-left: 20px;
        }

        .boxes>p {
            width: 33vw;
            font-size: small;
        }

        .video-js {
            width: 95vw;
            height: 90vh;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
    <script type="text/javascript" src="./scripts/currentTime.js"></script>
    <script type="text/javascript" src="./scripts/loadtimeCPU.js"></script>
</head>

<body>
    <header class="boxes">
        <p>IPv4 address of the server: <?php include './php/serverADDR.php'; ?> </p>
        <p id="loadtimeCPU"> </p>
        <p id="currentTime"> 00:00:00 </p>
    </header>
    <section>
        <video id="my-player" class="video-js" data-setup='{"poster":"http://<?php include './php/serverADDR.php'; ?>/stream/posterTestScreen_1080p.png",  "controls": true, "preload": "auto", "autoplay": "muted", "liveui": true, "fluid": true}'>
            <source src="http://<?php include './php/serverADDR.php'; ?>/stream/hls/.m3u8" type="application/x-mpegURL">
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
