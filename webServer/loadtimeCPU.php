<?php
$loadtime = sys_getloadavg();

/*
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
*/

echo $loadtime[0];
